<?php

namespace FooWeChat\Providers;

use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class RestRoseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
         $this->publishes([
            __DIR__.'/../Publishes/Config/wechat.php' => config_path('wechat.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../Publishes/Migrations/' => database_path('migrations')
        ], 'migrations');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        App::bind('WeChatAPI', function()
        {
            return new \FooWeChat\Core\WeChatAPI;
        });
    }
}