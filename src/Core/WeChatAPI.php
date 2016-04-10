<?php

namespace FooWeChat\Core;

use App;
use Config;
use Cookie;
use Input;
use Request;
use Session;
use GuzzleHttp\Client;

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
                    if(!Session::has('department')) Session::put('department', $rec->department);
                    if(!Session::has('position')) Session::put('position', $rec->position); 

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

}