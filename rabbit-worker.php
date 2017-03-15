<?php
date_default_timezone_set('Africa/Lagos');

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

    foreach ($nativeData['recipients'] as $rex) {
        echo $rex. PHP_EOL;
        $url = '' . $nativeData['url'] . '?username=' . $nativeData['username'] . '&password=' . $nativeData['password'] . '&to=' . $rex . '&text=' . $msg->body . '&from=' . $nativeData['from'] . '&smsc=' . $nativeData['smsc'] . '&dlr_mask=' . $nativeData['dlr_mask'] . '&dlr_url=' . $nativeData['dlr_url'] . '';
//        file_get_contents($url);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_exec($ch);
        $info = curl_getinfo($ch);
        $status = $info['http_code'] == $settings['DEFAULT_HTTP_RESPONSE'] ? 'Accepted': 'Failed';
        curl_close($ch);

        $data = '[DATETIME:' . $nativeData['timestamp'] . '][STATUS:' . $status .'][SMSC:' . $nativeData['smsc'] . '][FROM:' . $nativeData['from'] . '][TO:' . $rex . '][MSG:' . $msg->body . '][URL:'. $url. ']';
        $log = new Logger($settings['logger']['name']);
        $log->pushHandler(new StreamHandler($settings['logger']['path'], Logger::INFO));
        $log->info($data);
    }
};

$connection = $service->init_rabbitmq($settings['amqp']['host'], $settings['amqp']['port'], $settings['amqp']['username'], $settings['amqp']['password']);
$channel = $connection->channel();
$channel->exchange_declare($settings['exchange_name'], 'topic', false, false, false);
//list($queue_name, ,) = $channel->queue_declare("", false, false, true, false);
//$channel->queue_bind($queue_name, $settings['exchange_name']);

$channel->queue_declare($settings['queue_name'], false, true, false, false);
$channel->queue_bind($settings['queue_name'], $settings['exchange_name']);

$channel->basic_qos(null, 1, null);
$channel->basic_consume($settings['queue_name'], '', false, true, false, false, $callback);

while(count($channel->callbacks)) {
    $channel->wait();
}

$channel->close();
$connection->close();
