<?php

namespace ShvetsGroup\LaravelEmailDatabaseLog;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;

class LaravelEmailDatabaseLogEventServiceProvider extends ServiceProvider
{
    /** @inheritdoc */
    protected $listen = [
        MessageSending::class => [
            EmailLogger::class,
        ],
        MessageSent::class => [
            EmailLogger::class,
        ],
    ];
}
