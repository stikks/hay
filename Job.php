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

class Job
{
    public function perform()
    {
        try {
            $service = new Service();
            $settings = $service->settings;
            $connection = $service->init_rabbitmq($settings['amqp']['host'], $settings['amqp']['port'], $settings['amqp']['username'], $settings['amqp']['password']);
            $channel = $connection->channel();
            $channel = $service->declare_queue($channel, $settings['queue_name']);

            $recipients = $this->args['recipients'];

            foreach ($recipients as $rex) {
                $headers = $this->args['headers'];
                $headers['recipient'] = $rex;
                $headers['text'] = $this->args['text'];

                $time = $service->get_timestamp($settings['tps']);
                $message_params = $this->args['message_params'];
                array_push($message_params, array('timestamp' => $time));
                $headers['timestamp'] = $time;

                $message = new AMQPMessage($headers);
//                $message->set('application_headers', $headers);
                $channel->basic_publish($message, $settings['exchange_name'], $settings['queue_name']);

                $data = '[DATETIME:'. time() .'][STATUS: Queued][SMSC:'. $headers['smsc'] .'][FROM:'.$headers['from'].'][TO:'.$rex.'][MSG:'.$headers['text'].'][DLR_MASK:'.$headers['dlr_mask'].'][DLR:'.$headers['dlr_url'].']';
                $log = new Logger($settings['logger']['name']);
                $log->pushHandler(new StreamHandler($settings['logger']['path'], Logger::INFO));
                $log->info($data);

            }

            $channel->close();
            $connection->close();

        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }
}