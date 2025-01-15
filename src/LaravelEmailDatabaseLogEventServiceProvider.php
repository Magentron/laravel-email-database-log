<?php

namespace ShvetsGroup\LaravelEmailDatabaseLog;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Mail\Events\MessageSending;

class LaravelEmailDatabaseLogEventServiceProvider extends ServiceProvider
{
    /** @inheritdoc */
    protected $listen = [
        MessageSending::class => [
            EmailLogger::class,
        ],
    ];
}
