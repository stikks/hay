<?php
/**
 * Created by PhpStorm.
 * User: stikks
 * Date: 9/20/16
 * Time: 12:10 PM
 */
require_once __DIR__ . '/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');