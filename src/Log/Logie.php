<?php 
namespace FooWeChat\Log;

use App\Log;
use FooWeChat\Authorize\Auth;
use FooWeChat\Helpers\Helper;
use Request;
use Session;

/**
* 日志
*/
class Logie
{
	protected $self;
	
	function __construct()
	{
		if (Session::has('id')) {
			$this->self = Session::get('id');
		}else{
			die('FooWeChat\Log\Logie: 需要登录');
		}
		
	}
	/**
	* 新日志
	*
	* @param $array, ['info', 'content'], info, notice, warning, alert
	*
	* @return null
	*/
	public function add($array)
	{
		$ip = Request::ip();
		$a = new Auth;
		$h = new Helper;
		$info = $h->ipToCity($ip);

		$member_id = $this->self;

		$city = $info['status'] === 0 ? $info['content']['address'] : '公司';

		$way = $a->usingWechat() ? '微信' : '网页';

		$point = $info['status'] === 0 ? $info['content']['point']['x'].'|'.$info['content']['point']['y'] : '公司';

		$record = [];
		$record = array_add($record, 'member_id', $member_id);
		$record = array_add($record, 'name', Session::get('name'));
		$record = array_add($record, 'type', $array[0]);
		$record = array_add($record, 'content', $array[1]);
		$record = array_add($record, 'way', $way);
		$record = array_add($record, 'city', $city);
		$record = array_add($record, 'point', $point);
		$record = array_add($record, 'ip', $ip);

		Log::create($record);

	}

	/*
	* other functions
	*
	*/
}