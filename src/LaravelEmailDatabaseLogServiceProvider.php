<?php

namespace ShvetsGroup\LaravelEmailDatabaseLog;

use Illuminate\Support\ServiceProvider;

/** @psalm-suppress UnusedClass */
class LaravelEmailDatabaseLogServiceProvider extends ServiceProvider
{
    /**
     * Register any other events for your application.
     *
     * @return void
     */
    public function boot()
    {

    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(LaravelEmailDatabaseLogEventServiceProvider::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../migrations' => database_path('migrations'),
            ], 'laravel-email-database-log-migration');
        }
    }
}
