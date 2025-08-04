<?php
return [
    'host' => env('RABBITMQ_DEFAULT_HOST', 'localhost'),
    'port' => env('RABBITMQ_DEFAULT_PORT', 5672),
    'user' => env('RABBITMQ_DEFAULT_USER', 'guest'),
    'password' => env('RABBITMQ_DEFAULT_PASS', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'exchange_default' => env('RABBITMQ_EXCHANGE_DEFAULT', 'exchange_notification'),
    'queues' => [
        'queue_opinion_management' => env('QUEUE_OPINION_MANAGEMENT', 'queue_opinion_management'),
        'queue_import_registration' => env('QUEUE_IMPORT_REGISTRATION', 'queue_import_registration'),
        'queue_published_recourses' => env('QUEUE_IMPORT_REGISTRATION', 'queue_import_registration'),
    ],
    'routing' => [
            'module_import_registration_draft' => env('MODULE_IMPORT_REGISTRATION_DRAFT', 'module_import_registration_draft')
    ],
    'routing_key' => env('RABBITMQ_ROUTING_KEY', 'import_registration'),
];
