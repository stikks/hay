
<?php

require __DIR__.'/connection.php';

echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";

$callback = function($msg) {

    $headers = $msg->get('application_headers');
    $nativeData = $headers->getNativeData();

    $url = ''.$nativeData['url'].'?username='.$nativeData['username'].'&password='.$nativeData['password'].'&to='.$nativeData['to']. '&text='.$msg->body.'&from='.$nativeData['from'].'&smsc='.$nativeData['smsc'].'&dlr_mask='.$nativeData['dlr_mask'].'&dlr_url='.$nativeData['dlr_url'].'';

    file_get_contents($url);
    $file = 'messages.log';
//    $current = file_get_contents($file);
    $data = '[DATETIME:'.$nativeData['timestamp'].'][STATUS: Accepted][SMSC:'.$nativeData['smsc'].'][FROM:'.$nativeData['from'].'][TO:'.$nativeData['to'].'][MSG:'.$msg->body.']';
    file_put_contents($file, $data.PHP_EOL, FILE_APPEND);
};

$settings = require __DIR__.'/settings.php';

$channel = $connection->channel();

//$channel->basic_qos(null, 1, null);

$channel->basic_consume($settings['queue_name'], '', false, true, false, false, $callback);

while(count($channel->callbacks)) {
    $channel->wait();
}
