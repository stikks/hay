<?php
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

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

require __DIR__ . '/vendor/autoload.php';

session_start();

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

$settings = require __DIR__.'/settings.php';

$GLOBALS['channel'] = $channel;
$GLOBALS['connection'] = $connection;

//$channel->exchange_declare($settings['exchange_name'], 'headers', false, true, false);

$channel->exchange_declare($settings['exchange_name'], 'x-delayed-message', false, true, false, false, false, new AMQPTable(array(
    "x-delayed-type" => "headers"
)));

$channel->queue_declare('queue', false, true, false, false);

$channel->queue_declare($settings['queue_name'], false, true, false, false, false, new AMQPTable(array(
    "x-dead-letter-exchange" => $settings['exchange_name'],
    'x-message-ttl' => $settings['delay'],
    'x-dead-letter-routing-key' => $settings['queue_name']
)));

$channel->queue_bind($settings['queue_name'], $settings['exchange_name']);
//$channel->queue_bind('queue', $settings['exchange_name']);

$app = new \Slim\App();

$app->get('/', function ($request, $response){

//    $username = $request->getParam('username');
//
//    if(!$username) {
//       return $response->withStatus(404)
//        ->withHeader('Content-Type', 'text/html')
//        ->write('username missing');
//    }
//
//    $password = $request->getParam('password');
//
//    if (!$password) {
//       return $response->withStatus(404)
//        ->withHeader('Content-Type', 'text/html')
//        ->write('password missing');
//    }
//
//    $recipient = $request->getParam('to');
//
//    if (!$recipient) {
//    return $response->withStatus(404)
//        ->withHeader('Content-Type', 'text/html')
//        ->write('recipient missing');
//}

    $text = $request->getParam('text');

    if (!$text) {
        return $response->withStatus(404)
            ->withHeader('Content-Type', 'text/html')
            ->write('Text missing');
    }
    $settings = require __DIR__.'/settings.php';

    $channel = $GLOBALS['channel'];

    array_push($_SESSION['data'], $text);

    $headers = new AMQPTable(array("x-delay" => $settings['delay']));
    $message = new AMQPMessage($text, array('delivery_mode' => 2));
    $message->set('application_headers', $headers);
    $channel->basic_publish($message, $settings['exchange_name'], $settings['queue_name']);

    return $response->withStatus(200);
});

$app->run();