<?php
return array(
    'default' => array(
        'host' => env('QUEUE_HOST', '127.0.0.1'),
        'port' => env('QUEUE_PORT', 5672),
        'api_port' => env('QUEUE_APIPORT', 15672),
        'username' => env('QUEUE_USERNAME', 'guest'),
        'password' => env('QUEUE_PASSWORD', 'guest'),
        'vhost' => env('QUEUE_VHOST', '/'),
    )
);
