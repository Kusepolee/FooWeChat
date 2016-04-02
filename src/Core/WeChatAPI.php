<?php

namespace FooWeChat\Core;

use App;
use Config;
use GuzzleHttp\Client;

/**
* 微信核心API
*/

class WeChatAPI
{

    private $corpID;
    private $corpSecret;
    private $corpTalken;
    private $agentID;

    protected $client;

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
		$conf = Config::get('wechat');
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

}