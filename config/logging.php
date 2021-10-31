<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'newrelic' => [
            'driver' => 'custom',
            'via' => App\Lagoon\NewRelicLogger::class,
	        'NEWRELIC_LICENSE' => env('NEWRELIC_LICENSE',''),
            'service' => env('SERVICE_NAME', env('LAGOON', 'unconfigured')),
            'env' => [
	        'APP_NAME' => env('APP_NAME', 'unconfigured'),
	        'APP_ENV' => env('APP_ENV', 'unconfigured'),
	        'APP_DEBUG' => env('APP_DEBUG', 'unconfigured'),
	        'LAGOON' => env('LAGOON', 'unconfigured'),
	        'LAGOON_PROJECT' => env('LAGOON_PROJECT', 'unconfigured'),
	        'LAGOON_KUBERNETES' => env('LAGOON_KUBERNETES', 'local'),
	        'LAGOON_ENVIRONMENT' => env('LAGOON_ENVIRONMENT', 'local'),
	        'LAGOON_ENVIRONMENT_TYPE' => env('LAGOON_ENVIRONMENT_TYPE', 'local'),
	        'LAGOON_VERSION' => env('LAGOON_VERSION', ''),
             ]
        ],
        'stack' => [
            'driver' => 'stack',
            'channels' => ['stderr'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
        ],
        'wobot-applog' => [
            'driver' => 'single',
            'path' => '/app/storage/logs/wobot.log',
            'level' => 'debug',
        ],
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'days' => 14,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => 'critical',
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => 'debug',
            'handler' => SyslogUdpHandler::class,
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
            ],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug',
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug',
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],
    ],

];
