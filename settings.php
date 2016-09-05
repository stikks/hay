<?php
/**
 * Created by PhpStorm.
 * User: stikks
 * Date: 9/5/16
 * Time: 3:34 PM
 */
return [
    'queue_name' => 'queue', // set to false in production
    'exchange_name' => 'exchange', // Allow the web server to send the content-length header
    'log_path' => '/var/log/message_queue.log',
    'channels' => '10',

    // Monolog settings
    'logger' => [
        'name' => 'slim-app',
        'path' => __DIR__ . '/../logs/app.log',
    ],
];