<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";

$callback = function($msg) {
    $messagePublishedAt = $msg->body;
    echo 'seconds between publishing and consuming: '
        . (date('Y-m-d H:i:s', time()-$messagePublishedAt)) . PHP_EOL;
//    $file = '/var/www/html/text.log';
//    $current = file_get_contents($file);
//    $current .= $msg->body;
//    $current .= "\n";
//    file_put_contents($file, $current);
};

function process_message(AMQPMessage $message)
{
    $headers = $message->get('application_headers');
    $nativeData = $headers->getNativeData();
    var_dump($nativeData['x-delay']);
    var_dump($nativeData);
    var_dump($headers);
//    $message->delivery_info['channel']->basic_nack($message->delivery_info['delivery_tag']);
}

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');

$settings = require __DIR__.'/settings.php';

$channel = $connection->channel();

//$channel->basic_qos(null, 1, null);
//$channel->basic_consume('delayed_queue', '', false, true, false, false, 'process_message');

$channel->basic_consume($settings['queue_name'], '', false, true, false, false, $callback);

while(count($channel->callbacks)) {
    $channel->wait();
}
