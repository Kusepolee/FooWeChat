<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 微信企业号配置文件
    |--------------------------------------------------------------------------
    |
    | 配置模式：
    |  -- 开发模式：dev
    |  -- 服务器模式：server
    |
    */

    'mode' => 'dev',

    'dev' => [
        'corpID'         => 'wx53445ce4e5929352',
        'corpSecret'     => 'AHFKLwfiEgiIAh7vHBOpLfUxIpC1_IZf1o_0SFMn00_LyNNQnn-8AxRhNPducJtx',
        'agentID'        => 0,
        'token'          => 'EFPF9vnXrnbyUtcBnaqvdqw6T',
        'encodingAESKey' => 'MnrTikRxQTibVLHKRcmmWsXsl5gKje4JBlsPaVa9DFl',
        'callBakcUrl'    => '',
    ],
    
    'server' => [
        'corpID'         => 'wx32726be4bb032ac5',
        'corpSecret'     => 'KQALlMGbpOBd75sRJXidO_cOePrbi2getTUw4ZzGLlBdfDUl5SoHODYP2iVJroa5',
        'agentID'        => 6,
        'token'          => 'Kh6E3rdp8mSZ14S7CfigL',
        'encodingAESKey' => 'bHVHIlpi6Ouc1jiLfPrRJHNCraNsrxZuXAQdDNgg2ir',
        'callBakcUrl'    => '',
    ],

    'custom' => [
        'name'                 => '江苏恒久滚塑制品有限公司',
        'nic_name'             => '恒久滚塑',
        'short_name'           => '恒久-塑件专家',
        'year'                 => '2015',
        'tel'                  => '0527 - 8352 1018',
        'fax'                  => '0527 - 8777 4118',
        'email'                => 'hi@henjou.com',
        'url'                  => 'www.henjou.com',
        'address'              => '江苏省沭阳县七雄工业园区',
        'posetcode'            => '223600',
        'bank'                 => '中国农业银行股份有限公司沭阳建陵支行',
        'accunt'               => '1050 0601 0400 0666 0',
        'wechat_code'          => '40UUDDfEH2pgrXNv9xDx',
        'inside_department_id' => 2,
    ],

    'app' =>[
        'dir'                 => '/mnt/may/',
        'git_pull_key'        => 'king0105',
        'composer_update_key' => 'king0105',
    ],

];

