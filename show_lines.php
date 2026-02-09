<?php
$lines = file('challenge.php');
foreach ($lines as $i => $line) {
    $num = $i + 1;
    echo str_pad($num, 4, ' ', STR_PAD_LEFT) . ': ' . $line;
}
