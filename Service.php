<?php

/**
 * Created by PhpStorm.
 * User: stikks-workstation
 * Date: 9/7/16
 * Time: 11:58 AM
 */

namespace Service;

$GLOBALS['settings'] = require __DIR__.'/settings.php';

class Service
{
    protected $redis;

    public function __construct($redis)
    {
        $this->redis = $redis;
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

}