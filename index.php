<?php
require __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Predis\Client;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

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
$channel = $connection->channel();
$settings = require __DIR__.'/settings.php';;

$channel->exchange_declare('exchange', 'headers', false, true, false);
$channel->queue_declare('queue', false, true, false, false);
$channel->queue_bind('queue', 'exchange');

$channel->exchange_declare($settings['exchange_name'], 'headers', false, true, false, false, false, new AMQPTable(array(
    "x-delayed-type" => "headers"
)));

$channel->queue_declare($settings['queue_name'], false, true, false, false, false, new AMQPTable(array(
    "x-dead-letter-exchange" => $settings['exchange_name'],
//    'x-message-ttl' => $settings['delay'],
    'x-dead-letter-routing-key' => $settings['queue_name']
)));

//$channel->queue_declare('persistent_sevas', false, true, false, false);
$channel->queue_bind($settings['queue_name'], $settings['exchange_name']);

$GLOBALS['channel'] = $channel;
$GLOBALS['connection'] = $connection;
$GLOBALS['settings'] =  $settings;

$app = new \Slim\App();

$app->group('', function (){
    $this->get('/sendsms', function ($request, $response){

        $settings = require __DIR__.'/settings.php';

        $from = $request->getParam('from');

        if(!$from) {
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'text/html')
                ->write('from missing');
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

        $dlr_mask = $request->getParam('dlr_mask');

        if(!$dlr_mask) {
            $dlr_mask = $settings['dlr_mask'];
        }

        $dlr = $request->getParam('dlr_url');

        if (!$dlr) {
            $dlr = $settings['dlr_url'];
        }

        $dlr_url = urlencode(urldecode($dlr));

        $username = $request->getParam('username');

        if(!$username) {
            $username = $settings['external_url']['username'];
        }

        $password = $request->getParam('password');

        if (!$password) {
            $password = $settings['external_url']['password'];
        }

        $host = $request->getParam('host');

        if (!$host) {
            $host = $settings['external_url']['host'];
        }

        $port = $request->getParam('port');

        if (!$port) {
            $port = $settings['external_url']['port'];
        }

        $smsc = $request->getParam('smsc');

        if (!$smsc) {
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'text/html')
                ->write('smsc missing');
        }

        if (in_array(strtolower($smsc), $settings['networks'])) {
            $tps = $settings['networks'][strtolower($smsc)];
        }
        else {
            $tps = $settings['tps'];
        }

        $channel = $GLOBALS['channel'];
        $service = $GLOBALS['service'];
        $connection = $GLOBALS['connection'];

        $time = $service->get_timestamp($tps);

        $message_params = array(
            'delivery_mode' => 2,
            'priority' => 1,
        );

        $instant = $request->getParam('instant');

        if (!$instant) {
            array_push($message_params, array('timestamp' => $time));
        }

        $queue = $request->getParam('queue');

        if (!$queue) {
            $queue = $settings['queue_name'];
        }
        else {

            $channel->exchange_declare($queue, 'headers', false, true, false);
            $channel->queue_declare($queue, false, true, false, false);
            $channel->queue_bind($queue, 'exchange');
        }

        $message = new AMQPMessage($text, $message_params);

        $domain = $settings['external_url']['domain'];
        $route = $settings['external_url']['route'];

        $url = 'http://'. $host. '.'.  $domain. ':'. $port. $route;

        $headers = new AMQPTable(array(
            "x-delay" => $settings['delay'],
            'url' => $url,
            'timestamp'=> $time,
            'smsc' => $smsc,
            'username' => $username,
            'password' => $password,
            'from' => $from,
            'to' => $recipient,
            'dlr_url' => $dlr_url,
            'dlr_mask' => $dlr_mask
        ));
        $message->set('application_headers', $headers);
        $channel->basic_publish($message, $settings['exchange_name'], $queue);

        $data = '[DATETIME:'. time() .'][STATUS: Queued][SMSC:'. $smsc .'][FROM:'.$from.'][TO:'.$recipient.'][MSG:'.$text.'][DLR_MASK:'.$dlr_mask.'][DLR:'.$dlr.']';
        $this->log->debug();

        $log = new Logger($settings['logger']['name']);
        $log->pushHandler(new Monolog\Handler\RotatingFileHandler($settings['logger']['path'], $settings['logger']['maxFiles'], Logger::INFO));
        $log->info($data);

        $channel->close();
        $connection->close();

        return $response->withStatus(202)
            ->write('Task added to queue');
    });

    $this->get('/dlr', function ($request, $response) {

        $senderID = $request->getParam('sc');

        if(!$senderID) {
            $senderID = null;
        }

        $message = $request->getParam('msg');

        if (!$message) {
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'text/html')
                ->write('msg missing');
        }

        $msisdn = $request->getParam('msisdn');

        if (!$msisdn) {
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'text/html')
                ->write('msisdn missing');
        }

        $serviceID = $request->getParam('serv_id');

        if (!$serviceID) {
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'text/html')
                ->write('serv_id missing');
        }

        $srcModule = $request->getParam('src_module');

        if (!$srcModule) {
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'text/html')
                ->write('src_module missing');
        }

        $dlr = $request->getParam('dlr');

        if (!$dlr) {
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'text/html')
                ->write('dlr missing');
        }

        $smsc = $request->getParam('smsc');

        if (!$smsc) {
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'text/html')
                ->write('smsc missing');
        }

        $requestTime = $request->getParam('request_time');

        if (!$requestTime) {
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'text/html')
                ->write('request_time missing');
        }

        $service = $GLOBALS['service'];
        $channel = $GLOBALS['channel'];
        $connection = $GLOBALS['connection'];

        $que = $request->getParam('queue');

        if (!$que) {
            $que = $service->get_dlr_queue();
        }
        else {
            $channel->queue_declare($que, false, true, false, false);
        }

        $messageID = $request->getParam('msg_id');
        $subscriptionType = $request->getParam('sub_type');
        $serviceName = $request->getParam('serv_name');
        $dp_retry = $request->getParam('dp_retry');

        $__data = array('msisdn' => $msisdn, 'serv_id'=> $serviceID, 'scrm' => $srcModule, 'dlr' => $dlr, 'sender_id' => $senderID, 'smsc' => $smsc, 'request_time'=>$requestTime);
        $_data = json_encode($__data);

//        $text = $serviceID. '*'. $msisdn. '*'. $senderID . '*SRCM'. $srcModule . '*'. $dlr . '*' . $smsc. '*'. $billingTime. '*';

        $msg = new AMQPMessage($_data);
        $channel->basic_publish($msg, '', $que);

//        $file = 'dlr.log';
        $data = '[DATETIME:'. $requestTime .'][STATUS: Accepted][SMSC:'. $smsc .'][FROM:'.$senderID.'][TO:'.$msisdn.'][MSG:'.$message.'][SRCM:'.$srcModule.'][SERVICE_ID:'.$serviceID.'][DLR:'.$dlr.']';
        $this->log->debug($data);
//        file_put_contents($file, $data.PHP_EOL, FILE_APPEND);

        $channel->close();
        $connection->close();

        return $response->withStatus(202)
            ->write('Task added to queue');

    });

    $this->get('/dlr/change', function ($request, $response) {

        $queue = $request->getParam('queue');

        if(!$queue) {
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'text/html')
                ->write('queue missing');
        }

        $service = $GLOBALS['service'];
        $service->set_dlr_queue($queue);

        return $response->withStatus(200)
            ->withHeader('Content-Type', 'application/json')
            ->write($queue);
    });

    $this->get('/queue/size', function ($request, $response) {

        $queue = $request->getParam('queue');

        if(!$queue) {
            return $response->withStatus(404)
                ->withHeader('Content-Type', 'text/html')
                ->write('queue missing');
        }

        $channel = $GLOBALS['channel'];
        $connection = $GLOBALS['connection'];
        $declaration = $channel->queue_declare($queue, false, true, false, false);

        $channel->close();
        $connection->close();

        return $response->withStatus(200)
            ->withHeader('Content-Type', 'application/json')
            ->write($declaration[1]);
    });
});

$app->run();