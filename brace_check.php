<?php
$lines = file('challenge.php');
$level = 0;
foreach ($lines as $i => $line) {
    $num = $i + 1;
    for ($j = 0; $j < strlen($line); $j++) {
        $ch = $line[$j];
        if ($ch === '{') { $level++; }
        elseif ($ch === '}') { $level--; }
    }
    echo str_pad($num,4,' ',STR_PAD_LEFT) . ": level={$level} -> " . rtrim($line) . "\n";
}
echo "Final level: $level\n";
