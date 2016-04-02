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
        ]);

        $this->publishes([
            __DIR__.'/../Publishes/Migrations/2016_4_2_000000_create_server_vals_table.php' => database_path('migrations'),
        ]);

        $this->publishes([
            __DIR__.'/../Publishes/Models/ServerVal.php' => app_path(),
        ]);
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