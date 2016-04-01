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
        //
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