<?php

/**
 * Created by PhpStorm.
 * User: stikks-workstation
 * Date: 9/7/16
 * Time: 11:58 AM
 */

namespace Service;
use Predis\Client;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

class Service
{
    protected $redis;

    public function __construct()
    {
        $this->redis = new Client();
        $this->settings = require __DIR__.'/settings.php';
    }

    public function init_rabbitmq($host, $port, $username, $password) {
        $connection = new AMQPStreamConnection($host, $port, $username, $password);
        $this->connection = $connection;
        return $connection;
    }

    public function declare_queue($channel, $queue) {

//        $channel->exchange_declare($queue, 'headers', false, true, false, false, false, new AMQPTable(array(
//            "x-delayed-type" => "headers"
//        )));
//
//        $channel->queue_declare($queue, false, true, false, false, false, new AMQPTable(array(
//            "x-dead-letter-exchange" => $queue,
//            'x-dead-letter-routing-key' => $queue
//        )));

        $channel->exchange_declare($queue, 'fanout', false, true, false);

        $channel->queue_declare($queue, false, true, false, false, false);

        $channel->queue_bind($queue, $queue);

        return $channel;
    }

    public function get_timestamp($limit) {

        try {
            $timestamp = (int)$this->redis->get('timestamp');

            if ($timestamp < time()) {
                $this->redis->set('timestamp', time());
            }

            $count = (int)$this->redis->get('count');

            if ($count == $limit) {
                $count = 1;
                $this->redis->set('timestamp', time() + 1);
            } else {
                $count += 1;
            }

            $timestamp = $this->redis->get('timestamp');
            $this->redis->set('count', $count);

        }
        catch (\Exception $e) {
            $count = $this->redis->set('count', 1);
            $timestamp = $this->redis->set('timestamp', time());
        }

        return $timestamp;
    }

    public function get_dlr_queue() {
        
        try {
            $queue = $this->redis->get('dlr_queue');
        }
        catch (\Exception $e) {
            $queue = $this->redis->set('dlr_queue', 'persistent_sevas');
        }
        
        return $queue;
    }
    
    public function set_dlr_queue($queue) {
        $this->redis->set('dlr_queue', $queue);
        return true;
    }

}
