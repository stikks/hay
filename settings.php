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
    'delay' => 5000,
    'tps' =>10,
    'external_url' => [
        'url' => 'http://sponge.atp-sevas.com:13031/cgi-bin/sendsms',
        'username' => 'sponge',
        'password'=> 'sponge'
    ],

    // Monolog settings
    'logger' => [
        'name' => 'slim-app',
        'path' => __DIR__ . '/../logs/app.log',
    ],
];