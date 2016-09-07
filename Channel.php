<?php

/**
 * Created by PhpStorm.
 * User: stikks-workstation
 * Date: 9/7/16
 * Time: 10:07 AM
 */
require __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;

class ExtendedChannel extends AMQPChannel
{
    public function batch_basic_publish(
        $msg,
        $exchange = '',
        $routing_key = '',
        $mandatory = false,
        $immediate = false,
        $ticket = null
    ) {
        array_push($this->batch_messages[], func_get_args());
    }
}

class ExtendedConnection extends AbstractConnection {

    public function channel($channel_id = null)
    {
        if (isset($this->channels[$channel_id])) {
            return $this->channels[$channel_id];
        }

        $channel_id = $channel_id ? $channel_id : $this->get_free_channel_id();
        $ch = new ExtendedChannel($this->connection, $channel_id);
        $this->channels[$channel_id] = $ch;

        return $ch;
    }
}
