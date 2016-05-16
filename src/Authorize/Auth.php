<?php
namespace FooWeChat\Authorize;

use App\Department;
use App\Member;
use App\Position;
use FooWeChat\Helpers\Helper;
use Session;

/**
*  权限
*
*  1. sample: 
*	  $auth = [
*               'root'       => 'only',             // only - 仅root用户
*				'admin'      => 'no',               // no - 禁止管理员, yes or null - 允许管理员, only - 仅管理员可用
*				'way'        => 'web',              // web - 仅网页访问, wechat - 仅微信访问
*				'except'     => '2|3',              // 禁止访问人员列表: work_id1|work_id2|中文名1|中文名2|...; 若中文名重复则报错
*				'user'       => '12|17',            // 允许访问人员列表: work_id1|work_id2|中文名1|中文名2|...; 若中文名重复则报错
*				'position'   => '>员工|<总监',       // 运算符: [大于: >, 大于等于: >=, 等于: =, 小于等于: <=, 小于: <],多项以'|'分隔
*				'department' => '<公司内部|>=资源部', //运算符: [大于: >, 大于等于: >=, 等于: =, 小于等于: <=, 小于: <],多项以'|'分隔
*             ];
*
*     $test = new FooWeChat\Authorize\Auth;
*     if(!$test->auth($auth)){
*		return view('40x',['text'=>'权限不足']);
*		exit;
*     }
*
* 2. 不写规则,表示不限制; root用户无限制
*/
class Auth
{
	protected $self;
	protected $selfId;

	protected $rootWorkIdList = [30, 1]; // 工号: work_id

	protected $safeInsideDepartmentName = '总经理'; // 内部分支 

	/**
	* 构造
	*
	* 1. 赋值 $self, $selfId
	*/
	function __construct()
	{
		if(Session::has('id')){
			$this->selfId = $myId = Session::get('id');
			$this->self = Member::find($myId);
		}else{
			//return view('40x',['color'=>'danger', 'type'=>'1', 'code'=>'1.5']);
			//die('FooWeChat\Authorize\Auth\__construct : need login');
		}
	}

	/**
	* root用户
	*
	* @param int $work_id or null
	* @return boolean
	*/
	public function isRoot($work_id=-1)
	{

		if($work_id != -1){
			return array_search($work_id, $this->rootWorkIdList) === false ? false : true;
		}else{
			return array_search($this->self->work_id, $this->rootWorkIdList) === false ? false : true;
		}
		
	}

	/**
	* 管理员 
	*
	* @param int $id or null
	* @return boolean
	*
	*/
	public function isAdmin($id=0)
	{
		if($id != 0){
			$admin = Member::find($id)->admin;
			return $admin === 0 ? true : false;
		}else{
			return $this->self->admin === 0 ? true : false;
		}
		
	}

	/**
	* 本人
	*
	* @param int $id
	*
	* @return boolean
	*/
	public function isSelf($id)
	{
		return $this->selfId === $id ? true : false;
	}

	/**
	* 锁定
	*
	* @param int $id
	*
	* @return boolean
	*/
	public function isLocked($id=-1)
	{
		if($id === -1){
			return $self->state === 0 ? true : false;
		}else{
			$rec = Member::find($id)->state;
			if(count($rec)){
				return Member::find($id)->state === 0 ? true : false;
			}else{
				die('FooWeChat\Authorize\Auth\isLocked: 无效id');
			}
			
		}
	}

	/**
	* 使用微信访问
	*
	*/
	public function usingWechat()
	{
		$user_agent = $_SERVER['HTTP_USER_AGENT'];
		return strpos($user_agent, 'MicroMessenger') ? true :false;
	}

	/**
	* 检查user正确性
	*
	* @param string
	*
	* @return null or die
	*/
	public function checkUser($string)
	{
		$work_id_or_name = explode('|', $string);

		$id_list = [];

		foreach ($work_id_or_name as $p){
			$ids = Member::where('work_id', $p)
			               ->orWhere('name', $p)
			               ->select('id', 'name')
			               ->get();
			if(count($ids)){
				foreach ($ids as $d){
					$i = $d->id;
					$a = array_push($id_list, $i);
				}
			}else{
				die('FooWeChat\Authorize\Auth\checkUser: 有不存在的工号或姓名');
			}
		}

		if(count($id_list) > count($work_id_or_name)) die('FooWeChat\Authorize\Auth\checkUser: 有重名');
		if(count(array_unique($id_list)) != count($id_list)) die('FooWeChat\Authorize\Auth\checkUser: 有用户重复出现');
	}

	/**
	* 检验auth数组正确性
	*
	* @param array 
	*
	* @return null or die
	*/
	public function checkAuthArray($array)
	{

		//root
		if(array_has($array, 'root')){
			if(array_get($array, 'root') != 'only') {
				die('FooWeChat\Authorize\Auth\checkAuthArray: 参数 root 值错误');
			}
		}

		//admin
		if(array_has($array, 'admin')){
			$a = array_get($array, 'admin');
			if($a != 'yes' && $a != 'no' && $a != 'only') {
				die('FooWeChat\Authorize\Auth\checkAuthArray: 参数 admin 值错误');
			}
		}

		//way
		if(array_has($array, 'way')){
			$a = array_get($array, 'way');
			if($a != 'wechat' && $a != 'web' && $a != 'all') {
				die('FooWeChat\Authorize\Auth\checkAuthArray: 参数 way 值错误');
			}
		}

		//except
		if(array_has($array, 'except')){
			$this->checkUser(array_get($array, 'except'));
		}

		//user
		if(array_has($array, 'user')){
			$this->checkUser(array_get($array, 'user'));
		}

	}

	/**
	* 外部人员限制
	*
	*/
	public function isInside(){
		$safe_inside = Department::where('name', $this->safeInsideDepartmentName)->first();
		if(!count($safe_inside)) die('FooWeChat\Authorize\Auth\safe_inside: 内部单位名设置错误');

		$safe_inside_code = $safe_inside->code;
		$danger_department = Member::find($this->selfId)->department;
		$danger_department_code_of_me = Department::find($danger_department)->code;

		return str_contains($danger_department_code_of_me, $safe_inside_code) ? true : false;
	}


	/**
	* 授权
	*
	* @param $array
	*
	* @return boolean
	*/
	public function auth($array)
	{
		$this->checkAuthArray($array);

		//root
		if($this->isRoot()){
			return true;
			
		}else{
			if(array_has($array, 'root') || array_get($array, 'root') === 'only'){
				return false;

			}
		}


		//admin
		if(!$this->isRoot() && $this->isAdmin()){
			if(!array_has($array, 'admin') || array_get($array, 'admin') === 'yes' || array_get($array, 'admin') === 'only'){
				return true;
				
			}
		}

		//如果为外部: 客户, 供应商, 政务部门
		if(!$this->isInside()) return false;


		//常规
		if(!$this->isRoot() && !$this->isAdmin()){
			if(array_has($array, 'admin') && array_get($array, 'admin') === 'only'){
				return false;
				
			}
		}

		// 访问方法
		if(array_has($array, 'way')){
			$way = array_get($array, 'way');

			if($way === 'wechat'){
				if(!$this->usingWechat()){
					return false;
					
				}
			}elseif($way === 'web'){
				if($this->usingWechat()){
					return false;
					
				}
			}
		}

		//在禁止名单中
		if(array_has($array, 'except')){
			$str = array_get($array, 'except');
			$work_id_or_name_list = explode('|', $str);

			if(array_search($this->self->work_id, $work_id_or_name_list) === false && array_search($this->self->name, $work_id_or_name_list) === false){
				//go on..
			}else{

				return false;
				
			}
		}

		//在允许名单中
		if(array_has($array, 'user')){
			$str = array_get($array, 'user');
			$work_id_or_name_list = explode('|', $str);
			if(array_search($this->self->work_id, $work_id_or_name_list) === false && array_search($this->self->name, $work_id_or_name_list) === false){
				//go on
			}else{
				return true;
				
			}
		}

		//职位 order
		if(array_has($array, 'position')){
			$str = array_get($array, 'position');
			$positions = explode('|', $str);
			$me = $this->self->position;
			$my_position_order = Position::find($me)->order;
			if(!count($my_position_order)) die('FooWeChat\Authorize\Auth: need login');

			foreach ($positions as $p){

				if(str_contains($p, '>') && !str_contains($p, '=')) {
					$name = mb_substr($p, 1, mb_strlen($p),'utf-8');
					$rec = Position::where('name', $name)->first();
					if(!count($rec)) die('FooWeChat\Authorize\Auth.position1: 职位名错误');
					if($my_position_order >= $rec->order){
						return false;
						
					}else{
						//return true;
						//go on ..
					}

				}elseif (str_contains($p, '>=')){
					$name = mb_substr($p, 2, mb_strlen($p),'utf-8');
					$rec = Position::where('name', $name)->first();
					if(!count($rec)) die('FooWeChat\Authorize\Auth.position2: 职位名错误');
					if($my_position_order > $rec->order){
						return false;
						
					}else{
						//return true;
						//go on..
					}

				}elseif (str_contains($p, '=') && !str_contains($p, '>=') && !str_contains($p, '<=')){

					$name = mb_substr($p, 1, mb_strlen($p),'utf-8');
					$rec = Position::where('name', $name)->first();
					if(!count($rec)) die('FooWeChat\Authorize\Auth.position3: 职位名错误');
					if($my_position_order <> $rec->order){
						return false;
						
					}else{
						//return true;
						//go on..
					}

				}elseif (str_contains($p, '<=')) {

					$name = mb_substr($p, 2, mb_strlen($p),'utf-8');
					$rec = Position::where('name', $name)->first();
					if(!count($rec)) die('FooWeChat\Authorize\Auth.position4: 职位名错误');
					if($my_position_order < $rec->order){
						return false;
						
					}else{
						//return true;
						//go on..
					}

				}elseif (str_contains($p, '<') && !str_contains($p, '=')) {

					$name = mb_substr($p, 1, mb_strlen($p),'utf-8');
					$rec = Position::where('name', $name)->first();
					if(!count($rec)) die('FooWeChat\Authorize\Auth.position5: 职位名错误');
					if($my_position_order <= $rec->order){
						return false;
						
					}else{
						//return true;
						//go on..
					}
				}
				
			}

		}

		//department
		if(array_has($array, 'department')){

			$str = array_get($array, 'department');
			$departments = explode('|', $str);

			$me = $this->self->department;
			//echo $me;

			$my_department_code = Department::find($me)->code;
			//echo $my_department_code;
			if(!count($my_department_code)) die('FooWeChat\Authorize\Auth: need login');
			foreach ($departments as $p){
				if(str_contains($p, '>') && !str_contains($p, '=')) {

					$name = mb_substr($p, 1, mb_strlen($p),'utf-8');
					//echo $name;
					$rec = Department::where('name', $name)->first();
					if(!count($rec)) die('FooWeChat\Authorize\Auth.department1: 部门名错误');
					$needDepartment = $rec->code;
					//echo $my_department_code.'*'.$needDepartment;
					if(str_contains($my_department_code, $needDepartment) || 
						($my_department_code != $needDepartment && 
						!str_contains($needDepartment, $my_department_code))
						){
						return false;
						
					}else{
						//return true;
						//go on..
					}
					
				}elseif(str_contains($p, '>=')) {
					$name = mb_substr($p, 2, mb_strlen($p),'utf-8');
					//echo $name;
					$rec = Department::where('name', $name)->first();
					if(!count($rec)) die('FooWeChat\Authorize\Auth.department2: 部门名错误');
					//echo $my_department_code.'/'.$rec->code;
					$needDepartment = $rec->code;
					if(($my_department_code != $needDepartment && 
						!str_contains($needDepartment, $my_department_code))
						){
						return false;
						
					}else{
						//return true;
						//go on..
					}
				}elseif (str_contains($p, '=') && !str_contains($p, '>=') && !str_contains($p, '<=')){
					$name = mb_substr($p, 1, mb_strlen($p),'utf-8');
					//echo $name;
					$rec = Department::where('name', $name)->first();
					if(!count($rec)) die('FooWeChat\Authorize\Auth.department3: 部门名错误');
					//echo $my_department_code.'/'.$rec->code;
					$needDepartment = $rec->code;
					if($my_department_code != $needDepartment){
						return false;
						
					}else{
						//return true;
						//go on..
					}
				}elseif (str_contains($p, '<=')){
					$name = mb_substr($p, 2, mb_strlen($p),'utf-8');
					//echo $name;
					$rec = Department::where('name', $name)->first();
					if(!count($rec)) die('FooWeChat\Authorize\Auth.department4: 部门名错误');
					//echo $my_department_code.'/'.$rec->code;
					$needDepartment = $rec->code;
					if(($my_department_code != $needDepartment && 
						!str_contains($my_department_code, $needDepartment))
						){
						return false;
						
					}else{
						//return true;
						//go on..
					}
				}elseif (str_contains($p, '<') && !str_contains($p, '=')) {
					$name = mb_substr($p, 1, mb_strlen($p),'utf-8');
					//echo $name;
					$rec = Department::where('name', $name)->first();
					if(!count($rec)) die('FooWeChat\Authorize\Auth.department5: 部门名错误');
					
					$needDepartment = $rec->code;
					//echo $my_department_code.'/'.$rec->code;
					if($my_department_code === $needDepartment || ($my_department_code != $needDepartment && 
						!str_contains($my_department_code, $needDepartment))
						){
						return false;
						
					}else{
						//return true;
						//go on..
					}
				}
			}

		}
		return true;

	}
	
	/**
	* 管理权
	*
	* 1. root用户
	*
	* 2. 是系统管理员
	*    a. 目标是root用户 -> 无权
	*    b. 本人不是root用户, 而目标也是管理员 -> 无权
	*    c. 
	*
	* 3. 非管理员
	*    a. 本人部门大于等于目标部门 + 本人职位大于目标职位 -> 有权
	*
	* @param integer $id, 0 or 1 sigal $self
	* @return boolean 
	*/
	public function hasRights($id, $self = 1)
	{
		//允许本人
		if($self === 0){
			if($this->selfId == $id) {
				return true;
				
			}
		}

		$target = Member::find($id);

		if($this->isRoot()){
			return true;
		}elseif(!$this->isRoot() && $this->isAdmin()){
			if($this->isRoot($target->work_id)){
				return false;
			}elseif($target->admin === 0){
				return false;
			}else{
				return true;
			}
			
		}else{

			if($this->isRoot($target->work_id)){
				return false;
			}elseif($target->admin === 0){
				return false;
			}

			$departmentList = $this->getDepartments();
			$positionList = $this->getPositions();

			$targetDepartment = $target->department;
			$targetPosition = $target->position;

			if (array_key_exists($targetDepartment,$departmentList) && array_key_exists($targetPosition, $positionList)){
			  	return true;
			}else{
			  	return false;
			}
		}

	}

	/**
	* 获取管辖的部门
	*
	* 1. 所在部门
	*
	* 2. 所有上级部门
	*/
	public function getDepartments()
	{

		$me = Member::find($this->selfId)->department;

		$myDepartmentCode = Department::find($me)->code;

		$likeCode = $myDepartmentCode.'%';

		$recs = Department::where('code','LIKE', $likeCode)->get();

		if(count($recs)){
			$arr = [];
			foreach ($recs as $rec) {
				$arr = array_add($arr, $rec->id, $rec->name);
			}
			return $arr;
		}else{
			die('FooWeChat\Authorize\Auth\getDepartments: 记录异常');
			//return view('40x',['color'=>'danger', 'type'=>'1', 'code'=>'1.2']);
		}

	}

	/**
	* 获取管辖职位
	* 
	* 1. 低于自己的职位
	*/
	public function getPositions()
	{
		$me = Member::find($this->selfId)->position;

		$code = Position::find($me)->order;

		$recs = Position::where('order','>', $code)->orderBy('order','DESC')->get();

		$arr = [];
		if(count($recs)){
			foreach ($recs as $rec) {
				$arr = array_add($arr, $rec->id, $rec->name);
			}
		}else{
			//最低职位的人员, 无管辖职位,返回空数组
			//die('FooWeChat\Authorize\Auth\getDepartments: 记录异常');
			//return view('40x',['color'=>'danger', 'type'=>'1', 'code'=>'1.2']);
		}
		return $arr;
	}

	/**
	* 在允许列表中加入本人: 允许本人操作
	*
	* @param $array -origen, $key -array key, $id -page use id
	*
	* @return $array new 
	*/
	public function addSelf($array, $key, $id)
	{
		if($key != 'user' && $key != 'except') die('FooWeChat\Authorize\Auth\addSelf: 错误键名');

		if($this->selfId == $id){
			$self_work_id = Member::find($this->selfId)->work_id;

			if(array_has($array, $key)){
				$array[$key] = $array[$key].'|'.$self_work_id;
			}else{
				$array = array_add($array, $key, strval($self_work_id));
			}
		}
		return $array;
	}



	/**
	* other functions
	*
	*/
}























