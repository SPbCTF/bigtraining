<?php
foreach ($argv as $i => $arg) {
    if ($arg === "DEBUG") {
        array_splice($argv, $i, 1);
        define("DEBUG", true);
        break;
    }
}
if (!defined("DEBUG")) {
    define("DEBUG", false);
}

chdir(__DIR__);

function close($code, $public = "", $private = "") {
    if (is_numeric($code)) {
        $exitCode = $code;
    } elseif ($code == "OK") {
        $exitCode = 101;
    } elseif ($code == "CORRUPT") {
        $exitCode = 102;
    } elseif ($code == "MUMBLE") {
        $exitCode = 103;
    } elseif ($code == "DOWN") {
        $exitCode = 104;
    } elseif ($code == "CHECKER_ERROR") {
        $exitCode = 110;
    } else {
        $exitCode = 110;
    }

    if (!empty($public)) {
        echo "$public\n";
    }
    if (!empty($private)) {
        fwrite(STDERR, "$private\n");
    }
    fwrite(STDERR, "Exit with code $code\n");
    exit($exitCode);
}

function closeif($condition, $code, $public = "", $private = "") {
    if ($condition) {
        close($code, $public, $private);
    }
}

function randstr($len = false, $charset = "LO") {
    if (is_string($len)) {
        $charset = $len;
        $len = false;
    }
    if ($len === false) {
        $len = mt_rand(5, 20);
    }

    if (preg_match('#^(LO|HI|NUM)+$#s', $charset)) {
        preg_match_all('#LO|HI|NUM#s', $charset, $mt);
        $charset = "";
        foreach ($mt[0] as $m) {
            if ($m == "LO") {
                $charset .= "abcdefghijklmnopqrstuvwxyz";
            } elseif ($m == "HI") {
                $charset .= "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
            } elseif ($m == "NUM") {
                $charset .= "0123456789";
            }
        }
    }

    $str = "";
    $charsetUp = strlen($charset) - 1;
    for ($i = 0; $i < $len; $i++) {
        $str .= $charset[mt_rand(0, $charsetUp)];
    }
    return $str;
}

function http($uri, $method = "GET", $headers = "", $data = false, $postfile = false, $addParams = false) {
  static $cr = NULL;

  if (DEBUG) {
      fwrite(STDERR, "== HTTP Request:\n");
      fwrite(STDERR, "$method $uri\n");
      if ($headers != "") {
          fwrite(STDERR, "$headers\n");
      }
      if ($data) {
          fwrite(STDERR, "Data: $data\n");
      }
      fwrite(STDERR, "\n");
  }

  if ($cr === NULL) {
    $cr = curl_init();
    curl_setopt_array($cr, array(CURLOPT_HEADER => 1,
                                 CURLOPT_RETURNTRANSFER => 1,
                                 CURLOPT_SSL_VERIFYPEER => 0,
                                 CURLOPT_SSL_VERIFYHOST => 0,
                                 CURLOPT_COOKIEFILE => "",
                                 CURLOPT_CONNECTTIMEOUT => 2,
                                 CURLOPT_TIMEOUT => 5,
                           ));
  }

  curl_setopt($cr, CURLOPT_POST, (strtoupper($method) == "POST" ? 1 : 0));
  curl_setopt($cr, CURLOPT_URL, $uri);

  if ($data !== false) {
    if ($postfile) {
      $arr = array();
      foreach (explode("&", $data) as $pd) {
        list ($key, $val) = explode("=", $pd, 2);
        if ($val[0] == '@') {
         $arr[$key] = curl_file_create(substr($val, 1));
        } else {
         $arr[$key] = $val;
        }
      }
      curl_setopt($cr, CURLOPT_POSTFIELDS, $arr);
    } else {
     curl_setopt($cr, CURLOPT_POSTFIELDS, $data);
    }
  }
  curl_setopt($cr, CURLOPT_NOBODY, strtoupper($method) == "HEAD");
  curl_setopt($cr, CURLOPT_CUSTOMREQUEST, $method);
  curl_setopt($cr, CURLOPT_HTTPHEADER, explode("\r\n", trim($headers)));

  if (is_array($addParams)) {
    curl_setopt_array($cr, $addParams);
  }

  $res = curl_exec($cr);

  if (DEBUG) {
      fwrite(STDERR, "== HTTP Response:\n");
      fwrite(STDERR, "$res\n");
  }

  return $res;
}

function ffsockopen($host, $port, $timeout = 3, $dontPanic = false) {
    global $S;
    
    if (DEBUG) {
        fwrite(STDERR, "Opening connection to $host:$port\n");
    }
    $s = @fsockopen($host, $port, $nul, $nul, $timeout);
    if (! $s) {
        if (! $dontPanic) {
            close("DOWN", "Can't connect to $host:$port");
        }
        return false;
    }
    stream_set_timeout($s, $timeout);
    
    $S = $s;
    
    return $s;
}

function ffread($s, $len = NULL) {
    global $S;
    
    if (func_num_args() == 1) {
        $len = $s;
        $s = $S;
    }
    
    $buf = "";
    while (strlen($buf) < $len) {
        $chunk = fread($s, $len - strlen($buf));
        if ($chunk === '' || $chunk === false) {
            break;
        }
        if (DEBUG) {
            fwrite(STDERR, $chunk);
        }
        $buf .= $chunk;
    }
    if ($buf == '' && $len > 0) {
        return false;
    }
    return $buf;
}

function ffgetc($s = NULL, $dontPanic = false) {
    global $S;
    
    if (func_num_args() == 0) {
        $s = $S;
    } elseif (func_num_args() == 1 && !is_resource($s)) {
        $dontPanic = $s;
        $s = $S;
    }
    
    $chunk = fgetc($s);
    if ($chunk === '' || $chunk === false) {
        if (! $dontPanic) {
            close("DOWN", "Can't read char from socket");
        }
    }
    if (DEBUG) {
        fwrite(STDERR, $chunk);
        fflush(STDERR);
    }
    return $chunk;
}

function freadall($s = NULL) {
    global $S;
    
    if (func_num_args() == 0) {
        $s = $S;
    }
    
    if (DEBUG) {
        fwrite(STDERR, "*** freadall\n");
        fflush(STDERR);
    }
    $buf = "";
    while (true) {
        $chunk = fgetc($s);
        if ($chunk === '' || $chunk === false) {
            break;
        }
        if (DEBUG) {
            fwrite(STDERR, $chunk);
            fflush(STDERR);
        }
        $buf .= $chunk;
    }
    return $buf;
}

function ffgets($s = NULL, $dontPanic = false) {
    global $S;
    
    if (func_num_args() == 0) {
        $s = $S;
    } elseif (func_num_args() == 1 && !is_resource($s)) {
        $dontPanic = $s;
        $s = $S;
    }
        
    $line = fgets($s);
    if ($line === '' || $line === false) {
        if (! $dontPanic) {
            close("DOWN", "Can't read line from socket");
        }
    }
    if (DEBUG) {
        fwrite(STDERR, $line);
        fflush(STDERR);
    }
    return $line;
}

function ffwrite($s, $what = NULL, $dontPanic = false) {
    global $S;
    
    if (func_num_args() == 1) {
        $what = $s;
        $s = $S;
    } elseif (func_num_args() == 2 && !is_resource($s)) {
        $dontPanic = $what;
        $what = $s;
        $s = $S;
    }
    
    if (DEBUG && $what != "") {
        $debugWhat = $what;
        if (substr($what, -1) == "\n") {
            $debugWhat = substr($what, 0, -1);
        }
        fwrite(STDERR, ">>> $debugWhat\n");
        fflush(STDERR);
    }
    while (strlen($what) > 0) {
        $written = fwrite($s, $what);
        if ($written == 0) {
            if (! $dontPanic) {
                close("DOWN", "Can't write to socket", $what);
            }
            break;
        }
        $what = substr($what, $written);
    }
}

function freaduntil($s, $what = NULL, $dontPanic = false) {
    global $S;
    
    if (func_num_args() == 1) {
        $what = $s;
        $s = $S;
    } elseif (func_num_args() == 2 && !is_resource($s)) {
        $dontPanic = $what;
        $what = $s;
        $s = $S;
    }
    
    $buf = "";
    while (substr($buf, -strlen($what)) !== $what) {
        $toRead = max(1, strlen($what) - strlen($buf));
        while ($toRead < strlen($what)) {
            if (substr($buf, -(strlen($what) - $toRead)) === substr($what, 0, strlen($what) - $toRead)) {
                break;
            } else {
                $toRead++;
            }
        }
        $chunk = fread($s, $toRead);
        if ($chunk === '' || $chunk === false) {
            if (! $dontPanic) {
                close("MUMBLE", "Can't read from socket", "Waiting for '$what', got ...'" . substr($buf, -strlen($what) * 2) . "'");
            }
            break;
        }
        if (DEBUG) {
            fwrite(STDERR, $chunk);
            fflush(STDERR);
        }
        $buf .= $chunk;
    }
    return $buf;
}

function fexpect($s, $what = NULL, $code = "MUMBLE", $public = false, $private = false) {
    global $S;
    
    if (func_num_args() == 1) {
        $what = $s;
        $s = $S;
    } elseif (func_num_args() == 2 && !is_resource($s)) {
        $code = $what;
        $what = $s;
        $s = $S;
    } elseif (func_num_args() == 3 && !is_resource($s)) {
        $public = $code;
        $code = $what;
        $what = $s;
        $s = $S;
    } elseif (func_num_args() == 4 && !is_resource($s)) {
        $private = $public;
        $public = $code;
        $code = $what;
        $what = $s;
        $s = $S;
    }
    
    $buf = ffread($s, strlen($what));
    if ($buf !== $what) {
        if ($public === false) {
            $public = "Got bad reply";
        }
        if ($private === false) {
            $private = "Waiting for '$what', got '$buf'";
        }
        close($code, $public, $private);
    }
    return $buf;
}
