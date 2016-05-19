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
*              'department' => '市场部|生产部|self|self+',
*              'seek'       => '>:经理@市场部|>=:总监@生产部', //指定角色
*              'self'       => 'own|master|sub|master+|sub+', //own = 本人, master = 领导, sub = 下属, 带+号:所有领导或下属
*             ];
*
* @param null or session
*
* @return $array
*/
class Select
{
	protected $self;
	protected $selfId;
	
	function __construct()
	{
		$this->selfId = $myid = Session::get('id');
		$this->self = Member::find($myid);
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
				if($name === 'self'){
					$own = $this->getOwnDepartment($this->selfId);
					$id_list[] = $own;
				}elseif($name === 'self+'){
					$owns = $this->getOwnAndSubDepartments($this->selfId);
					$id_list = array_merge($id_list, $owns);
				}else{
					$this->checkDepartment($name);
					$dp = Department::where('name', $name)->first();
					$id_list[] = $dp->id;
				}
			}
			
			$id_list = array_unique($id_list);

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

				$subs = explode(':', $sets[0]);
				$operator = $subs[0];
				$p = Position::where('name', $subs[1])->first();
				$position = $p->id;
				$d = Department::where('name', $sets[1])->first();
				$department = $d->id;

				//职位计算
				$target_position_order = $p->order;

				$sample = ['>', '>=', '=', '<=', '<'];
				$real = ['<', '<=', '=', '>=', '>'];

				$operator_real = $real[array_search($operator, $sample)];

				$position_ids = Position::where('order', $operator_real, $target_position_order)->get();

				$position_id_list = [];
				foreach ($position_ids as $p) {
					$position_id_list[] = $p->id;
				}

				//下辖部门
				$target_department_code = Department::find($department)->code;
				$str = $target_department_code.'%';
				$master_department_ids = Department::where('code', 'LIKE', $str)->get();

				$department_id_list = [];
				foreach ($master_department_ids as $a) {
					$department_id_list[] = $a->id;
				}

				//查询符合条件的用户
				$members = Member::whereIn('department', $department_id_list)
				                 ->whereIn('position', $position_id_list)
				                 ->where('state', 0)
				                 ->where('show', 0)
				                 ->where('id', '>', 1)
				                 ->get();

				$work_id_list = [];
				foreach ($members as $m) {
					$work_id_list[] = $m->work_id;
				}

				//更新touser
				$new = $this->addTouser($arr, $work_id_list);
				$arr['touser'] = $new;


			}//end foreach

		}

		//self
		if(array_has($array, 'self')){
			$s = array_get($array, 'self');
			$values = explode("|", $s);

			$work_id_list = [];
			foreach ($values as $v) {
				$this->checkSelf($v);
				if($v === 'own'){
					$work_id_list[] = $this->self->work_id;
				}elseif($v === 'master'){
					$master_array = $this->getMaster($this->selfId);
					$work_id_list = array_merge($work_id_list, $master_array);

				}elseif($v === 'master+'){
					$masters_array = $this->getMasters($this->selfId);
					$work_id_list = array_merge($work_id_list, $masters_array);

				}elseif($v === 'sub'){
					$sub_array = $this->getSub($this->selfId);
					$work_id_list = array_merge($work_id_list, $sub_array);
				}elseif($v === 'sub+'){
					$subs_array = $this->getSubs($this->selfId);
					$work_id_list = array_merge($work_id_list, $subs_array);
				}
			}//end foreach

			//更新touser
			$new = $this->addTouser($arr, $work_id_list);
			$arr['touser'] = $new;
		}

		return $arr;
	}

	/**
	* 检查用户编号
	*
	*/
	public function checkUser($user)
	{
		$rec = Member::where('work_id', $user)->get();
		if(!count($rec)) die('FooWeChat\Selector\Select\checkUser: 有无效的编号');
		if(count($rec) > 1) die('FooWeChat\Selector\Select\checkUser: 数据库错误: 有重复编号');
	}

	/**
	* 检查部门名
	*
	*/
	public function checkDepartment($department)
	{
		$rec = Department::where('name', $department)->get();
		if(!count($rec)) die('FooWeChat\Selector\Select\checkDepartment: 有错误的部门名');
		if(count($rec) > 1) die('FooWeChat\Selector\Select\checkDepartment: 数据库错误: 有重复部门名');
	}

	/**
	* 检查seek
	*
	*/
	public function checkSeek($seek)
	{
		if(count($seek) != 2) die('FooWeChat\Selector\Select\checkSeek: 格式错误, 示例: >.经理@市场部'); 
		$rec = Department::where('name', $seek[1])->get();
		if(!count($rec)) die('FooWeChat\Selector\Select\checkSeek: 错误部门名');
		if(count($rec) > 1) die('FooWeChat\Selector\Select\checkSeek: 数据库错误:有重名的部门名'); 

		$subs = explode(':', $seek[0]);
		if(count($subs) != 2) die('FooWeChat\Selector\Select\checkSeek: 运算符错误或缺失, 示例: >.经理@市场部'); 
		$sample = ['>', '>=', '=', '<=', '<'];
		if(array_search($subs[0], $sample) === false) die('FooWeChat\Selector\Select\checkSeek: 运算符错误, 示例: >.经理@市场部');
		
		$rec_sub = Position::where('name', $subs[1])->get(); 
		if(!count($rec_sub)) die('FooWeChat\Selector\Select\checkSeek: 错误职位名称'); 
		if(count($rec_sub) > 1) die('FooWeChat\Selector\Select\checkSeek: 数据库错误:有重名的职位'); 
	}

	/**
	* 检查self
	*
	*/
	public function checkSelf($self)
	{
		$sample = ['own', 'master', 'sub', 'master+', 'sub+'];
		if(array_search($self, $sample) === false) die('FooWeChat\Selector\Select\checkSelf: 错误参数设置');
	}

	/*
	* 添加符合要求的work_id到接收者
	*
	*/
	public function addTouser($array, $add_array)
	{
		if(array_has($array, 'touser')){
			$old = array_get($array, 'touser');
			$old_array = explode("|", $old);
			$new = array_merge($old_array, $add_array);
			$new = array_unique($new);
			return implode("|", $new);
		}else{
			return implode("|", $add_array);
		}
	}

	/**
	* 获取上级部门, 按部门从小到大排序
	*
	*/
	public function getMasterDepartments($id)
	{
		$target_code = Department::find($id)->code;

		$codes = explode('-', $target_code);
		$code_list = [];

		foreach ($codes as $c) {
			array_pop($codes);
			if(count($codes) > 0) $code_list[] = implode("-", $codes);
		}

		 $arr = [];
		 $department_ids = Department::whereIn('code', $code_list)->orderBy('code', 'DESC')->get();

		 foreach ($department_ids as $d) {
		 	$arr[] = $d->id;
		 }

		 return $arr;
	}

	/**
	* 获取上级职位
	*
	*/
	public function getMasterPositions($id)
	{
		$target_order = Position::find($id)->order;
		$arr = [];

		$recs = Position::where('order', '<', $target_order)->orderBy('order', 'DESC')->get();

		foreach ($recs as $rec) {
			$arr[] = $rec->id;
		}

		return $arr;
	}

	/**
	* 获取上级
	*
	*
	*/
	public function getMaster($id)
	{
		$target = Member::find($id);
		$self_department_id = $target->department;

		$department_ids = $this->getMasterDepartments($target->department);
		$position_ids = $this->getMasterPositions($target->position);

		array_unshift($department_ids, $self_department_id);

		$arr = [];
		foreach ($department_ids as $d) {
			foreach ($position_ids as $p) {
				$recs = Member::where('department', $d)
				              ->where('position', $p)
				              ->where('id', '>', 1)
				              ->where('state', 0)
				              ->where('show', 0)
				              ->get();
				if(count($recs)){
					foreach ($recs as $r) {
						$arr[] = $r->work_id;
					}
					return $arr;
				}
			}//end foreach
		}//end foreach
	}

	/*
	* 获取所有上级
	*
	*/
	public function getMasters($id)
	{
		$target = Member::find($id);
		$self_department_id = $target->department;

		$department_ids = $this->getMasterDepartments($target->department);
		$position_ids = $this->getMasterPositions($target->position);

		array_unshift($department_ids, $self_department_id);

		$recs = Member::whereIn('department', $department_ids)
		              ->whereIn('position', $position_ids)
		              ->where('id', '>', 1)
		              ->where('state', 0)
		              ->where('show', 0)
		              ->get();
		$arr = [];

		if(count($recs)){
			foreach ($recs as $rec) {
				$arr[] = $rec->work_id;
			}
			return $arr;
		}
	}

	/*
	* 获取下级部门
	*
	*/
	public function getSubDepartments($id)
	{
		$target_code = Department::find($id)->code;
		$taget_code_like = $target_code.'%';

		$recs = Department::where('code', 'LIKE', $taget_code_like)->orderBy('code')->get();

		$arr = [];
		if(count($recs)){
			foreach ($recs as $rec) {
				$arr[] = $rec->id;
			}
			return $arr;
		}

	}

	/**
	* 获取下级职位
	*
	*/
	public function getSubPositions($id)
	{
		$target_order = Position::find($id)->order;
		$arr = [];

		$recs = Position::where('order', '>', $target_order)->orderBy('order')->get();

		foreach ($recs as $rec) {
			$arr[] = $rec->id;
		}

		return $arr;
	}

	/**
	* 获取所有下级
	*
	* 1. 在子部门或者本部门
	* 2. 职位小于
	*/
	public function getSubs($id)
	{
		$target = Member::find($id);

		$department_ids = $this->getSubDepartments($target->department);
		$position_ids = $this->getSubPositions($target->position);
		
		$recs = Member::whereIn('department', $department_ids)
		              ->whereIn('position', $position_ids)
		              ->where('id', '>', 1)
		              ->where('state', 0)
		              ->where('show', 0)
		              ->get();

		$arr = [];             
		if(count($recs)){
			foreach ($recs as $rec) {
				$arr[] = $rec->work_id;
			}
			return $arr;
		}

	}

	/**
	* 获取下级
	*
	* 1. 在本部门, 职位仅次于的人(群)
	* 2. 在子部门, 职位最高的(不比较职位)
	*/
	public function getSub($id)
	{
		$target = Member::find($id);
		$department_ids = $this->getSubDepartments($target->department);
		$position_ids = $this->getSubPositions($target->position);

		$self_department = $target->department;

		$arr = [];

		foreach ($position_ids as $p) {
			$recs = Member::where('department', $self_department)
						  ->where('position', $p)
			              ->where('id', '>', 1)
			              ->where('state', 0)
			              ->where('show', 0)
			              ->get();
			if(count($recs)){
				foreach ($recs as $rec) {
					$arr[] = $rec->work_id;
				}
				break;
			}
		}//end foreach

		array_shift($department_ids);

		foreach ($department_ids as $d) {
			foreach ($position_ids as $p) {
				$recs = Member::where('department', $d)
						  ->where('position', $p)
			              ->where('id', '>', 1)
			              ->where('state', 0)
			              ->where('show', 0)
			              ->get();
			    if(count($recs)){
			    	foreach ($recs as $rec) {
			    		$arr[] = $rec->work_id;
			    	}
			    	break;
			    }
			}
		}// end foreach 

		return $arr;
	}

	/*
	* 获取所在部门
	*
	*/
	public function getOwnDepartment($id)
	{
		$target = Member::find($id);
		return $target->department;
	}

	/*
	* 获取所在及下属部门
	*
	*/
	public function getOwnAndSubDepartments($id)
	{
		$target = Member::find($id);
		$code = Department::find($target->department)->code;
		$code_like = $code.'%';

		$recs = Department::where('code', 'LIKE', $code_like)->get();

		$arr = [];
		if(count($recs)){
			foreach ($recs as $rec) {
				$arr[] = $rec->id;
			}
		}

		return $arr;
	}

	/**
	* other functions
	*
	*/
}













