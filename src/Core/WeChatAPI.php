<?php

namespace FooWeChat\Core;

use App;
use Config;

/**
* 微信核心API
*/

class WeChatAPI
{

    private $corpID;
    private $corpSecret;
    private $corpTalken;
    private $agentID;

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
        $this->corpSecret = $this->conf('encodingAESKey');
        $this->corpTalken = $this->conf('talken');
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
        $recArray = App\ServerVal::where('name',$key)
                            ->where('updated_time', '>', (time() -7200))
                            ->get();
        return $recArray;
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

        $serverVal = $this->searchServeVal('talken');
		if(count($serverVal))
        {
            //可用
            return $serverVal->val;

        }else{
            //不可用
            $weChatGetTalkenUrl = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=".$this->corpID."&corpsecret=".$this->corpSecret;
            $res = file_get_contents($weChatGetTalkenUrl);
            //$ress = json_decode($res);
            //$res1 = utf8_encode($res);
            $res2 = json_decode($res);

            //print_r($res2);
            var_dump($res2);

            //return $ress;
            //return $res->'errmsg';
            //return $weChatGetTalkenUrl;
            //return 'null';
        };
	}
}