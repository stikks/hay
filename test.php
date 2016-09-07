<?php

foreach (range(1,100) as $x) {
    $c = file_get_contents('http://localhost:9090?text=missing');
    var_dump($c);
}