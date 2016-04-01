<?php

namespace FooWeChat\Facades;

use Illuminate\Support\Facades\Facade;

class WeChatAPI extends Facade{
    protected static function getFacadeAccessor () 
    { 
    	return 'WeChatAPI'; 
    }
}