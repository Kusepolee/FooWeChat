# FooWeChat
FooWeChat: WeChat SDK for laravel5+

log:

FooWeChat\Providers\RestRoseServiceProvider::class,

'WeChatAPI' => FooWeChat\Facades\WeChatAPI::class, 

注册登录中间件:
'wechat_or_login' => App\Http\Middleware\WeChatOrLogin::class,

设置中文错误信息提示:
1.resources/lang 下新建 zh
2.复制 resources/lang/en 下 validation.php
3.翻译

安装表单组件: 5.0
1. composer require illuminate/html
2. 注册facades,config/app.php
	Illuminate\Html\HtmlServiceProvider::class,
	'Form'      => Illuminate\Html\FormFacade::class, 

need:simple-qrcdoe

FUCK the git NND cao shit

