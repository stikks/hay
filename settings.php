<?php
/**
 * Created by PhpStorm.
 * User: stikks
 * Date: 9/5/16
 * Time: 3:34 PM
 */
return [
    'queue_name' => 'default_queue', // set to false in production
    'exchange_name' => 'default_exchange', // Allow the web server to send the content-length header
    'log_path' => 'message_queue.log',
    'tps' => '10',

    // Monolog settings
    'logger' => [
        'name' => 'slim-app',
        'path' => __DIR__ . '/../logs/app.log',
    ],
];