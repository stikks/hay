<?php
/**
 * Created by PhpStorm.
 * User: stikks-workstation
 * Date: 2/24/17
 * Time: 1:29 PM
 */
require __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Message\AMQPMessage;
require __DIR__.'/Service.php';
use Service\Service;

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

                $time = $service->get_timestamp($settings['tps']);
                $message_params = $this->args['message_params'];
                array_push($message_params, array('timestamp' => $time));

                $message = new AMQPMessage($this->args['text'], $message_params);
                $message->set('application_headers', $headers);
                $channel->basic_publish($message, $settings['exchange_name'], $settings['queue_name']);
            }

            $channel->close();
            $connection->close();

        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }
}