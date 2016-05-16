<?php

namespace FooWeChat\Core;

use App\Department;
use App\Member;
use App\ServerVal;
use Config;
use Cookie;
use Input;
use Request;
use Session;
use GuzzleHttp\Client;
use Psr\Http\Message\StreamInterface;

/**
* 微信核心API
*/
class WeChatAPI
{

    protected $corpID;
    protected $corpSecret;
    protected $corpTalken;
    protected $agentID;

    protected $client;
    protected $oAuth2UserInfoArray;
    
    public $code;
    

    /**
     * 构造函数
     *
     * @param void 
     *
     * @return mix
     */
    public function __construct()
    {
        $this->corpID     = $this->conf('corpID');
        $this->corpSecret = $this->conf('corpSecret');
        $this->corpTalken = $this->conf('token');
        $this->agentID    = $this->conf('agentID');
    }
    /**
     * 获取配置文件信息
     *
     * @param string 
     *
     * @return array
     */
	private function conf($key)
	{
		$conf = Config::get('foowechat');
		return $conf[$conf['mode']][$key];
	}


   /**
     * 查询服务器变量
     *
     * @param string 
     *
     * @return string
     */
    private function searchServeVal($key)
    {
        $ex = ServerVal::where('var_name', $key)->first();
        if(count($ex)){
            $expire = $ex->expire;
            $rec = ServerVal::where('var_name', $key)
                            ->where('var_up_time', '>', (time() - $expire))
                            ->first();
            if(count($rec)){
                return $rec->var_value;
            }else{
                return $rec;
            }
            
        }else{
            return $ex;
        }

    }

   /**
     * 获取Talken: 检查数据库server_vals -> 有 + 未过期 -> 取用
     *                  |____ 没有 / 过期 -> 调用并新建 / 更新数据库
     *
     * @param null
     *
     * @return string
     */
	public function getAccessToken()
	{

        $serverVal = $this->searchServeVal('token');

		if(count($serverVal))
        {
            return $serverVal;
        }else{
            
            $wechat_get_token_url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=".$this->corpID."&corpsecret=".$this->corpSecret;
            $client = new Client();
            $json = $client->get($wechat_get_token_url)->getBody();
            $arr = json_decode($json, true);
            
            $wehat_token = $arr['access_token'];
            $expire = $arr['expires_in'];

            $go = ServerVal::updateOrCreate(['var_name' => 'token'], ['var_value'=> $wehat_token, 'expire'=>$expire, 'var_up_time' => time()]);

            return $wehat_token;
        };
	}

    /**
     * 获取微信用户信息:1. 获取cdoe
     *
     * @param null
     *
     * @return redirect url
     */
    public function oAuth2()
    {
        $redirect_url = Request::url();
        $wechat_Oauth2_url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$this->corpID."&redirect_uri=".$redirect_url."&response_type=code&scope=SCOPE&state=STATE#wechat_redirect";

        header("Location: $wechat_Oauth2_url");
        exit;
    }
    
    /**
     * 获取微信用户信息:2. 以cdoe和token换取 UserID DeviceId
     *
     * @param $code, getAccessToken()
     *
     * @return array
     */
    public function oAuth2UserInfo()
    {
        $wechat_Oauth2_user_info_url = "https://qyapi.weixin.qq.com/cgi-bin/user/getuserinfo?access_token=".$this->getAccessToken()."&code=".$this->code;
        $client = new Client();
        $json = $client->get($wechat_Oauth2_user_info_url)->getBody();
        $arr = json_decode($json, true);
        $this->oAuth2UserInfoArray = $arr;
    }

    /**
     * 设置用户session, cookie
     *
     * @param $userid
     *
     * @return mix
     */
    public function weChatUserSetCookieAndSession()
    {     
        $arr = $this->oAuth2UserInfoArray;
        $userid = $arr['UserId'];
        $deviceid = $arr['DeviceId'];

        $rec = Member::where('work_id', $userid)->first();

        if(count($rec)){
            
            if($rec->state === 0){
                //账号状态正常
                if(!Session::has('id')) Session::put('id', $rec->id);
                if(!Session::has('name')) Session::put('name', $rec->name);

                Cookie::queue('id', $rec->id, 20160);

            }else{
                return view('40x',['color'=>'warning', 'type'=>'2', 'code'=>'2.1']);
            }
                

        }else{
            return view('40x',['color'=>'danger', 'type'=>'1', 'code'=>'1.2']);
        }

    }


    /**
     * 初始化部门: 批量建立部门
     *
     * 要求: 从未创建部门或者清除所有部门,只余根部门
     *
     * @param json
     *
     * @return view or redirect
     */
    public function initDepartments ()
    {
        $recs = Department::where('id', '>', 1)->get();
        //$arr = array();

        if(count($recs)){

            foreach ($recs as $rec) {
                $arr  = [
                            'name'     => $rec->name, 
                            'parentid' => $rec->parentid, 
                            'order'    => $rec->order, 
                            'id'       => $rec->id,
                        ];

                $this->createDepartment($arr);
            } 

        }else{
            return view('40x',['color'=>'red', 'type'=>'1', 'code'=>'1.2']);
        }
    }

    /**
     * 初始化部门: 新建部门
     *
     * 待改进: 从其他模块调用,不能等待返回值 !!!
     *
     * @param array :['name', 'parentid', 'order', 'id']
     *
     * @return null
     */
    public function createDepartment ($array)
    {
        $post_JSON = json_encode($array, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        $wechat_department_create_url = 'https://qyapi.weixin.qq.com/cgi-bin/department/create?access_token='.$this->getAccessToken();

        $client = new Client();
        $client->request('POST', $wechat_department_create_url, ['body' => $post_JSON])->getBody();
        
        // $err = json_decode($rs, true);

        // if($err['errcode'] != 0){
        //     return view('40x',['errorCode' => '5', 'msg' => $err['errmsg']]); // 5: 微信服务器错误
        //     exit;
        // }
    }

    public function initUsers ()
    {
       $recs = Member::where('members.id', '>', 1)
                       ->leftJoin('positions', 'members.position', '=', 'positions.id')
                       ->select('members.*', 'positions.name as positionName')
                       ->get();
       //$rec = App\Member::find(2);

        if(count($recs)){

            foreach ($recs as $rec) {
                $arr  = [
                            'userid'     => $rec->work_id,
                            'name'       => $rec->name,
                            'department' => explode('-',$rec->department),
                            'position'   => $rec->positionName,
                            'mobile'     => $rec->mobile,
                            'gender'     => $rec->gender,
                            'email'      => $rec->email,
                            'weixinid'   => $rec->weixinid,
                        ];

                $this->createUser($arr);
               
            } 

        }else{
            return view('40x',['color'=>'red', 'type'=>'1', 'code'=>'1.2']);
        } 
    }

    /**
     * 新建: 用户
     *
     * 待改进: 从其他模块调用,不能等待返回值!!!
     *
     * @param array :['userid', 'name', 'departmnet', 'position', 'mobile', 'gender', 'email', 'wexinid']
     *
     * @return null
     */
    public function createUser ($array)
    {
            $post_JSON = json_encode($array, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

            $wechat_user_create_url = 'https://qyapi.weixin.qq.com/cgi-bin/user/create?access_token='.$this->getAccessToken();

            $client = new Client();
            $rs = $client->request('POST', $wechat_user_create_url, ['body' => $post_JSON])->getBody();
            //$err = json_decode($rs, true);
            //print_r($err);
            // $t = $err['errcode'];
            // if($t > 0){
            //     return view('40x',['errorCode' => '5', 'msg' => $err['errmsg']]); // 5: 微信服务器错误
            //     exit;
            // }
    }

    /**
     * 更新: 用户
     *
     * 待改进: 从其他模块调用,不能等待返回值!!!
     *
     * @param array :['userid', 'name', 'departmnet', 'position', 'mobile', 'gender', 'email', 'wexinid']
     *
     * @return null
     */
    public function updateUser ($array)
    {
            $post_JSON = json_encode($array, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

            $wechat_user_update_url = 'https://qyapi.weixin.qq.com/cgi-bin/user/update?access_token='.$this->getAccessToken();

            $client = new Client();
            $rs = $client->request('POST', $wechat_user_update_url, ['body' => $post_JSON])->getBody();
            //$err = json_decode($rs, true);
            //print_r($err);
            // $t = $err['errcode'];
            // if($t > 0){
            //     return view('40x',['errorCode' => '5', 'msg' => $err['errmsg']]); // 5: 微信服务器错误
            //     exit;
            // }
    }

    /**
    * 文本消息
    *
    */
    public function sendText($array)
    {
        
    }

    /**
    * 删除: 用户
    * 
    * @param UserId = members.work_id
    *
    * @return JSON
    */
    public function deleteUser($work_id)
    {
        $wechat_user_delete_url = 'https://qyapi.weixin.qq.com/cgi-bin/user/delete?access_token='.$this->getAccessToken().'&userid='.$work_id;
        $client = new Client();
        $client->get($wechat_user_delete_url);
        //$arr = json_decode($json, true);

        //return $arr['errcode'] === 0 ? true : false;

    }

    /**
    * other functions
    *
    */
}














