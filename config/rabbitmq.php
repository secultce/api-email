<?php

return [
    'enabled' => env('RABBITMQ_ENABLED', true),

    'host' => env('RABBITMQ_DEFAULT_HOST', 'localhost'),
    'port' => (int) env('RABBITMQ_DEFAULT_PORT', 5672),
    'user' => env('RABBITMQ_DEFAULT_USER', 'guest'),
    'pass' => env('RABBITMQ_DEFAULT_PASS', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),

    'exchange_default' => env('RABBITMQ_EXCHANGE_DEFAULT', 'exchange_notification'),

    'queues' => [
        'queue_opinion_management' => env('QUEUE_OPINION_MANAGEMENT', 'queue_opinion_management'),
        'queue_import_registration' => env('QUEUE_IMPORT_REGISTRATION', 'queue_import_registration'),
        'queue_published_recourses' => env('QUEUE_PUBLISHED_RECOURSES', 'queue_published_recourses'),
        'queue_accountability' => env('QUEUE_ACCOUNTABILITY', 'queue_accountability'),
    ],

    'routing' => [
        'module_import_registration_draft' => env('MODULE_IMPORT_REGISTRATION_DRAFT', 'module_import_registration_draft'),
        'module_accountability_proponent' => env('MODULE_ACCOUNTABILITY_PROPONENT', 'module_accountability_proponent'),
        'module_accountability_adm' => env('MODULE_ACCOUNTABILITY_ADM', 'module_accountability_adm'),
        'plugin_published_recourses' => env('PLUGIN_PUBLISHED_RECOURSES', 'plugin_published_recourses'),
    ],

    'prefetch_count' => (int) env('RABBITMQ_PREFETCH_COUNT', 1),

    'retry' => [
        // Ativar somente depois de importar as definições no broker.
        'enabled' => env('RABBITMQ_IMPORT_REGISTRATION_RETRY_ENABLED', false),
        'exchange' => 'exchange_notification_retry',
        // O índice representa o número de retries JÁ realizados (não a tentativa total).
        // Os TTLs são fixados no definitions.json: 60s, 300s e 900s.
        'routes' => [
            1 => 'module_import_registration_draft_retry_1',
            2 => 'module_import_registration_draft_retry_2',
            3 => 'module_import_registration_draft_retry_3',
        ],
    ],
];
