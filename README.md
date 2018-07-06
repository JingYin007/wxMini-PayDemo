# wxMini-PayDemo
此代码，整合小程序支付的核心代码，请看考知道文档进行配置  上传时间：2018-07-07
# 背景

- 近期进行小程序的开发，毕竟是商城项目的开发，最后牵扯到的微信支付是必要的
- 个人开发过程中也是遇到各种问题
- 在此，进行代码的详细配置，以方便小程序新手的快速操作

> 使用语言：`PHP`
> 框架：`ThinkPHP 3.2 `
>  整理时间：`2018-07-07`

tip:  **`【代码都是可转化的，即便是ThinkPHP5.0+ 还是Laravel框架，相对熟悉PHP代码语法的，进行转化也只是分分钟的事哦！】`**

# 一、开发前的准备

## ①. 了解小程序支付业务
- 此处，官方已做了详细说明 —— [***业务说明***](https://pay.weixin.qq.com/wiki/doc/api/wxa/wxa_api.php?chapter=7_11&index=2)

> `很多人这一步还没有完成，就咔咔咔的测试支付功能，显然是太急于求成了，比如：我！`
> 注意：
> 1. 要开通微信支付功能(一般有两三天的审核时间)
> 2. 本人开通后，选择的是 `绑定一个已有的微信支付商户号`，也就几分钟的事

-  此处请阅读官方文末的注意事项：

>	1 `appid` 必须为最后拉起收银台的小程序 `appid`； #***这句话感觉不说还好，一说更容易引起多余的考虑(忽视)***
	2 `mch_id` 为和 `appid` 成对绑定的支付商户号，收款资金会进入该商户号；# ***此处我直接使用了所绑定的商户号中的 mch_id*** 
	3 `trade_type` 请填写 `JSAPI`； #***可暂时忽略，因为我的代码已进行了配置***
	4 `openid` 为`appid` 对应的用户标识，即使用 `wx.login`接口获得的 `openid` #***可参考我的 payment/index.js代码***

## ②. 阅读业务流程图
- 这个图示，本人强烈推荐阅读，流程明确了，自然代码也就理顺了！
![](https://img-blog.csdn.net/20180706183418934?watermark/2/text/aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3UwMTE0MTU3ODI=/font/5a6L5L2T/fontsize/400/fill/I0JBQkFCMA==/dissolve/70)

## ③. 开发步骤
-  如果开发者已做过JSAPI或JSSDK调起微信支付，接入小程序支付非常相似，以下是三种接入方式的对比：
![](https://img-blog.csdn.net/20180706180312153?watermark/2/text/aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3UwMTE0MTU3ODI=/font/5a6L5L2T/fontsize/400/fill/I0JBQkFCMA==/dissolve/70)

- 如此看来，小程序要想集成支付功能，倒是简单多了
- 对公众号微信支付感兴趣的可以参考之前整理的一篇文章 —— [***微信公众平台开发[4] —— ThinkPHP 框架下微信支付***](https://blog.csdn.net/u011415782/article/details/78007022)

# 二、服务端代码文件的使用指导

- 这里进行配置的代码，都在源码包的 `wxMini-PayDemo\Server-PHP` 目录下 

> 声明：***因为本人所提供的代码是基于 `ThinkPHP3.2` 框架编写整理的，所以对于使用过 `ThinkPHP` 或 `Laravel` 框架的PHPer来说，简单明了，可根据自己的框架进行调整适配，所以，此处讲的可能不会太过琐碎 ***

## ①. 公共配置文件的数据补充

- 此为源代码中的 `wxMini-PayDemo\Server-PHP\Conf\config.php`，此文件代码比较少，我直接进行展示：
```
return array(
    //'配置项'=>'配置值'
    'wxPay' => [
        //TODO 此处使用的是小程序的 APPID
        'appid' => 'wx8787xxxxxxxxxxxxx',
        'app_secret' => '0a7xxxxxxxxxxxxxxxxxxxxxxxxxxxxx622',
        'pay_mchid' => '13xxxxxx02', // 微信支付MCHID 商户收款账号
        'pay_apikey' => '1qaxxxxxxxxxxxxxxxxxxxxxhgf5', // 微信支付KEY
        'notify_url' => 'https://www.mySercver.com/WxApi/Pay/notify', // 微信支付成功后进行回调的链接
        'login_url' => "https://api.weixin.qq.com/sns/jscode2session?" .
            "appid=%s&secret=%s&js_code=%s&grant_type=authorization_code", // 微信使用code换取用户openid及session_key的url地址
    ],
);
```
- 对于上述配置信息的来源，应该没啥疑问吧？
- 注意一点，`notify_url`作为支付回调的链接地址，要求配置成自己的服务器路径，同时注意协议的要求`https`（小程序官方要求，需要进行域名的配置）

## ②. 公共方法 function.php 的补充

- 对于本人的逻辑处理中，其实放在此处并需要调用的只有一个方法 `curl_get()`
- 并且只在 `PayController.class.php` 的 `getOpenID()` 方法中进行了一次调用，也可以自行提取使用哦
![](https://img-blog.csdn.net/20180706190149413?watermark/2/text/aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3UwMTE0MTU3ODI=/font/5a6L5L2T/fontsize/400/fill/I0JBQkFCMA==/dissolve/70)

## ③. 核心处理文件 `PayController.class.php`

>此文件代码已做了详细处理，在你正确放置后，需要注意的几点如下：

- (1). 注意命名空间 `namespace` 与自己业务代码的对应 

- (2). 在 `prepay()` 方法中，因为不同的业务都会有属于自己的判断处理逻辑，
![](https://img-blog.csdn.net/20180706191017794?watermark/2/text/aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3UwMTE0MTU3ODI=/font/5a6L5L2T/fontsize/400/fill/I0JBQkFCMA==/dissolve/70)
所以，在使用是完全可以替换掉所调用的 `prepayOrderDeal()`方法，相信也是一看就懂哦！

- (3). 再有就是，在 `notify()` 这个回调方法中，一定会涉及到自己业务的更新处理，所以被调用的 `payNotifyOrderDeal()` 就可以改成你自己的业务处理逻辑了
![](https://img-blog.csdn.net/20180706191536731?watermark/2/text/aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3UwMTE0MTU3ODI=/font/5a6L5L2T/fontsize/400/fill/I0JBQkFCMA==/dissolve/70)
```
注意，此处传入的 "$result" 参数中 #包含着微信支付的众多信息，可自行提取
比如我主要使用的就是其中的 `out_trade_no`和 `total_fee`:
前者用于匹配我对该已支付订单的后续更新操作 
	#【提示：我在使用时需要使用 "M" 进行字符串的截取才是我自己业务的实际订单编号哦！】;
后者是实际消费的金额，可用于数据表的记录
```

# 三、小程序端的代码配置指导

- 这里进行配置的代码，都在源码包的 `wxMini-PayDemo\wxChat` 目录下
> 为了项目代码的可管理性/通用性，我自行提取了主要的两个公共文件 `config.js` 和 `util.js`;

## ①. utils下公共 `config.js` 文件的使用



- 1 `config.js` 文件中，主要就是配置一些公共访问路径之类的数据，方便后期代码上线后的链接更改，所以对于其中的 `restUrl` 和 `imgServer` 修改为自己的服务器地址即可
![](https://img-blog.csdn.net/20180706194117380?watermark/2/text/aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3UwMTE0MTU3ODI=/font/5a6L5L2T/fontsize/400/fill/I0JBQkFCMA==/dissolve/70)
注意一点：我的 `restUrl` 是对应于我的小程序 Api接口路径的，举个例子，我的支付回调路径为 :
`https://www.mySercver.com/WxApi/Pay/notify`

## ②. utils下公共 `util.js` 文件的使用

- 这个是和 `config.js` 文件在同一目录下的公共文件，其实就是整合了三个主要的方法，主要注意的是，如果你有所补充，记得在文件的最后进行输出就好
```
module.exports = {
  http_get: http_get,
  http_post: http_post,
  showToast: showToast,
}
```

## ③. payment/index.js文件的使用
- 此文件作为 小程序微信支付前端的核心文件，在保证你的各个文件目录对应配置正确的情况下，只需在进行支付唤醒时，调用其中的 `btnClickToPay()` 方法即可：
![](https://img-blog.csdn.net/20180706195125911?watermark/2/text/aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3UwMTE0MTU3ODI=/font/5a6L5L2T/fontsize/400/fill/I0JBQkFCMA==/dissolve/70)
- 当然，我只是随便定的一个方法，实际使用的时候，其实就是以类似的形式，去调用后面的 `wxPay()`方法呗！

# 四、使用及测试效果

## ①. 测试效果
- 在我的小程序项目中，唤醒的效果（开发工具中）如下：
![](https://img-blog.csdn.net/20180706195705551?watermark/2/text/aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3UwMTE0MTU3ODI=/font/5a6L5L2T/fontsize/400/fill/I0JBQkFCMA==/dissolve/70)

- 如果是在自己的手机端进行测试，在保证你的域名配置正确的情况下，唤醒的样式就是常见的样子：
-![](https://img-blog.csdn.net/20180706200014340?watermark/2/text/aHR0cHM6Ly9ibG9nLmNzZG4ubmV0L3UwMTE0MTU3ODI=/font/5a6L5L2T/fontsize/400/fill/I0JBQkFCMA==/dissolve/70)

## ②. 补充说明

- 相信在实际配置使用的过程中一定会出现各种问题，我也是一点点的梳理排错过来的
- 前面的多是些配置问题的规范，如果到了最后的唤醒阶段，出现的问题要注意查看开发工具的控制台，其中会有比较详细的报错信息，然后再进行排查解决

# 附录：

## [>>> 源码下载参考](https://github.com/JingYin007/wxMini-PayDemo) 