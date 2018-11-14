#!/usr/bin/php
<?php
$SERVICE = "pumba";
$PORT = 33333;

require "_checker_common.php";

define("OP_REG", pack("Q", 0x2E6157E22E6157E2));
define("OP_SEND", pack("Q", 0x5E8D5E8D5E8D5E8D));
define("OP_READ", pack("Q", 0x2EAD2EAD2EAD2EAD));
define("OP_USERS", pack("Q", 0x1157ACC51157ACC5));
define("OP_MSGS", pack("Q", 0x115711578355A6E5));
define("OP_ABOUT", pack("Q", 0xAB08750F7847E));

define("ST_SUCCESS", pack("Q", 0x5ACCE55));
define("ST_FAIL", pack("Q", 0xFA11002E));

function u64random() {
    return substr(md5(microtime() . mt_rand() . mt_rand() . mt_rand(), true), 0, 8);
}

function u64tostr($u64) {
    return bin2hex(strrev($u64));
}

function strtou64($str) {
    return strrev(hex2bin($str));
}

function do_reg($userId, $key) {
    $packId = u64random();
    ffwrite($packId . OP_REG . $userId . $key . "\0");
    
    $rePackId = ffread(8);
    closeif($rePackId !== $packId, "DOWN", "Reply packet ID doesn't match", "Expected " . bin2hex($packId) . ", got " . bin2hex($rePackId));
    $status = ffread(8);
    if ($status === ST_SUCCESS) {
        $reUserId = ffread(8);
        closeif($reUserId !== $userId, "MUMBLE", "Reply user ID doesn't match", "Expected " . bin2hex($userId) . ", got " . bin2hex($reUserId));
        return true;
    } elseif ($status === ST_FAIL) {
        $errorMsg = trim(freaduntil("\0"), "\0");
        close("MUMBLE", "Can't register: $errorMsg");
    } else {
        close("MUMBLE", "Got neither success nor failure", "Got " . bin2hex($status));
    }
}

function do_send($fromId, $toId, $message) {
    $packId = u64random();
    ffwrite($packId . OP_SEND . $fromId . $toId . $message . "\0");
    
    $rePackId = ffread(8);
    closeif($rePackId !== $packId, "DOWN", "Reply packet ID doesn't match", "Expected " . bin2hex($packId) . ", got " . bin2hex($rePackId));
    $status = ffread(8);
    if ($status === ST_SUCCESS) {
        $msgId = ffread(8);
        $cipherText = trim(freaduntil("\0"), "\0");
        closeif(!preg_match('#^([a-f0-9]{2}){1,255}$#s', $cipherText), "MUMBLE", "Ciphertext is not valid hex");
        closeif(strlen($cipherText) < strlen($message) * 2, "MUMBLE", "Ciphertext is too short");
        closeif(strlen($cipherText) > strlen($message) * 2, "MUMBLE", "Ciphertext is too long");
        return [$msgId, $cipherText];
    } elseif ($status === ST_FAIL) {
        $errorMsg = trim(freaduntil("\0"), "\0");
        close("MUMBLE", "Can't send message: $errorMsg");
    } else {
        close("MUMBLE", "Got neither success nor failure", "Got " . bin2hex($status));
    }
}

function do_read($userId, $msgId, $key) {
    $packId = u64random();
    ffwrite($packId . OP_READ . $userId . $msgId . $key . "\0");
    
    $rePackId = ffread(8);
    closeif($rePackId !== $packId, "DOWN", "Reply packet ID doesn't match", "Expected " . bin2hex($packId) . ", got " . bin2hex($rePackId));
    $status = ffread(8);
    if ($status === ST_SUCCESS) {
        $message = trim(freaduntil("\0"), "\0");
        return $message;
    } elseif ($status === ST_FAIL) {
        $errorMsg = trim(freaduntil("\0"), "\0");
        close("MUMBLE", "Can't read message: $errorMsg");
    } else {
        close("MUMBLE", "Got neither success nor failure", "Got " . bin2hex($status));
    }
}

function do_users() {
    $packId = u64random();
    ffwrite($packId . OP_USERS);
    
    $rePackId = ffread(8);
    closeif($rePackId !== $packId, "DOWN", "Reply packet ID doesn't match", "Expected " . bin2hex($packId) . ", got " . bin2hex($rePackId));
    $status = ffread(8);
    if ($status === ST_SUCCESS) {
        list (, $numUsers) = unpack("Q", ffread(8));
        closeif($numUsers < 0 || $numUsers > 1000000, "MUMBLE", "Wrong number of users: $numUsers");
        $userList = [];
        for ($i = 0; $i < $numUsers; $i++) {
            $userList[ffread(8)] = true;
        }
        return $userList;
    } elseif ($status === ST_FAIL) {
        $errorMsg = trim(freaduntil("\0"), "\0");
        close("MUMBLE", "Can't list users: $errorMsg");
    } else {
        close("MUMBLE", "Got neither success nor failure", "Got " . bin2hex($status));
    }
}

function do_msgs($userId) {
    $packId = u64random();
    ffwrite($packId . OP_MSGS . $userId);
    
    $rePackId = ffread(8);
    closeif($rePackId !== $packId, "DOWN", "Reply packet ID doesn't match", "Expected " . bin2hex($packId) . ", got " . bin2hex($rePackId));
    $status = ffread(8);
    if ($status === ST_SUCCESS) {
        list (, $numMsgs) = unpack("Q", ffread(8));
        closeif($numMsgs < 0 || $numMsgs > 100000, "MUMBLE", "Wrong number of messages: $numMsgs");
        $msgList = [];
        for ($i = 0; $i < $numMsgs; $i++) {
            $msgId = ffread(8);
            $cipherText = trim(freaduntil("\0"), "\0");
            $msgList[$msgId] = $cipherText;
        }
        return $msgList;
    } elseif ($status === ST_FAIL) {
        $errorMsg = trim(freaduntil("\0"), "\0");
        close("MUMBLE", "Can't list messages: $errorMsg");
    } else {
        close("MUMBLE", "Got neither success nor failure", "Got " . bin2hex($status));
    }
}

function do_about() {
    $packId = u64random();
    ffwrite($packId . OP_ABOUT);
    
    $rePackId = ffread(8);
    closeif($rePackId !== $packId, "DOWN", "Reply packet ID doesn't match", "Expected " . bin2hex($packId) . ", got " . bin2hex($rePackId));
    $status = ffread(8);
    if ($status === ST_SUCCESS) {
        $about = trim(freaduntil("\0"), "\0");
        closeif($about !== "PUblic Message BoArd - encrypts your messages so you don't have to.", "MUMBLE", "About text invalid", "Got '$about'");
        return true;
    } elseif ($status === ST_FAIL) {
        $errorMsg = trim(freaduntil("\0"), "\0");
        close("MUMBLE", "Can't get about: $errorMsg");
    } else {
        close("MUMBLE", "Got neither success nor failure", "Got " . bin2hex($status));
    }
}

function info() {
    close("OK", "vulns: 1");
}

function check($ip) {
    global $PORT;
    
    if (! DEBUG) {
        close("OK");  // this checker does checks in put/get
    }

    ffsockopen($ip, $PORT);
    
    // do_send(strtou64("1c52fd5bd8c2e4a6"), strtou64("1c52fd5bd8c2e4a6"), "hello, world");
    var_dump(do_read(strtou64("1c52fd5bd8c2e4a6"), strtou64("c46c54b7ec0f2ac3"), "0RQJWcf05St4jOhMA1K"));
    
    close("OK");
    
    if (mt_rand(0, 2)) {
        do_about();
    }
    
    if (mt_rand(0, 10)) {  // register new user
        $userId = u64random();
        $key = randstr(mt_rand(10, 20), "LOHINUM");
        do_reg($userId, $key);
        
        if (mt_rand(0, 3) == 0) {  // register another user as recipient
            $recpId = u64random();
            $recpKey = randstr(mt_rand(10, 20), "LOHINUM");
            do_reg($recpId, $recpKey);
        } else {
            $recpId = $userId;
            $recpKey = $key;
        }
        
        if (mt_rand(0, 4)) {  // check recipient in list
            $userList = do_users();
            closeif(!isset($userList[$recpId]), "MUMBLE", "Can't find fresh user in list", "Looking for user " . u64tostr($recpId));
        }
        
        if (mt_rand(0, 8)) {  // send message
            $message = randstr(mt_rand(10, 20), "LOHINUM");
            list ($msgId, $ciphertext) = do_send($userId, $recpId, $message);
            
            if (mt_rand(0, 8)) {  // check message in list
                $msgList = do_msgs($recpId);
                closeif(!isset($msgList[$msgId]), "MUMBLE", "Can't find fresh message in list", "Looking for msg " . u64tostr($msgId) . " of user " . u64tostr($recpId));
                closeif($msgList[$msgId] !== $ciphertext, "MUMBLE", "Can't find fresh ciphertext in list", "Looking for msg " . u64tostr($msgId) . " of user " . u64tostr($recpId));
            }
            
            if (mt_rand(0, 8)) {  // read message
                $reMessage = do_read($recpId, $msgId, $recpKey);
                closeif($reMessage !== $message, "MUMBLE", "Can't get fresh message", "Looking for msg " . u64tostr($msgId) . " of user " . u64tostr($recpId));
            }
        }
    }
    
    close("OK");
}

function put($ip, $flagId, $flag) {
    global $PORT;

    ffsockopen($ip, $PORT);
    
    if (mt_rand(0, 2)) {
        do_about();
    }
    
    if (mt_rand(0, 10)) {  // register new user
        $userId = u64random();
        $key = randstr(mt_rand(10, 20), "LOHINUM");
        do_reg($userId, $key);
        
        if (mt_rand(0, 3) == 0) {  // register another user as recipient
            $recpId = u64random();
            $recpKey = randstr(mt_rand(10, 20), "LOHINUM");
            do_reg($recpId, $recpKey);
        } else {
            $recpId = $userId;
            $recpKey = $key;
        }
        
        if (mt_rand(0, 4)) {  // check recipient in list
            $userList = do_users();
            closeif(!isset($userList[$recpId]), "MUMBLE", "Can't find fresh user in list", "Looking for user " . u64tostr($recpId));
        }
        
        if (mt_rand(0, 8)) {  // send message
            $message = randstr(mt_rand(10, 20), "LOHINUM");
            list ($msgId, $ciphertext) = do_send($userId, $recpId, $message);
            
            if (mt_rand(0, 8)) {  // check message in list
                $msgList = do_msgs($recpId);
                closeif(!isset($msgList[$msgId]), "MUMBLE", "Can't find fresh message in list", "Looking for msg " . u64tostr($msgId) . " of user " . u64tostr($recpId));
                closeif($msgList[$msgId] !== $ciphertext, "MUMBLE", "Can't find fresh ciphertext in list", "Looking for msg " . u64tostr($msgId) . " of user " . u64tostr($recpId));
            }
            
            if (mt_rand(0, 8)) {  // read message
                $reMessage = do_read($recpId, $msgId, $recpKey);
                closeif($reMessage !== $message, "MUMBLE", "Can't get fresh message", "Looking for msg " . u64tostr($msgId) . " of user " . u64tostr($recpId));
            }
        }
    }
    
    $flagUserId = substr(md5($flagId, true), 0, 8);
    $flagUserKey = randstr(mt_rand(10, 20), "LOHINUM");
    do_reg($flagUserId, $flagUserKey);
    
    if (!isset($recpId)) {
        $recpId = $flagUserId;
        $recpKey = $flagUserKey;
    }
    
    list ($msgId, $ciphertext) = do_send($recpId, $flagUserId, $flag);
    
    close("OK", u64tostr($flagUserId) . ":" . $flagUserKey . ":" . u64tostr($msgId) . ":" . $ciphertext);
}

function get($ip, $state, $flag) {
    global $PORT;

    if (!strstr($state, ":")) {
        close("CORRUPT", "Previous flag wasn't put");
    }
    list ($flagUserId, $flagUserKey, $flagMsgId, $flagCiphertext) = explode(":", $state);
    $flagUserId = strtou64($flagUserId);
    $flagMsgId = strtou64($flagMsgId);

    ffsockopen($ip, $PORT);
    
    if (mt_rand(0, 2)) {
        do_about();
    }
    
    if (mt_rand(0, 10)) {  // register new user
        $userId = u64random();
        $key = randstr(mt_rand(10, 20), "LOHINUM");
        do_reg($userId, $key);
        
        if (mt_rand(0, 3) == 0) {  // register another user as recipient
            $recpId = u64random();
            $recpKey = randstr(mt_rand(10, 20), "LOHINUM");
            do_reg($recpId, $recpKey);
        } else {
            $recpId = $userId;
            $recpKey = $key;
        }
        
        if (mt_rand(0, 4)) {  // check recipient in list
            $userList = do_users();
            closeif(!isset($userList[$recpId]), "MUMBLE", "Can't find fresh user in list", "Looking for user " . u64tostr($recpId));
        }
        
        if (mt_rand(0, 8)) {  // send message
            $message = randstr(mt_rand(10, 20), "LOHINUM");
            list ($msgId, $ciphertext) = do_send($userId, $recpId, $message);
            
            if (mt_rand(0, 8)) {  // check message in list
                $msgList = do_msgs($recpId);
                closeif(!isset($msgList[$msgId]), "MUMBLE", "Can't find fresh message in list", "Looking for msg " . u64tostr($msgId) . " of user " . u64tostr($recpId));
                closeif($msgList[$msgId] !== $ciphertext, "MUMBLE", "Can't find fresh ciphertext in list", "Looking for msg " . u64tostr($msgId) . " of user " . u64tostr($recpId));
            }
            
            if (mt_rand(0, 8)) {  // read message
                $reMessage = do_read($recpId, $msgId, $recpKey);
                closeif($reMessage !== $message, "MUMBLE", "Can't get fresh message", "Looking for msg " . u64tostr($msgId) . " of user " . u64tostr($recpId));
            }
        }
    }
    
    $userList = do_users();
    closeif(!isset($userList[$flagUserId]), "CORRUPT", "Can't find user id in list", "Looking for user " . u64tostr($flagUserId));
    
    $msgList = do_msgs($flagUserId);
    closeif(!isset($msgList[$flagMsgId]), "CORRUPT", "Can't find message in list", "Looking for msg " . u64tostr($flagMsgId) . " of user " . u64tostr($flagUserId));
    closeif($msgList[$flagMsgId] !== $flagCiphertext, "CORRUPT", "Can't find ciphertext in list", "Looking for msg " . u64tostr($flagMsgId) . " of user " . u64tostr($flagUserId));
    
    $reMessage = do_read($flagUserId, $flagMsgId, $flagUserKey);
    
    closeif($reMessage !== $flag, "CORRUPT", "Flag doesn't match", "Got '$reMessage', expecting '$flag'");
    
    close("OK");
}

if ($argv[1] == "info") {
    info();
} elseif ($argv[1] == "check") {
    check($argv[2]);
} elseif ($argv[1] == "put") {
    put($argv[2], $argv[3], $argv[4]);
} elseif ($argv[1] == "get") {
    get($argv[2], $argv[3], $argv[4]);
}
