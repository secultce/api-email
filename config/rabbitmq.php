<?php
return [
    'host' => env('RABBITMQ_DEFAULT_HOST', 'localhost'),
    'port' => env('RABBITMQ_DEFAULT_PORT', 5672),
    'user' => env('RABBITMQ_DEFAULT_USER', 'guest'),
    'password' => env('RABBITMQ_DEFAULT_PASS', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'queue' => env('RABBITMQ_QUEUE', 'import_registration'),
    'exchange' => env('RABBITMQ_EXCHANGE', 'registration'),
    'routing_key' => env('RABBITMQ_ROUTING_KEY', 'import_registration'),
];
