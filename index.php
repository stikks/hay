<?php
require __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
require __DIR__ . '/vendor/predis/predis/autoload.php';
use Predis\Client;
require __DIR__.'/Service.php';
use Service\Service;

if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

# connect to redis
$redis = new Client();

$service = new Service($redis);
$GLOBALS['service'] = $service;

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
//$connection = new ExtendedConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

$GLOBALS['channel'] = $channel;
$GLOBALS['connection'] = $connection;$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
//$connection = new ExtendedConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();
$GLOBALS['settings'] = require __DIR__.'/settings.php';;

$settings = $GLOBALS['settings'];

//$channel->exchange_declare($settings['exchange_name'], 'headers', false, true, false);

$channel->exchange_declare($settings['exchange_name'], 'x-delayed-message', false, true, false, false, false, new AMQPTable(array(
    "x-delayed-type" => "headers"
)));

//$channel->queue_declare('queue', false, true, false, false);

$channel->queue_declare($settings['queue_name'], false, true, false, false, false, new AMQPTable(array(
    "x-dead-letter-exchange" => $settings['exchange_name'],
//    'x-message-ttl' => $settings['delay'],
    'x-dead-letter-routing-key' => $settings['queue_name']
)));

$channel->queue_bind($settings['queue_name'], $settings['exchange_name']);
//$channel->queue_bind('queue', $settings['exchange_name']);

$app = new \Slim\App();

$app->get('/', function ($request, $response){

    $from = $request->getParam('from');

    if(!$from) {
       return $response->withStatus(404)
        ->withHeader('Content-Type', 'text/html')
        ->write('from missing');
    }

    $smsc = $request->getParam('smsc');

    if (!$smsc) {
       return $response->withStatus(404)
        ->withHeader('Content-Type', 'text/html')
        ->write('smsc missing');
    }

    $recipient = $request->getParam('to');

    if (!$recipient) {
    return $response->withStatus(404)
        ->withHeader('Content-Type', 'text/html')
        ->write('recipient missing');
}

    $text = $request->getParam('text');

    if (!$text) {
        return $response->withStatus(404)
            ->withHeader('Content-Type', 'text/html')
            ->write('Text missing');
    }
    $settings = require __DIR__.'/settings.php';

    $channel = $GLOBALS['channel'];
    $service = $GLOBALS['service'];

    $time = $service->get_timestamp();

    $message = new AMQPMessage($text, array(
        'delivery_mode' => 2,
        'priority' => 1,
        'timestamp' => $time
    ));
    $headers = new AMQPTable(array(
        "x-delay" => $settings['delay'],
        'url' => $settings['extended_url']['url'],
        'password' => $settings['extended_url']['password'],
        'username' => $settings['extended_url']['username'],
        'to' => $recipient,
        'from' => $from,
        'timestamp'=> $time
    ));
    $message->set('application_headers', $headers);
    $channel->basic_publish($message, $settings['exchange_name'], $settings['queue_name']);

    return $response->withStatus(200);
});

$app->get('/dlr', function ($request, $response) {
    var_dump($request->getParams());
});

$app->run();