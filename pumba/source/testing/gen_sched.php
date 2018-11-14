<?php
echo "        ";
for ($r = 0; $r < 12; $r++) {
    echo "if ((i % 12) == $r) {\n";
    
    echo "            c = " . mt_rand(0, 255) . ";\n";
    $rounds = mt_rand(20, 40);

    for ($i = 0; $i < $rounds; $i++) {
        $op = ['+', '-', '*', '^', '&', '&', '&', '&', '&'];
        $op = $op[mt_rand(0, count($op) - 1)];
        $o1 = ['c', 'K(c)'];
        $o1 = $o1[mt_rand(0, count($o1) - 1)];
        if (mt_rand(0, 1)) {
            $o1 = str_replace('c', 'i+c', $o1);
        }
        if (mt_rand(0, 1)) {
            $o1 = str_replace('c', 'c+(ROLu64(nonce,'.mt_rand(1,63).') & 0xFF)', $o1);
        }
        if (mt_rand(0, 4) == 0) {
            $o1 = "ROLchar(" . $o1 . ",".mt_rand(1,7).")";
        }

        $o2 = ['c', 'K(c)', mt_rand(0, 255)];
        $o2 = $o2[mt_rand(0, count($o2) - 1)];
        if (mt_rand(0, 1)) {
            $o2 = str_replace('c', 'c+(ROLu64(nonce,'.mt_rand(1,63).') & 0xFF)', $o2);
        }
        if (mt_rand(0, 4) == 0) {
            $o2 = "ROLchar(" . $o2 . ",".mt_rand(1,7).")";
        }
        
        echo "            c = ($o1) $op ($o2);\n";
    }

    echo "        } else ";
}
echo "{}";
echo "\n";