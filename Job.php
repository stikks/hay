<?php
/**
 * Created by PhpStorm.
 * User: stikks-workstation
 * Date: 2/24/17
 * Time: 1:29 PM
 */
date_default_timezone_set('Africa/Lagos');
require __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Message\AMQPMessage;
require __DIR__.'/Service.php';
use Service\Service;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use PhpAmqpLib\Wire\AMQPTable;

class Job
{
    public function perform()
    {
//        try {
        $service = new Service();
        $settings = $service->settings;
        $connection = $service->init_rabbitmq($settings['amqp']['host'], $settings['amqp']['port'], $settings['amqp']['username'], $settings['amqp']['password']);
        $channel = $connection->channel();
        $channel->queue_declare($settings['queue_name'], false, true, false, false);
        $channel->exchange_declare($settings['exchange_name'], 'topic', false, false, false);
        $channel->queue_bind($settings['queue_name'], $settings['exchange_name']);

        $recipients = $this->args['recipients'];

//        foreach ($recipients as $rex) {

        $head = $this->args['headers'];
        $head['recipients'] = $recipients;
        $headers = new AMQPTable($head);
        $time = $service->get_timestamp($settings['tps']);
        $message_params = $this->args['message_params'];
        array_push($message_params, array('timestamp' => $time));
        $message = new AMQPMessage($this->args['text'], $message_params);
//            $headers = new AMQPTable(array(
//                'x-foo'=>'bar',
//                'table'=>array('figuf', 'ghf'=>5, 5=>675),
//                'num1' => -4294967295,
//                'num2' => 5,
//                'num3' => -2147483648,
//                'true' => true,
//                'false' => false,
//                'void' => null,
//                'date' => new DateTime(),
//                'array' => array(null, 'foo', 'bar', 5, 5674625, 'ttt', array(5, 8, 2)),
//                'arr_with_tbl' => array(
//                    'bar',
//                    5,
//                    array('foo', 57, 'ee', array('foo'=>'bar', 'baz'=>'boo', 'arr'=>array(1,2,3, true, new DateTime()))),
//                    67,
//                    array(
//                        'foo'=>'bar',
//                        5=>7,
//                        8=>'boo',
//                        'baz'=>3
//                    )
//                ),
//                '64bitint' => 9223372036854775807,
//                '64bit_uint' => '18446744073709600000',
//                '64bitint_neg' => -pow(2, 40)
//            ));
//            $headers->set('shortshort', -5, AMQPTable::T_INT_SHORTSHORT);
//            $headers->set('short', -1024, AMQPTable::T_INT_SHORT);
        echo PHP_EOL . PHP_EOL . 'SENDING MESSAGE WITH HEADERS' . PHP_EOL . PHP_EOL;
        var_dump($headers->getNativeData());
        echo PHP_EOL;

        $message->set('application_headers', $headers);
        $channel->basic_publish($message, $settings['exchange_name']);

        $data = '[DATETIME:'. time() .'][STATUS: Queued][SMSC:'. $head['smsc'] .'][FROM:'.$head['from'].'][MSG:'.$this->args['text'].'][DLR_MASK:'.$head['dlr_mask'].'][DLR:'.$head['dlr'].']';
        $log = new Logger($settings['logger']['name']);
        $log->pushHandler(new StreamHandler($settings['logger']['path'], Logger::INFO));
        $log->info($data);

//        }

        $channel->close();
        $connection->close();
//
//        } catch (\Exception $e) {
//            echo $e->getMessage();
//        }
    }
}