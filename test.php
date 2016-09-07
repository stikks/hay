<?php

foreach (range(1,10) as $x) {
    $c = file_get_contents('http://localhost:9090?text=missing&from=abc&to=08170814752&smsc=goat');
    var_dump($c);
}
