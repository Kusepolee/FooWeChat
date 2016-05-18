<?php 
namespace FooWeChat\Selector;

use App\Department;
use App\Member;
use App\Position;
use Session;

/**
* 信息发送范围
* 1. $array = [
*              'user'       => '编号1|编号2', // all -所有
*              'department' => '市场部|生产部',
*              'seek'       => '>.经理@市场部|>=.总监@生产部', //指定角色
*              'self'       => 'own|master|sub|master+|sub+', //own = 本人, master = 领导, sub = 下属, 带+号:所有领导或下属
*             ];
*
* @param null or session
*
* @return $array
*/
class Select
{
	protected $selfDepartment;
	protected $selfPosition;
	
	function __construct()
	{
		$myid = Session::get('id');
		$me = Member::find($myid);

		$this->selfDepartment = $me->department;
		$this->selfPosition = $me->position;
	}

	/**
	* 接收人
	*
	*/
	public function select($array)
	{
		$arr = [];
		//user
		if(array_has($array, 'user')){
			$u = array_get($array, 'user');
			if($u === 'all'){
				return array_add($arr, 'touser', '@all');
			}else{
				$work_id_or_name_list = explode('|', $u);
				if(count(array_unique($work_id_or_name_list)) < count($work_id_or_name_list)) die('FooWeChat\Selector\Select: 有重复编号');

				foreach ($work_id_or_name_list as $p) {
					$this->checkUser($p);
				}
				$arr = array_add($arr, 'touser', $u);
			}
		}

		//部门
		if(array_has($array, 'department')){
			$d = array_get($array, 'department');
			$names = explode('|', $d);
			//print_r($names);
			
			if(count(array_unique($names)) < count($names)) die('FooWeChat\Selector\Select: 有重复的部门名');

			$id_list = [];
			foreach ($names as $name) {
				$this->checkDepartment($name);
				$dp = Department::where('name', $name)->first();
				$id_list[] = $dp->id;
			}
			$id_list_str = implode("|", $id_list);
			$arr = array_add($arr, 'toparty', $id_list_str);
		}

		//seek
		if(array_has($array, 'seek')){
			$s = array_get($array, 'seek');
			$seeks = explode('|', $s);

			foreach ($seeks as $seek) {
				$sets = explode('@', $seek);
				$this->checkSeek($sets);

				$subs = explode('.', $sets[0]);
				$operator = $subs[0];
				$p = Position::where('name', $subs[1])->first();
				$position = $p->id;
				$d = Department::where('name', $sets[1])->first();
				$department = $d->id;

				$target_position_order = $p->order;

				$position_ids = Position::where('order', $operator, $target_position_order)->get();

				foreach ($position_ids as $p) {
					echo $p->name;
					
				}



				//$recs = Member::where('department', $department)


			}//end foreach

		}

		return $arr;
	}

	/**
	* 检查用户有效性
	*
	*/
	public function checkUser($user)
	{
		$rec = Member::where('work_id', $user)->get();
		if(!count($rec)) die('FooWeChat\Selector\Select\checkUser: 有无效的编号');
		if(count($rec) > 1) die('FooWeChat\Selector\Select\checkUser: 数据库错误: 有重复编号');
	}

	/**
	* 检查部门名有效性
	*
	*/
	public function checkDepartment($department)
	{
		$rec = Department::where('name', $department)->get();
		if(!count($rec)) die('FooWeChat\Selector\Select\checkDepartment: 有错误的部门名');
		if(count($rec) > 1) die('FooWeChat\Selector\Select\checkDepartment: 数据库错误: 有重复部门名');
	}

	/**
	* 检查seek有效性
	*
	*/
	public function checkSeek($seek)
	{
		if(count($seek) != 2) die('FooWeChat\Selector\Select\checkSeek: 格式错误, 示例: >.经理@市场部'); 
		$rec = Department::where('name', $seek[1])->get();
		if(!count($rec)) die('FooWeChat\Selector\Select\checkSeek: 错误部门名');
		if(count($rec) > 1) die('FooWeChat\Selector\Select\checkSeek: 数据库错误:有重名的部门名'); 

		$subs = explode('.', $seek[0]);
		if(count($subs) != 2) die('FooWeChat\Selector\Select\checkSeek: 运算符错误或缺失, 示例: >.经理@市场部'); 
		$sample = ['>', '>=', '=', '<=', '<'];
		if(array_search($subs[0], $sample) === false) die('FooWeChat\Selector\Select\checkSeek: 运算符错误, 示例: >.经理@市场部');
		
		$rec_sub = Position::where('name', $subs[1])->get(); 
		if(!count($rec_sub)) die('FooWeChat\Selector\Select\checkSeek: 错误职位名称'); 
		if(count($rec_sub) > 1) die('FooWeChat\Selector\Select\checkSeek: 数据库错误:有重名的职位'); 
	}


	/**
	* other functions
	*
	*/
}