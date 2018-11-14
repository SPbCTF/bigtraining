#!/usr/bin/php
<?php
$SERVICE = "weeper";
$PORT = 6868;

define("KEY_UP", "\x1b[A");
define("KEY_DOWN", "\x1b[B");
define("KEY_LEFT", "\x1b[D");
define("KEY_RIGHT", "\x1b[C");
define("KEY_HOME", "\x1b[H");
define("KEY_END", "\x1b[F");
define("KEY_PAGEUP", "\x1b[5~");
define("KEY_PAGEDN", "\x1b[6~");

define("SPEC_CLEAR", "\x1B[3J\x1B[H\x1B[2J");
define("SPEC_RAW_ENABLE", "\xFF\xFB\x01\xFF\xFB\x03\xFF\xFC\x22");
define("SPEC_RAW_DISABLE", "\xFF\xFC\x01\xFF\xFC\x03\xFF\xFB\x22");

require "_checker_common.php";

function info() {
    close("OK", "vulns: 1");
}

function do_one_move(&$field, &$x, &$y, $moveX, $moveY) {
    $width = strlen($field[0]);
    $height = count($field);
    
    if ($moveX < $x) { ffwrite(str_repeat(KEY_LEFT, $x - $moveX)); }
    if ($moveX > $x) { ffwrite(str_repeat(KEY_RIGHT, $moveX - $x)); }
    if ($moveY < $y) { ffwrite(str_repeat(KEY_UP, $y - $moveY)); }
    if ($moveY > $y) { ffwrite(str_repeat(KEY_DOWN, $moveY - $y)); }
    ffwrite("\n");
    
    while (true) {
        // echo "x: $x, y: $y\n";
        $c = ffgetc();
        if ($c == "\x1b") {
            fexpect("[", "MUMBLE", "Got no cursor movement");
            $c = ffgetc() . ffgetc();
            if ($c == '1A') $y--;
            elseif ($c == '1B') $y++;
            elseif ($c == '2D') $x--;
            elseif ($c == '2C') $x++;
            else close("MUMBLE", "Bad cursor movement", $c);
            
            if ($x < 1 || $x > $width || $y < 1 || $y > $height) {
                close("MUMBLE", "Out of field cursor movement", "x: $x, y: $y");
            }
        } elseif ($c == "\r") {
            fexpect("\x1B[");
            freaduntil("B");
            return 1;  // win
        } else {
            // echo "field: " . urlencode($c) . "\n";
            $field[$y - 1][$x - 1] = $c;
            ffgetc();

            if ($c == "W") {
                fexpect("\x1b[2D\r\x1B[");
                freaduntil("B");
                return -1;  // boom
            }

            fexpect("\x1B[2D", "MUMBLE", "", "No caret return");
            
            if ($x == $moveX && $y == $moveY) {
                return 0;  // game waits for input
            }
        }
    }
}

function mine_list_from_text($text) {
    $glyphs = [
        'A' => [[1,0], [0,1], [2,1], [0,2], [1,2], [2,2], [0,3], [2,3], ],
        'B' => [[0,0], [0,1], [1,1], [0,2], [2,2], [0,3], [1,3], ],
        'C' => [[1,0], [2,0], [0,1], [0,2], [1,3], [2,3], ],
        'D' => [[2,0], [1,1], [2,1], [0,2], [2,2], [1,3], [2,3], ],
        'E' => [[0,0], [1,0], [2,0], [0,1], [1,1], [2,1], [0,2], [0,3], [1,3], [2,3], ],
        'F' => [[0,0], [1,0], [2,0], [0,1], [0,2], [1,2], [2,2], [0,3], ],
        'G' => [[0,0], [1,0], [2,0], [0,1], [0,2], [2,2], [0,3], [1,3], [2,3], ],
        'H' => [[0,0], [0,1], [1,1], [0,2], [2,2], [0,3], [2,3], ],
        'I' => [[0,0], [1,0], [2,0], [1,1], [1,2], [0,3], [1,3], [2,3], ],
        'J' => [[0,0], [1,0], [2,0], [1,1], [1,2], [0,3], [1,3], ],
        'K' => [[0,0], [2,0], [0,1], [1,1], [0,2], [2,2], [0,3], [2,3], ],
        'L' => [[0,0], [0,1], [0,2], [0,3], [1,3], [2,3], ],
        'M' => [[0,0], [1,0], [2,0], [0,1], [1,1], [2,1], [0,2], [1,2], [2,2], [0,3], [2,3], ],
        'N' => [[0,1], [1,1], [0,2], [2,2], [0,3], [2,3], ],
        'O' => [[0,0], [1,0], [2,0], [0,1], [2,1], [0,2], [2,2], [0,3], [1,3], [2,3], ],
        'P' => [[0,0], [1,0], [0,1], [2,1], [0,2], [1,2], [0,3], ],
        'Q' => [[1,0], [2,0], [0,1], [2,1], [1,2], [2,2], [2,3], ],
        'R' => [[0,0], [1,0], [2,0], [0,1], [1,1], [2,1], [0,2], [1,2], [0,3], [2,3], ],
        'S' => [[0,0], [1,0], [2,0], [0,1], [1,1], [2,2], [0,3], [1,3], [2,3], ],
        'T' => [[0,0], [1,0], [2,0], [1,1], [1,2], [1,3], ],
        'U' => [[0,0], [2,0], [0,1], [2,1], [0,2], [2,2], [0,3], [1,3], [2,3], ],
        'V' => [[0,0], [2,0], [0,1], [2,1], [0,2], [2,2], [1,3], ],
        'W' => [[0,0], [2,0], [0,1], [1,1], [2,1], [0,2], [1,2], [2,2], [1,3], ],
        'X' => [[0,0], [2,0], [1,1], [0,2], [2,2], [0,3], [2,3], ],
        'Y' => [[0,0], [2,0], [0,1], [2,1], [1,2], [1,3], ],
        'Z' => [[0,0], [1,0], [2,0], [1,1], [2,1], [0,2], [0,3], [1,3], [2,3], ],
        '0' => [[1,0], [0,1], [2,1], [0,2], [2,2], [1,3], ],
        '1' => [[1,0], [0,1], [1,1], [1,2], [0,3], [1,3], [2,3], ],
        '2' => [[0,0], [1,0], [2,1], [0,2], [1,2], [0,3], [1,3], [2,3], ],
        '3' => [[0,0], [1,0], [1,1], [2,1], [2,2], [0,3], [1,3], ],
        '4' => [[0,0], [2,0], [0,1], [2,1], [0,2], [1,2], [2,2], [2,3], ],
        '5' => [[0,0], [1,0], [2,0], [0,1], [1,2], [2,2], [0,3], [1,3], ],
        '6' => [[1,0], [2,0], [0,1], [0,2], [1,2], [2,2], [1,3], [2,3], ],
        '7' => [[0,0], [1,0], [2,0], [2,1], [1,2], [0,3], ],
        '8' => [[1,0], [0,1], [1,1], [2,1], [0,2], [2,2], [1,3], ],
        '9' => [[1,0], [0,1], [2,1], [1,2], [2,2], [0,3], [1,3], ],
        '=' => [[0,1], [1,1], [2,1], [0,2], [1,2], [2,2], ],
    ];
    
    $mineList = [];
    
    foreach (str_split($text) as $i => $c) {
        if (isset($glyphs[$c])) {
            foreach ($glyphs[$c] as $m) {
                $mineList[] = [$m[0] + 4 * $i + 2, $m[1] + 4];
            }
        }
    }
    
    return $mineList;
}

function gen_mine_list($width, $height, $mines) {
    $mineList = [];
    $were = [];
    for ($i = 0; $i < $mines; $i++) {
        do {
            $x = mt_rand(1, $width);
            $y = mt_rand(1, $height);
        } while (isset($were["$x,$y"]));
        
        $mineList[] = [$x, $y];
        $were["$x,$y"] = true;
    }
    
    return $mineList;
}

function gen_field($width, $height, $mineList) {
    $field = [];
    for ($y = 1; $y <= $height; $y++) {
        $field[] = str_repeat("0", $width);
    }
    foreach ($mineList as $m) {
        list ($x, $y) = $m;
        
        $field[$y - 1][$x - 1] = '*';
        
        for ($tx = max($x - 1, 1); $tx <= min($x + 1, $width); $tx++) {
            for ($ty = max($y - 1, 1); $ty <= min($y + 1, $height); $ty++) {
                if ($field[$ty - 1][$tx - 1] != '*') {
                    $field[$ty - 1][$tx - 1] = $field[$ty - 1][$tx - 1] + 1;
                }
            }
        }
    }
    
    return $field;
}

function check($ip) {
    global $PORT;

    ffsockopen($ip, $PORT);
    $prompt = freaduntil("=>");
    closeif(md5($prompt) != "278b556e0c003033e884c6269f041d28", "MUMBLE", "Can't get welcome menu", "Wrong welcome text, length " . strlen($prompt));
    
    if (mt_rand(0, 3) == 0) {  // upload homebrew
        ffwrite(str_repeat(KEY_DOWN, 2) . "\n");
        freaduntil("Name of the game: ");
        ffwrite(randstr(mt_rand(12, 24), "LOHINUM") . "\n");

        $width = mt_rand(10, 80);
        $height = mt_rand(10, 36);
        $mines = ceil($width * $height * 0.05);
        $mineList = gen_mine_list($width, $height, $mines);
        
        fexpect("Field size (WxHxM): ");
        ffwrite($width . "x" . $height . "x" . $mines . "\n");
        fexpect("Enter ");
        freaduntil(" ");
        fexpect("mines:   x,y x,y x,y ...\r\n");
        foreach ($mineList as $m) {
            list ($x, $y) = $m;
            ffwrite("$x,$y ");
        }
        ffwrite("\n");
        
        fexpect("Enter trophy for the winner: ");
        ffwrite(randstr(mt_rand(24, 48), "LOHINUM") . "\n");
        
        freaduntil("Homebrew game saved!");
        freaduntil("=>");
        ffwrite("\n");
        freaduntil("---------");
        freaduntil("=>");
    }
    
    $whatGame = mt_rand(0, 2);  // 0 = none, 1 = quick, 2 = homebrew
    
    if ($whatGame == 1) {  // play quick game
        ffwrite("\n");
        $prompt = freaduntil("=>");
        // echo ">>>" . md5($prompt) . "<<<\n";
        closeif(md5($prompt) != "2e4e00c3dbf4d0443308a84e0454ff31", "MUMBLE", "Can't get quick game menu", "Wrong difficulty text, length " . strlen($prompt));
        
        $diff = mt_rand(0, 3);
        $width = [9, 16, 30, 48][$diff];
        $height = [9, 16, 16, 32][$diff];
        
        ffwrite(str_repeat(KEY_DOWN, $diff) . "\n");
        freaduntil(SPEC_CLEAR);
        fexpect("If you're VIP, enter your VIP club pass (7): ", "MUMBLE", "No VIP pass prompt");
        for ($i = 6; $i >= 0; $i--) {
            fexpect("\x1b[s\x1b[1;42H" . $i . "\x1b[u", "MUMBLE", "No VIP pass countdown");
        }
        fexpect(SPEC_CLEAR);
        
        $firstLine = ffgets();
        closeif(!preg_match('#^\+-+\+\r\n$#s', $firstLine, $mt), "MUMBLE", "Wrong quick game field", "Wrong first line of field");
        $gotWidth = (strlen(trim($firstLine)) - 2) / 2;
        $gotHeight = 0;
        while (true) {
            $line = ffgets();
            if ($line == $firstLine) {
                break;
            }
            $gotHeight++;
            closeif($line != "|" . str_repeat("_ ", $gotWidth) . "|\r\n", "MUMBLE", "Wrong quick game field", "Wrong inner line of field");
        }
        
        closeif($gotWidth != $width, "MUMBLE", "Wrong quick game field size", "Field width $gotWidth != expected $width");
        closeif($gotHeight != $height, "MUMBLE", "Wrong quick game field size", "Field height $gotHeight != expected $height");
        
        $field = [];
        for ($i = 0; $i < $height; $i++) {
            $field[] = str_repeat('_', $width);
        }
        
        freaduntil("C");
        
        $x = (int)($width / 2) + 1;
        $y = (int)($height / 2) + 1;
        $moves = [];
        while (true) {
            do {
                $moveX = mt_rand(1, $width);
                $moveY = mt_rand(1, $height);
            } while ($field[$moveY - 1][$moveX - 1] != '_');
            
            $moves[] = [$moveX, $moveY];
            $outcome = do_one_move($field, $x, $y, $moveX, $moveY);
            
            if ($outcome != 0) {
                break;
            }
            
            if (mt_rand(0, 20) == 0) {
                ffwrite("s");
                freaduntil("B");
                fexpect("\r\nYour saved game: ");
                $saved = trim(freaduntil("\r\n"));
                
                $expectSaved = implode(", ", array_map(function ($m) { return "($m[0], $m[1])"; }, $moves));
                closeif($saved != $expectSaved, "MUMBLE", "Wrong savegame", "Got length " . strlen($saved));
                
                break;
            }
        }

        fexpect(SPEC_CLEAR);
        
        freaduntil("=>");
        ffwrite("\n");
        freaduntil("---------");
        freaduntil("=>");
    }
    
    if ($whatGame == 2) {  // play homebrew game
        ffwrite(KEY_DOWN . "\n");
        freaduntil("---------");
        freaduntil("Choose Homebrew game to play");
        $menu = freaduntil("---------");
        closeif(!strstr($menu, "[+] More...") || !strstr($menu, "<- back"), "MUMBLE", "Bad homebrew game menu");
        
        preg_match_all('#(?<=\r\n)\| +(\S+) +\|(?=\r\n)#s', $menu, $mt);
        $availableBrews = $mt[1];
        
        if (empty($availableBrews) || mt_rand(0, 4) == 0) {  // use more nonexistent
            ffwrite(str_repeat(KEY_DOWN, count($availableBrews)) . "\n");
            freaduntil("Newest and freshest homebrew games: ");
            $freshest = trim(freaduntil("\r\n\r\n"), "\r\n");
            fexpect("Name of the game: ");
            ffwrite(randstr() . "\n");
            freaduntil("Can't open game file!");
            freaduntil("=>");
            ffwrite("\n");
            freaduntil("---------");
            freaduntil("=>");
        } else {  // use existent
            $brewIdx = mt_rand(0, count($availableBrews) - 1);
            $brewName = $availableBrews[$brewIdx];
            if (mt_rand(0, 3)) {  // use cursor keys to choose
                ffwrite(str_repeat(KEY_DOWN, $brewIdx) . "\n");
                freaduntil(SPEC_RAW_DISABLE);
                fexpect(SPEC_CLEAR);
            } else {  // use more to choose
                ffwrite(str_repeat(KEY_DOWN, count($availableBrews)) . "\n");
                freaduntil("Newest and freshest homebrew games: ");
                $freshest = trim(freaduntil("\r\n\r\n"), "\r\n");
                fexpect("Name of the game: ");
                ffwrite($brewName . "\n");
                fexpect(SPEC_CLEAR);
            }

            // play randomly as no vip
            fexpect("If you're VIP, enter your VIP club pass (7): ", "MUMBLE", "No VIP pass prompt");
            for ($i = 6; $i >= 0; $i--) {
                fexpect("\x1b[s\x1b[1;42H" . $i . "\x1b[u", "MUMBLE", "No VIP pass countdown");
            }
            fexpect(SPEC_CLEAR);
            
            $firstLine = ffgets();
            closeif(!preg_match('#^\+-+\+\r\n$#s', $firstLine, $mt), "MUMBLE", "Wrong homebrew game field", "Wrong first line of field");
            $gotWidth = (strlen(trim($firstLine)) - 2) / 2;
            $gotHeight = 0;
            while (true) {
                $line = ffgets();
                if ($line == $firstLine) {
                    break;
                }
                $gotHeight++;
                closeif($line != "|" . str_repeat("_ ", $gotWidth) . "|\r\n", "MUMBLE", "Wrong homebrew game field", "Wrong inner line of field");
            }
            
            $width = $gotWidth;
            $height = $gotHeight;
            
            $field = [];
            for ($i = 0; $i < $height; $i++) {
                $field[] = str_repeat('_', $width);
            }
            
            freaduntil("C");
            
            $x = (int)($width / 2) + 1;
            $y = (int)($height / 2) + 1;
            $moves = [];
            while (true) {
                do {
                    $moveX = mt_rand(1, $width);
                    $moveY = mt_rand(1, $height);
                } while ($field[$moveY - 1][$moveX - 1] != '_');
                
                $moves[] = [$moveX, $moveY];
                $outcome = do_one_move($field, $x, $y, $moveX, $moveY);
                
                if ($outcome != 0) {
                    break;
                }
            
                if (mt_rand(0, 8) == 0) {
                    ffwrite("s");
                    freaduntil("B");
                    fexpect("\r\nYour saved game: ");
                    $saved = trim(freaduntil("\r\n"));
                    
                    $expectSaved = implode(", ", array_map(function ($m) { return "($m[0], $m[1])"; }, $moves));
                    closeif($saved != $expectSaved, "MUMBLE", "Wrong savegame", "Got length " . strlen($saved));
                    
                    break;
                }
            }

            fexpect(SPEC_CLEAR);
            
            freaduntil("=>");
            ffwrite("\n");
            freaduntil("---------");
            freaduntil("=>");
        }
    }
    
    // freadall();
    
    ffwrite(str_repeat(KEY_DOWN, 3) . "\n");
    freaduntil("\xff");
    fexpect("\xfc\x01\xff\xfc\x03\xff\xfb\x22");
    
    close("OK");
}

function put($ip, $flagId, $flag) {
    global $PORT;

    ffsockopen($ip, $PORT);
    $prompt = freaduntil("=>");
    closeif(md5($prompt) != "278b556e0c003033e884c6269f041d28", "MUMBLE", "Can't get welcome menu", "Wrong welcome text, length " . strlen($prompt));
    
    ffwrite(str_repeat(KEY_DOWN, 2) . "\n");
    freaduntil("Name of the game: ");
    ffwrite($flagId . "\n");

    $fieldType = mt_rand(0, 99);
    if ($fieldType < 40) {  // just random expert
        $width = 30;
        $height = 16;
        $mines = 99;
        $mineList = gen_mine_list($width, $height, $mines);
        $field = gen_field($width, $height, $mineList);
        $prize = $flag;
    } elseif ($fieldType < 90) {  // qr with flag
        $field = explode("\n", trim(shell_exec("./qr-gen '$flag'"), "\n"));
        $width = strlen($field[0]);
        $height = count($field);
        $mineList = [];
        $mines = 0;
        for ($y = 1; $y <= $height; $y++) {
            for ($x = 1; $x <= $width; $x++) {
                if ($field[$y - 1][$x - 1] == '*') {
                    $mineList[] = [$x, $y];
                    $mines++;
                }
            }
        }
        $field = gen_field($width, $height, $mineList);
        $prize = randstr(mt_rand(24, 48), "LOHINUM");
    } else {  // text with flag
        $width = 2 + 4 * strlen($flag);
        $height = 10;
        $mineList = mine_list_from_text($flag);
        $mines = count($mineList);
        $field = gen_field($width, $height, $mineList);
        $prize = randstr(mt_rand(24, 48), "LOHINUM");
    }
    
    fexpect("Field size (WxHxM): ");
    ffwrite($width . "x" . $height . "x" . $mines . "\n");
    fexpect("Enter ");
    freaduntil(" ");
    fexpect("mines:   x,y x,y x,y ...\r\n");
    foreach ($mineList as $m) {
        list ($x, $y) = $m;
        ffwrite("$x,$y ");
    }
    ffwrite("\n");
    
    fexpect("Enter trophy for the winner: ");
    ffwrite($prize . "\n");
    
    freaduntil("Homebrew game saved!");
    freaduntil("=>");
    ffwrite("\n");
    freaduntil("---------");
    freaduntil("=>");
    
    ffwrite(str_repeat(KEY_DOWN, 3) . "\n");
    freaduntil("\xff");
    fexpect("\xfc\x01\xff\xfc\x03\xff\xfb\x22");
    
    @mkdir("_weeper_state");
    @mkdir("_weeper_state/$ip");
    file_put_contents("_weeper_state/$ip/$flagId", implode("\n", $field) . "\n");
    
    close("OK", "$flagId:$prize");
}

function get($ip, $state, $flag) {
    global $PORT;
    
    if (!strstr($state, ":")) {
        close("CORRUPT", "Previous flag wasn't put");
    }
    list ($flagId, $flag) = explode(":", $state);
    
    if (!is_file("_weeper_state/$ip/$flagId")) {
        close("CHECKER_ERROR", "Checker can't remember the field", "No such file: _weeper_state/$ip/$flagId");
    }
    $secretField = explode("\n", trim(file_get_contents("_weeper_state/$ip/$flagId")));
    $width = strlen($secretField[0]);
    $height = count($secretField);

    ffsockopen($ip, $PORT);
    $prompt = freaduntil("=>");
    closeif(md5($prompt) != "278b556e0c003033e884c6269f041d28", "MUMBLE", "Can't get welcome menu", "Wrong welcome text, length " . strlen($prompt));
    
    ffwrite(KEY_DOWN . "\n");
    freaduntil("---------");
    freaduntil("Choose Homebrew game to play");
    $menu = freaduntil("---------");
    closeif(!strstr($menu, "[+] More...") || !strstr($menu, "<- back"), "MUMBLE", "Bad homebrew game menu");
    
    preg_match_all('#(?<=\r\n)\| +(\S+) +\|(?=\r\n)#s', $menu, $mt);
    $availableBrews = $mt[1];
    
    if (!in_array($flagId, $availableBrews) || mt_rand(0, 4) == 0) {  // use more to choose
        ffwrite(str_repeat(KEY_DOWN, count($availableBrews)) . "\n");
        freaduntil("Newest and freshest homebrew games: ");
        $freshest = trim(freaduntil("\r\n\r\n"), "\r\n");
        closeif(!strstr($freshest, "$flagId, "), "MUMBLE", "Can't find game in extended list", "Length of freshest: " . strlen($freshest));
        fexpect("Name of the game: ");
        ffwrite($flagId . "\n");
        fexpect(SPEC_CLEAR);
    } else {  // use cursor keys to choose
        $brewIdx = array_search($flagId, $availableBrews);
        ffwrite(str_repeat(KEY_DOWN, $brewIdx) . "\n");
        freaduntil(SPEC_RAW_DISABLE);
        fexpect(SPEC_CLEAR);
    }

    // play knowingly as vip
    fexpect("If you're VIP, enter your VIP club pass (7): ", "MUMBLE", "No VIP pass prompt");
    if (!is_file("_weeper_state/$ip/.checker-clubpass")) {
        $clubPass = "";
    } else {
        $clubPass = trim(file_get_contents("_weeper_state/$ip/.checker-clubpass"));
    }
    ffwrite("$clubPass\n");
    $res = ffread(10);
    closeif($res !== "Enter new ", "CHECKER_ERROR", "Checker can't use its club pass", "Response: $res");
    fexpect("club pass: ");
    
    $newClubPass = randstr(32, "LOHINUM");
    ffwrite("$newClubPass\n");
    fexpect("Club pass saved\n");
    @mkdir("_weeper_state");
    @mkdir("_weeper_state/$ip");
    file_put_contents("_weeper_state/$ip/.checker-clubpass", "$newClubPass\n");
    
    fexpect(SPEC_CLEAR);
    freaduntil("\x1b[1;1H");
    
    $firstLine = ffgets();
    closeif(!preg_match('#^\+-+\+\r\n$#s', $firstLine, $mt), "MUMBLE", "Wrong homebrew game field", "Wrong first line of field");
    $gotWidth = (strlen(trim($firstLine)) - 2) / 2;
    $gotHeight = 0;
    while (true) {
        $line = ffgets();
        if ($line == $firstLine) {
            break;
        }
        $gotHeight++;
        closeif($line != "|" . str_repeat("_ ", $gotWidth) . "|\r\n", "MUMBLE", "Wrong homebrew game field", "Wrong inner line of field");
    }
    
    closeif($gotWidth != $width, "MUMBLE", "Wrong game field size", "Field width $gotWidth != expected $width");
    closeif($gotHeight != $height, "MUMBLE", "Wrong game field size", "Field height $gotHeight != expected $height");
    
    $field = [];
    for ($i = 0; $i < $height; $i++) {
        $field[] = str_repeat('_', $width);
    }
    
    freaduntil("C");
    
    $x = (int)($width / 2) + 1;
    $y = (int)($height / 2) + 1;
    $moves = [];
    while (true) {
        $found = false;
        for ($moveY = 1; $moveY <= $height; $moveY++) {
            for ($moveX = 1; $moveX <= $width; $moveX++) {
                if ($field[$moveY - 1][$moveX - 1] == '_' && $secretField[$moveY - 1][$moveX - 1] != '*') {
                    $found = true;
                    break(2);
                }
            }
        }
        
        if (! $found) {
            fexpect("\r\x1B[", "MUMBLE", "Can't win");
            freaduntil("B");
            $outcome = 1;
            break;
        }
        
        $moves[] = [$moveX, $moveY];
        $outcome = do_one_move($field, $x, $y, $moveX, $moveY);
        
        if ($outcome != 0) {
            break;
        }
    }
    
    closeif($outcome == -1, "MUMBLE", "Checker lost the game");

    fexpect(SPEC_CLEAR);
    
    // freadall();
    
    freaduntil("Your prize: ");
    ffgets();
    $prize = ffgets();
    $prize = trim($prize, "\r\n| ");
    
    closeif($prize !== $flag, "CORRUPT", "Got wrong flag!", "Got '$prize', expected '$flag'");
    
    freaduntil("=>");
    ffwrite("\n");
    freaduntil("---------");
    freaduntil("=>");
    
    ffwrite(str_repeat(KEY_DOWN, 3) . "\n");
    freaduntil("\xff");
    fexpect("\xfc\x01\xff\xfc\x03\xff\xfb\x22");
    
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
