<?php
return array(
    //'配置项'=>'配置值'
    'wxPay' => [
        //TODO 此处使用的是小程序的 APPID
        'appid' => 'wx8787xxxxxxxxxxxxx',
        'app_secret' => '0a7xxxxxxxxxxxxxxxxxxxxxxxxxxxxx622',
        'pay_mchid' => '13xxxxxx02', // 微信支付MCHID 商户收款账号
        'pay_apikey' => '1qaxxxxxxxxxxxxxxxxxxxxxhgf5', // 微信支付KEY
        'notify_url' => 'https://www.mySercver.com/WxApi/Pay/notify', // 接收支付状态的连接
        // 微信使用code换取用户openid及session_key的url地址
        'login_url' => "https://api.weixin.qq.com/sns/jscode2session?" .
            "appid=%s&secret=%s&js_code=%s&grant_type=authorization_code",
    ],
);