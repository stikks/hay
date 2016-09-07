<?php

foreach (range(1,10) as $x) {
    $c = file_get_contents('http://sponge.atp-sevas.com/rabbitmq?text=TestMessage&from=5959&to=2348170814752&smsc=ETISALAT');
    var_dump($c);
}
