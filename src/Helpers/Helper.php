<?php 
namespace FooWeChat\Helpers;

use Config;
//use Carbon\Carbon;

/**
* 杂项工具
*/
class Helper
{
	
	//获取公司配置信息
	public function custom($key)
	{
		$conf = Config::get('foowechat');
		return $conf['custom'][$key];
	}

	public function copyRight()
	{
		$conf = Config::get('foowechat');
		$year = $conf['custom']['year'];
		$name = $conf['custom']['name'];
		$thisYear = date('Y');

		if($year < $thisYear){
			return "&copy;".$year.' - '.$thisYear.'  '.$name;
		}else{
			return "&copy;".$thisYear.$name;
		}
	}

	public function errorCode($key)
	{
		$errorCodes = [
			'1' => "禁止访问: 权限不足",
			'1.1' => "禁止访问: 未授权访问途径",
			'3' => "禁止访问: 您的账号状态可能被锁定",
			'4' => "错误: 找不到到相关记录",
			'4.1' => "错误: 密码错误",
			'5' => "错误: 微信服务器错误",
		];

		return $errorCodes[$key];
	}
}