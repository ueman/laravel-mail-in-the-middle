<?php

namespace VanEyk\MITM\Providers;

use Illuminate\Support\ServiceProvider;
use VanEyk\MITM\Console\Commands\HouseKeepingCommand;
use VanEyk\MITM\Mail\Transport\MailInTheMiddleMailer;
use VanEyk\MITM\Storage\Filesystem;
use VanEyk\MITM\Support\Config;
use VanEyk\MITM\Storage\MailStorage;
use VanEyk\MITM\Storage\MailStorageManager;
use VanEyk\MITM\Support\Path;

class MailInTheMiddleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->bindClasses();
        $this->mergeConfigFrom(Path::config(Config::FILE_NAME), Config::KEY);
        $this->loadMigrationsFrom(Path::migration());
    }

    public function boot(): void
    {
        $this->addTransportDriver();

        if (Config::get('autoRegister')) {
            $this->loadRoutesFrom(Path::routes('api.php'));
            $this->loadRoutesFrom(Path::routes('web.php'));
            $this->loadViewsFrom(Path::view(), Config::KEY);
        }

        if (Config::get('storage_driver') === 'database') {
            $this->loadMigrationsFrom(Path::migration());
        }

        if ($this->app->runningInConsole()) {
            $this->setupPublishes();
            $this->commands([
                HouseKeepingCommand::class,
            ]);
        }
    }

    public function addTransportDriver()
    {
        // Just call this once, to boot the MailServiceProvider
        $mailManager = app()->make('mail.manager');

        $mailManager->extend(
            Config::KEY,
            function () {
                return new MailInTheMiddleMailer(app(MailStorage::class));
            }
        );
    }

    public function setupPublishes()
    {
        $this->publishes([
            Path::config() => config_path(Config::FILE_NAME),
        ], Config::KEY . '-config');

        $this->publishes([
            Path::migration() => database_path('/migrations'),
        ], Config::KEY . '-migrations');
    }

    public function bindClasses(): void
    {
        $this->app->singleton(MailStorageManager::class, function () {
            return new MailStorageManager($this->app);
        });

        $this->app->singleton(Filesystem::class, static function () {
            return new Filesystem(Config::get('disk'));
        });

        $this->app->bind(MailStorage::class, static function () {
            $driver = Config::get('storage_driver');
            $manager = resolve(MailStorageManager::class);

            return $manager->driver($driver);
        });
    }
}