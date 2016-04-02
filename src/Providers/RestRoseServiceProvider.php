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
        

        //Core模块
        $this->publishes([
            __DIR__.'/../Core/Publishes/Config/wechat.php' => config_path('wechat.php'),
        ]);

        $this->publishes([
            __DIR__.'/../Core/Publishes/Migrations/' => database_path('migrations'),
        ]);

        $this->publishes([
            __DIR__.'/../Core/Publishes/Models/' => app_path(),
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