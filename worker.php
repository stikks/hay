<?php
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;

echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";

$callback = function($msg) {
    $file = '/var/www/html/text.log';
    $current = file_get_contents($file);
    $current .= $msg->body;
    $current .= "\n";
    file_put_contents($file, $current);
};

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');

$channels = array();

$settings = require __DIR__.'/settings.php';

$channel = $connection->channel();
$channel->basic_qos(null, 1, null);
$channel->basic_consume($settings['queue_name'], '', false, true, false, false, $callback);

while(count($channel->callbacks)) {
    $channel->wait();
}
