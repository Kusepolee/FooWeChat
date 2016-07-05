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
    public $agentID;
    public $safe = 1;
    protected $notFollowUsers;

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
		$conf = Config::get('restrose');
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
            
            return $this->getAccessTokenFromWechat();
        };
	}

    /**
    * 从微信服务器获取token
    *
    */
    public function getAccessTokenFromWechat()
    {
        $wechat_get_token_url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=".$this->corpID."&corpsecret=".$this->corpSecret;
        $client = new Client();
        $json = $client->get($wechat_get_token_url)->getBody();
        $arr = json_decode($json, true);
        
        $wehat_token = $arr['access_token'];
        $expire = $arr['expires_in'];

        $go = ServerVal::updateOrCreate(['var_name' => 'token'], ['var_value'=> $wehat_token, 'expire'=>$expire, 'var_up_time' => time()]);

        return $wehat_token;
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

        if(array_has($arr, 'errcode') && (array_get($arr, 'errcode') == 4001 || array_get($arr, 'errcode') == 4002)){
            $this->getAccessTokenFromWechat();
            $this->oAuth2UserInfo();
        }else{
            $this->oAuth2UserInfoArray = $arr;
        }
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
        $json = $client->request('POST', $wechat_department_create_url, ['body' => $post_JSON])->getBody();
        $arr = json_decode($json, true);

        if(array_has($arr, 'errcode') && (array_get($arr, 'errcode') == 4001 || array_get($arr, 'errcode') == 4002)){
            $this->getAccessTokenFromWechat();
            $this->createDepartment();
        }
    }

    public function initUsers ()
    {
       $recs = Member::where('members.id', '>', 1)
                       ->where('show', 0)
                       ->where('private', 1)
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
        $json = $client->request('POST', $wechat_user_create_url, ['body' => $post_JSON])->getBody();
        $arr = json_decode($json, true);

        if(array_has($arr, 'errcode') && (array_get($arr, 'errcode') == 4001 || array_get($arr, 'errcode') == 4002)){
            $this->getAccessTokenFromWechat();
            $this->createUser();
        }
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
        $json = $client->request('POST', $wechat_user_update_url, ['body' => $post_JSON])->getBody();
        $arr = json_decode($json, true);

        if(array_has($arr, 'errcode') && (array_get($arr, 'errcode') == 4001 || array_get($arr, 'errcode') == 4002)){
            $this->getAccessTokenFromWechat();
            $this->updateUser();
        }
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
        $json = $client->get($wechat_user_delete_url)->getBody();;
        $arr = json_decode($json, true);

        if(array_has($arr, 'errcode') && (array_get($arr, 'errcode') == 4001 || array_get($arr, 'errcode') == 4002)){
            $this->getAccessTokenFromWechat();
            $this->deleteUser();
        }

    }

    /**
    * 获取在微信,但没有关注的人员
    *
    * @param null
    *
    * @return array work_ids
    */
    public function getWechatUsersNotFollow()
    {
        $conf = Config::get('restrose');
        $inside_department_id = $conf['custom']['inside_department_id'];

        $wechat_Inside_department_not_follow_users_url = 'https://qyapi.weixin.qq.com/cgi-bin/user/simplelist?access_token='.$this->getAccessToken().'&department_id='.$inside_department_id.'&fetch_child=1&status=4';

        $client = new Client();
        $json = $client->get($wechat_Inside_department_not_follow_users_url)->getBody();;
        $arr = json_decode($json, true);

        if(array_has($arr, 'errcode') && (array_get($arr, 'errcode') == 4001 || array_get($arr, 'errcode') == 4002)){
            $this->getAccessTokenFromWechat();
            $this->getWechatUsersNotFollow();
        }else{
            $array = [];
            $recs = $arr['userlist'];
            foreach ($recs as $rec) {
                $array[] = $rec['userid'];
            }
            return $array;
        }
    }

    /**
    * 验证是否还未关注
    *
    * @param $id or null 
    *
    * @return boolean
    */
    public function hasFollow($id=0)
    {
        $work_id_list = [];
        if (count($this->notFollowUsers)) {
            $work_id_list = $this->notFollowUsers;
        }else{
            $work_id_list = $this->getWechatUsersNotFollow();
            $this->notFollowUsers = $work_id_list;
        }

        if ($id === 0) $id = Session::get('id');

        $work_id = Member::find($id)->work_id;

        return array_search($work_id, $work_id_list) === false ? true : false;

    }

    /**
    * 文本消息
    * 
    * @param array - recievors ok
    *
    * @return wechat message
    */
    public function sendText($array, $body)
    {
        $content = ['content'=>$body];

        $array = array_add($array, 'msgtype', 'text');
        $array = array_add($array, 'agentid', $this->agentID);
        $array = array_add($array, 'text', $content);
        $array = array_add($array, 'safe', $this->safe);
        $post_JSON = json_encode($array, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

        $wechat_send_message_url = 'https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token='.$this->getAccessToken();

        $client = new Client();
        $json = $client->request('POST', $wechat_send_message_url, ['body' => $post_JSON])->getBody();
        $arr = json_decode($json, true);

        if(array_has($arr, 'errcode') && (array_get($arr, 'errcode') == 4001 || array_get($arr, 'errcode') == 4002)){
            $this->getAccessTokenFromWechat();
            $this->sendText();
        }
    }

    /**
    * news 消息
    *
    * $arr = ['title'=>'标题','description'=>'介绍','url'=>'链接','picurl'=>'图片链接'];
    *
    * @param array
    * @return send news
    */
    public function sendNews($array, $arr)
    {
        $array = array_add($array, 'msgtype', 'news');
        $array = array_add($array, 'agentid', $this->agentID);
        

        $news = ['articles'=>''];
        $array = array_add($array, 'news', $news);

        $array['news']['articles'] = $arr;

        //print_r($array);
        $post_JSON = json_encode($array, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

        $wechat_send_message_url = 'https://qyapi.weixin.qq.com/cgi-bin/message/send?access_token='.$this->getAccessToken();

        $client = new Client();
        $json = $client->request('POST', $wechat_send_message_url, ['body' => $post_JSON])->getBody();
        $arr = json_decode($json, true);

        print_r($arr);

        if(array_has($arr, 'errcode') && (array_get($arr, 'errcode') == 4001 || array_get($arr, 'errcode') == 4002)){
            $this->getAccessTokenFromWechat();
            $this->sendText();
        }

    }

    /**
    * 换取openid
    *
    */
    public function getOpenId($work_id){
        $wechat_get_openid_url = 'https://qyapi.weixin.qq.com/cgi-bin/user/convert_to_openid?access_token='.$this->getAccessToken();
        $arr=[];
        $arr = array_add($arr, 'userid', $work_id);
        $post_JSON = json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        $client = new Client();
        $json = $client->request('POST', $wechat_get_openid_url, ['body' => $post_JSON])->getBody();
        $err = json_decode($json, true);

        if(array_has($arr, 'errcode') && (array_get($arr, 'errcode') == 4001 || array_get($arr, 'errcode') == 4002)){
            $this->getAccessTokenFromWechat();
            $this->getOpenId();
        }
    }

    /**
    * 从微信换取jsapi_ticket并保存
    *
    * @param token
    *
    * @return string 
    */
    public function getJsapiTicketFromWechat()
    {
        $wechat_jsapi_url = 'https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token='.$this->getAccessToken();
        $client = new Client();
        $json = $client->get($wechat_jsapi_url)->getBody();
        $arr = json_decode($json, true);

        if(array_has($arr, 'errcode') && (array_get($arr, 'errcode') == 4001 || array_get($arr, 'errcode') == 4002)){
            $this->getAccessTokenFromWechat();
            $this->getJsapiTicket();
        }else{
            $jsapi_ticket = $arr['ticket'];
            $expire = $arr['expires_in'];
            ServerVal::updateOrCreate(['var_name' => 'jsapi'], ['var_value'=> $jsapi_ticket, 'expire'=>$expire, 'var_up_time' => time()]);
            return $arr['ticket'];
        }

    }

    /**
    * 获取jsapi_ticket
    *
    */
    public function getJsapiTicket()
    {
        $serverVal = $this->searchServeVal('jsapi');
        if(count($serverVal))
        {
            return $serverVal;
        }else{
            return $this->getJsapiTicketFromWechat();
        };
    }


    /**
    * other functions
    *
    */
}














