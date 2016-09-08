
<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";

$callback = function($msg) {

    $headers = $msg->get('application_headers');
    $nativeData = $headers->getNativeData();

    $res = file_get_contents($nativeData['url'].'?username='.$nativeData['username'].'&password='.$nativeData['password'].'&to='.$nativeData['to'].
        '&text='.$msg->body.'&from='.$nativeData['from'].'&smsc='.$nativeData['smsc'].'&dlr_mask='.$nativeData['dlr_mask'].'&dlr_url='.$nativeData['dlr_url']);

    $file = 'messages.log';
    $current = file_get_contents($file);
//    $now = new DateTime("now", new DateTimeZone("Africa/Lagos"));
//    $_date = $now->format('Y-m-d H:i:s');
    $current .= '[DATETIME:'.$nativeData['timestamp'].'][STATUS: Accepted][SMSC:'.$nativeData['smsc'].'][FROM:'.$nativeData['from'].'][TO:'.$nativeData['to'].'][MSG:'.$msg->body.']';
    $current .= "\n";
    file_put_contents($file, $current);
};

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');

$settings = require __DIR__.'/settings.php';

$channel = $connection->channel();

$channel->basic_qos(null, 1, null);

$channel->basic_consume($settings['queue_name'], '', false, true, false, false, $callback);

while(count($channel->callbacks)) {
    $channel->wait();
}
