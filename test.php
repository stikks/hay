<?php

foreach (range(1,10) as $x) {
    $c = file_get_contents('http://sponge.atp-sevas.com/hay/sendsms?text=TestMessage&from=5959&to=2348170814752&smsc=ETISALAT&dlr_mask=31&dlr_url='.urlencode('http://sponge.atp-sevas.com/hay/dlr'));
    var_dump($c);
}
