<?php
return array(
    //'配置项'=>'配置值'
    'wxPay' => [
        //TODO 此处使用的是小程序的 APPID
        'appid' => 'wx8787213c85f13bc0', //wxda8621f25030c553
        'app_secret' => '0a78b7e13bef9c3e72b27c093c880622',
        'pay_mchid' => '1390724902', // 微信支付MCHID 商户收款账号
        'pay_apikey' => '1qaz2wsxhuangyi5feitengjd3sdhgf5', // 微信支付KEY
        'notify_url' => 'https://www.fetow.com/WxApi/Pay/notify', // 接收支付状态的连接
        // 微信使用code换取用户openid及session_key的url地址
        'login_url' => "https://api.weixin.qq.com/sns/jscode2session?" .
            "appid=%s&secret=%s&js_code=%s&grant_type=authorization_code",
    ],
);