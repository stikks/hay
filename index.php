<?php
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

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

$channel->exchange_declare($settings['exchange_name'], 'headers', false, true, false);

$channel->queue_declare($settings['queue_name'], false, true, false, false);

$channel->queue_bind($settings['queue_name'], $settings['exchange_name']);

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

    $channel = $GLOBALS['channel'];
    
    $msg = new AMQPMessage($text);
    $channel->batch_basic_publish($msg, 'exchange');
    
    $channel->publish_batch();
    
    return $response->withStatus(200);
});

$app->run();