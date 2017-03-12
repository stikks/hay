<?php

require_once __DIR__ . '/vendor/autoload.php';
use Predis\Client;
use PhpAmqpLib\Connection\AMQPStreamConnection;
require __DIR__.'/Service.php';
use Service\Service;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$service = new Service();
$settings = $service->settings;

echo ' [*] Waiting for messages. To exit press CTRL+C', "\n";

//$callback = function($msg){
//    echo ' [x] ', $msg->body, "\n";
//    $headers = $msg->get('application_headers');
//    $nativeData = $headers->getNativeData();
//    var_dump($nativeData);
//};

$callback = function($msg) {

    $headers = $msg->get('application_headers');
    $nativeData = $headers->getNativeData();
    $service = new Service();
    $settings = $service->settings;

    foreach ($nativeData['recipients'] as $rex) {
        echo $rex;
        $url = '' . $nativeData['url'] . '?username=' . $nativeData['username'] . '&password=' . $nativeData['password'] . '&to=' . $rex . '&text=' . $msg->body . '&from=' . $nativeData['from'] . '&smsc=' . $nativeData['smsc'] . '&dlr_mask=' . $nativeData['dlr_mask'] . '&dlr_url=' . $nativeData['dlr_url'] . '';
        file_get_contents($url);
        $data = '[DATETIME:' . $nativeData['timestamp'] . '][STATUS: Accepted][SMSC:' . $nativeData['smsc'] . '][FROM:' . $nativeData['from'] . '][TO:' . $rex . '][MSG:' . $msg->body . ']';
        $log = new Logger('messages.log');
        $log->pushHandler(new StreamHandler($settings['logger']['path'], Logger::INFO));
        $log->info($data);
    }
};

$connection = $service->init_rabbitmq($settings['amqp']['host'], $settings['amqp']['port'], $settings['amqp']['username'], $settings['amqp']['password']);
$channel = $connection->channel();
$channel->exchange_declare($settings['exchange_name'], 'topic', false, false, false);
list($queue_name, ,) = $channel->queue_declare("", false, false, true, false);
$channel->queue_bind($queue_name, $settings['exchange_name']);

$channel->basic_qos(null, 1, null);
$channel->basic_consume($queue_name, '', false, true, false, false, $callback);

while(count($channel->callbacks)) {
    $channel->wait();
}

$channel->close();
$connection->close();
