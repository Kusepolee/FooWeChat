<?php 
namespace FooWeChat\Helpers;

use App\Department;
use App\FormConfig;
use App\Member;
use App\Position;
use Config;
use DB;
use Session;
use GuzzleHttp\Client;
use Psr\Http\Message\StreamInterface;

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

	//错误信息
	public function errorCode($type, $code)
	{
		$errorTypes = [
						'1' =>'错误',
						'2' =>'禁止访问',
						'3' =>'权限不足',
						'4' =>'重要提示:删除',
						'5' =>'操作成功',
		             ];

		$errorCodes = [
<<<<<<< HEAD
						'1.1' => "未知错误",
						'1.2' => "找不到到相关记录",
						'1.3' => "密码错误",
						'1.4' => "微信服务器错误",
						'1.5' => "必须登录",
						'2.1' => "您的账户被锁定. 若需要使用本系统, 请联系系统管理员",
						'2.2' => "密码错误",
						'2.3' => "此页面必须使用微信访问, 禁止使用其他浏览器",
						'2.4' => "此页面需要使用常规浏览器访问, 禁止使用微信客户端",
						'2.5' => "您没有权限继续操作, 可能是您的账号被锁定或删除",
						'3.1' => "您没有权限进行此项操作, 或者使用不允许的方法访问.若需要继续使用或此次警示属于异常情况, 请联系系统管理员.",
						'4.1' => "您正在进行 [删除用户] 操作, 此操作将从系统中删除用户记录, 该用户将不能使用本系统, 包括通过微信. 删除是不可恢复的!!",
						'5.1' => "您的操作已成功!"
					  ];
		$arr = [];
		$arr[] = $errorTypes[$type];
		$arr[] = $errorCodes[$code];

		return $arr;
	}

	/**
	* 获取下拉列表数组
	*
	* @param string
	* @return array 
	*/
	public function getSelect($key)
	{
		$recs = FormConfig::where('list',$key)->get();


        if(count($recs)){

        	$arr =[];

        	foreach ($recs as $rec) {
        		$arr = array_add($arr, $rec->id, $rec->name);
        	}

        	return $arr;

        }else{
        	return  ['0' => 'null', ];
        }
     
	}

	/**
	* 所有职位
	*/
	public function getAllPositions()
	{
		$recs = Position::where('id', '>', 1)->orderBy('order','DESC')->get();
		if(count($recs)){
			$arr = [];
			foreach ($recs as $rec) {
				$arr = array_add($arr, $rec->id, $rec->name);
			}
			return $arr;
		}else{
			return view('40x',['color'=>'danger', 'type'=>'1', 'code'=>'1.2']);
		}
	}

	/**
	* 2. 所有部门:除了root
	*
	*/
	public function getAllDepartments()
	{
		$recs = Department::where('id', '>', 1)->get();

		if(count($recs)){
			$arr = [];
			foreach ($recs as $rec) {
				$arr = array_add($arr, $rec->id, $rec->name);
			}
			return $arr;
		}else{
			return view('40x',['color'=>'danger', 'type'=>'1', 'code'=>'1.2']);
		}
	}

	/**
	* 2. 所有公司部门
	*
	*/
	public function getInsideDepartments()
	{
		//公司内部记录:code
		$insideCode = '1-2-3%';

		$recs = Department::where('code','LIKE', $insideCode)->get();

		if(count($recs)){
			$arr = [];
			foreach ($recs as $rec) {
				$arr = array_add($arr, $rec->id, $rec->name);
			}
			return $arr;
		}else{
			return view('40x',['color'=>'danger', 'type'=>'1', 'code'=>'1.2']);
		}
	}

	/**
	* 分配工号
	*
	* 不含4, 以部门编号开头, 顺序增
	*
	* @param string
	* @return array 
	*/
	public function getWorkId()
	{
		$max = Member::max('work_id');
		if ($max=='') $max = 0;
		$max++;

		$pos = stripos($max,'4');
		if ($pos=='') {
			# code...
		}else{
			$length = strlen($max);

			for ($i=($pos+1); $i < $length; $i++) { 
				$max = substr_replace($max,'0',$i,($length-$pos));
			}

			$max = str_ireplace("4","5",$max);
		}
		
		$max = intval($max);

		return $max;
	}
=======
			'1' => "禁止访问: 权限不足",
			'1.1' => "禁止访问: 未授权访问途径",
			'3' => "禁止访问: 您的账号状态可能被锁定",
			'4' => "错误: 找不到到相关记录",
			'4.1' => "错误: 密码错误",
			'5' => "错误: 微信服务器错误",
		];
>>>>>>> origin/master

	/**
	* 检查表记录存在(删除前检查)
	*
	* 1.示例: $arr = ['table1'=>'list1|list2', 'table2'=>'list1|list2']
	*
	* @param $array, $val
	*
	* @return boolean
	*/
	public function exsitsIn($array, $val)
	{
		foreach ($array as $key => $value) {
			$keys =  explode('|', $value);
			foreach ($keys as $k) {
				$recs = DB::table($key)->where($k, $val)->get();
				if(count($recs)) return true;
			}	
		}

		return false;
	}

	/**
	* IP转城市及获取百度地图坐标
	*
	* @param IP
	*
	* @return array
	*/
	public function ipToCity($ip)
	{
		$baidu_IP_url = 'http://api.map.baidu.com/location/ip?ak=bsa3LH1GT1jhOep5N7Uz950xtTQWvp9I&ip='.$ip.'&coor=bd09ll';
		$client = new Client();
        $json = $client->get($baidu_IP_url)->getBody();
        
        return json_decode($json, true);
	}

	/**
	* other functions
	*
	*/
}
















