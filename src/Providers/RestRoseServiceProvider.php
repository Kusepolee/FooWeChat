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
        
        //public: bootstrap文件


        //Core模块
        // $this->publishes([
        //     __DIR__.'/../Core/Publishes/Config/wechat.php' => config_path('wechat.php'),
        //     __DIR__.'/../Core/Publishes/Migrations/' => database_path('migrations'),
        //     __DIR__.'/../Core/Publishes/Models/' => app_path(),
        // ]);

        // //Departments
        // $this->publishes([
        //     __DIR__.'/../Departments/Publishes/Migrations/' => database_path('migrations'),
        //     __DIR__.'/../Departments/Publishes/Models/' => app_path(),
        // ]);

        
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

        App::bind('Logie', function()
        {
            return new \FooWeChat\Log\Logie;
        });
    }
}