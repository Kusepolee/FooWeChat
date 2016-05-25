<?php

namespace FooWeChat\Facades;

use Illuminate\Support\Facades\Facade;

class Logie extends Facade{
    protected static function getFacadeAccessor () 
    { 
    	return 'Logie'; 
    }
}