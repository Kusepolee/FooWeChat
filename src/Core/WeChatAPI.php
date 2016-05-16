<?php

namespace FooWeChat\Core;

use App;
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

    protected $tmp;
    
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
        $this->corpID = $this->conf('corpID');
        $this->corpSecret = $this->conf('corpSecret');
        $this->corpTalken = $this->conf('token');
        $this->agentID = $this->conf('agentID');
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
        $recs = App\ServerVal::where('var_name', $key)
                            ->where('var_up_time', '>', (time() - 7200))
                            ->get();
        foreach ($recs as $rec) {
            return $rec->var_value;
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
            
            $weChatGetTalkenUrl = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=".$this->corpID."&corpsecret=".$this->corpSecret;
            $client = new Client();
            $json = $client->get($weChatGetTalkenUrl)->getBody();
            $arr = json_decode($json, true);
            $wehatToken = $arr['access_token'];

            $go = App\ServerVal::updateOrCreate(['var_name' => 'token'], ['var_value'=> $wehatToken, 'var_up_time' => time()]);

            return $wehatToken;
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
        $redirectUrl = Request::url();
        $weChatOauth2Url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$this->corpID."&redirect_uri=".$redirectUrl."&response_type=code&scope=SCOPE&state=STATE#wechat_redirect";

        header("Location: $weChatOauth2Url");
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
        $weChatOauth2UserInfoUrl = "https://qyapi.weixin.qq.com/cgi-bin/user/getuserinfo?access_token=".$this->getAccessToken()."&code=".$this->code;
        $client = new Client();
        $json = $client->get($weChatOauth2UserInfoUrl)->getBody();
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

        $recs = App\Member::where('work_id', $userid)->get();

        if(count($recs)){
            foreach ($recs as $rec) {
                if($rec->state === 0){
                    //账号状态正常
                    if(!Session::has('id')) Session::put('id', $rec->id);
                    if(!Session::has('name')) Session::put('name', $rec->name);

                    Cookie::queue('id', $rec->id, 20160);

                }else{
                    return view('40x')->with('errorCode','3');//权限不足
                    exit;
                }
                
            }

        }else{
            return view('40x')->with('errorCode','4');//未找到用户
            exit;
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
        $recs = App\Department::where('id', '>', 1)->get();
        //$arr = array();

        if(count($recs)){

            foreach ($recs as $rec) {
                $arr  = array('name' => $rec->name, 
                                'parentid' => $rec->parentid, 
                                'order' => $rec->order, 
                                'id' => $rec->id,
                                );
                $this->createDepartment($arr);
            } 

        }else{
            return view('40x',['errorCode' => '4']);
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
        $postJSON = json_encode($array, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        $weChatDepartmentCreateUrl = 'https://qyapi.weixin.qq.com/cgi-bin/department/create?access_token='.$this->getAccessToken();

        $client = new Client();
        $client->request('POST', $weChatDepartmentCreateUrl, ['body' => $postJSON])->getBody();
        // $err = json_decode($rs, true);

        // if($err['errcode'] != 0){
        //     return view('40x',['errorCode' => '5', 'msg' => $err['errmsg']]); // 5: 微信服务器错误
        //     exit;
        // }
    }

    public function initUsers ()
    {
       $recs = App\Member::all();
       //$rec = App\Member::find(2);

        if(count($recs)){

            foreach ($recs as $rec) {
                $arr  = array(
                                'userid' => $rec->work_id,
                                'name' => $rec->name,
                                'department' => explode('-',$rec->department),
                                'position' => $rec->position,
                                'mobile' => $rec->mobile,
                                'gender' => $rec->gender,
                                'email' => $rec->email,
                                'weixinid' => $rec->weixinid,
                                );
                $this->createUser($arr);
               
            } 

        }else{
            return view('40x',['errorCode' => '4']);
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
            $postJSON = json_encode($array, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

            $weChatUserCreateUrl = 'https://qyapi.weixin.qq.com/cgi-bin/user/create?access_token='.$this->getAccessToken();

            $client = new Client();
            $rs = $client->request('POST', $weChatUserCreateUrl, ['body' => $postJSON])->getBody();
            //$err = json_decode($rs, true);
            //print_r($err);
            // $t = $err['errcode'];
            // if($t > 0){
            //     return view('40x',['errorCode' => '5', 'msg' => $err['errmsg']]); // 5: 微信服务器错误
            //     exit;
            // }
    }


}














