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

$callback = function($msg) {

    $headers = $msg->get('application_headers');
    $nativeData = $headers->getNativeData();
    $service = new Service();
    $settings = $service->settings;

    $url = '' . $nativeData['url'] . '?username=' . $nativeData['username'] . '&password=' . $nativeData['password'] . '&to=' . $nativeData['to'] . '&text=' . $msg->body . '&from=' . $nativeData['from'] . '&smsc=' . $nativeData['smsc'] . '&dlr_mask=' . $nativeData['dlr_mask'] . '&dlr_url=' . $nativeData['dlr_url'] . '';
    file_get_contents($url);
    $data = '[DATETIME:' . $nativeData['timestamp'] . '][STATUS: Accepted][SMSC:' . $nativeData['smsc'] . '][FROM:' . $nativeData['from'] . '][TO:' . $nativeData['to'] . '][MSG:' . $msg->body . ']';
    $log = new Logger('messages.log');
    $log->pushHandler(new StreamHandler($settings['logger']['path'], Logger::INFO));
    $log->info($data);
};

$connection = $service->init_rabbitmq($settings['amqp']['host'], $settings['amqp']['port'], $settings['amqp']['username'], $settings['amqp']['password']);
$channel = $connection->channel();
$channel = $service->declare_queue($channel, $settings['queue_name']);

$channel = $connection->channel();
$channel->basic_qos(null, 1, null);
$channel->basic_consume($settings['queue_name'], '', false, true, false, false, $callback);

while(count($channel->callbacks)) {
    $channel->wait();
}