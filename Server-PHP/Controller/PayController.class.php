<?php
/**
 * Created by PhpStorm.
 * User: moTzxx
 * Date: 2018/6/15
 * Time: 14:32
 */

namespace WxApi\Controller;


use Think\Controller;
use WxApi\Model\OrderModel;

class PayController extends Controller
{

    protected function _initialize()
    {
        /**
         * 为了数据安全传输起见  我设置了POST请求方式
         */
        if (!IS_POST) {
            self::return_err('error request method');
        }
        $WeChat = C("WxPay");
        //微信支付参数配置(appid,商户号,支付秘钥)
        $config = array(
            'appid' => $WeChat['appid'],
            'app_secret' => $WeChat['app_secret'],
            'pay_mchid' => $WeChat['pay_mchid'],
            'pay_apikey' => $WeChat['pay_apikey'],
            'notify_url' => $WeChat['notify_url'],
            'login_url' => $WeChat['login_url'],
        );
        $this->config = $config;
    }

    /**
     * 获取微信用户的OpenID
     */
    public function getOpenID()
    {
        if (!IS_POST) {
            self::return_err('error request method');
        } else {
            $status = 0;
            $code = I('post.code') ? I('post.code') : 0;
            $config = $this->config;
            $wxLoginUrl = sprintf($config['login_url'],
                $config['appid'], $config['app_secret'], $code);

            $result = curl_get($wxLoginUrl);
            $wxResult = json_decode($result, true);
            if($wxResult['openid']){
                $status = 1;
            };
            if ($status){
                self::return_data($wxResult);
            }else{
                self::return_err("获取openID失败！");
            }
        }
    }

    /**
     * 预支付请求接口(POST)
     * @param string $openid openid
     * @param string $body 商品简单描述
     * @param string $order_sn 订单编号
     * @param string $total_fee 金额
     * @return  json的数据
     */
    public function prepay()
    {
        $config = $this->config;
        $openid = I('post.openid'); //或者从自己的数据中进行读取
        $body = I('post.body');
        $order_sn = I('post.order_sn');
        $total_fee = I('post.total_fee');

        /** -----------TODO --- 此处是我自己的业务逻辑处理操作--------------START------*/
        $this->prepayOrderDeal($order_sn);
        /** -----------TODO --------业务逻辑处理完成------------------------END-----*/
        //统一下单参数构造
        $unifiedorder = array(
            'appid' => $config['appid'],
            'mch_id' => $config['pay_mchid'],
            'nonce_str' => self::getNonceStr(),
            'body' => $body,
            'out_trade_no' => $order_sn . 'M' . time() . rand(0000, 9999),
            'total_fee' => $total_fee * 100,
            'spbill_create_ip' => get_client_ip(),
            'notify_url' => $config['notify_url'],
            'trade_type' => 'JSAPI',
            'openid' => $openid
        );
        $unifiedorder['sign'] = self::makeSign($unifiedorder);

        //请求数据
        $xmldata = self::array2xml($unifiedorder);
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $res = self::curl_post_ssl($url, $xmldata);
        if (!$res) {
            self::return_err("Can't connect the server");
        }
        // 这句file_put_contents是用来查看服务器返回的结果 测试完可以删除了
        //file_put_contents(APP_ROOT.'/Statics/log1.txt',$res,FILE_APPEND);

        $content = self::xml2array($res);
        if (strval($content['result_code']) == 'FAIL') {
            self::return_err(strval($content['err_code_des']));
        }
        if (strval($content['return_code']) == 'FAIL') {
            self::return_err(strval($content['return_msg']));
        }
        self::return_data(array('data' => $content));
        //$this->ajaxReturn($content);
    }

    /**
     * TODO 此处是我自己的业务逻辑处理
     * 因为不同的成型项目 一定会有自己的审核逻辑，可自行覆盖
     * @param $order_sn 订单号
     */
    public function prepayOrderDeal($order_sn)
    {
        return;
        $orderModel = new OrderModel();
        $checkStock = $orderModel->checkGoodsStockBySn($order_sn);
        if (!$checkStock) {
            self::return_err("Sorry，库存不足了");
        } else {
            //TODO 检查该订单是否已经支付
            $pay_status = $orderModel->getFieldBySn($order_sn, 'pay_status');
            $consignee = $orderModel->getFieldBySn($order_sn, 'consignee');
            $address = $orderModel->getFieldBySn($order_sn, 'address');
            if ($pay_status) {
                self::return_err("请不要重复支付哦！");
            } else {
                //TODO 检查该订单是否收货信息完整
                if (!$consignee || !$address) {
                    self::return_err("请完善您的收货信息，谢谢！");
                }
            }
        }
    }

    /**
     * 进行微信支付回调后的处理
     * TODO 此处的后续处理为本人的专属逻辑，请自行补充对应的业务逻辑即可
     * @param $result
     * TODO 强烈建议将其转化为 json 字符串形式，保存在数据表中，方便后期的微信退款操作
     * tip:$wx_pay_result_json = json_encode($result);
     */
    public function payNotifyOrderDeal($result)
    {
        $homeOrderController = new \Home\Controller\OrderController();
        $homeOrderController->payNotifyOrderDeal($result);
    }

    /**
     * 进行支付接口(POST)
     * @param string $prepay_id 预支付ID(调用prepay()方法之后的返回数据中获取)
     * @return  json的数据
     */
    public function pay()
    {
        $config = $this->config;
        $prepay_id = I('post.prepay_id');
        //此处获得的 $prepay_id 建议保存到订单数据表中，可方便后期"服务通知"业务的使用
        $data = array(
            'appId' => $config['appid'],
            'timeStamp' => time(),
            'nonceStr' => self::getNonceStr(),
            'package' => 'prepay_id=' . $prepay_id,
            'signType' => 'MD5'
        );
        $data['paySign'] = self::makeSign($data);
        $this->ajaxReturn($data);
    }

    /**
     * 微信支付回调验证
     * @return array|bool
     */
    public function notify()
    {
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];

        // 这句file_put_contents是用来查看服务器返回的XML数据 测试完可以删除了
        //file_put_contents(APP_ROOT.'/Statics/log2.txt',$res,FILE_APPEND);

        //将服务器返回的XML数据转化为数组
        $data = self::xml2array($xml);
        // 保存微信服务器返回的签名sign
        $data_sign = $data['sign'];
        // sign不参与签名算法
        unset($data['sign']);
        $sign = self::makeSign($data);

        // 判断签名是否正确  判断支付状态
        if (($sign === $data_sign) && ($data['return_code'] == 'SUCCESS') && ($data['result_code'] == 'SUCCESS')) {
            $result = $data;
            //获取服务器返回的数据
            $out_trade_no = $data['out_trade_no'];            //订单单号
            $openid = $data['openid'];                    //付款人openID
            $total_fee = $data['total_fee'];            //付款金额
            $transaction_id = $data['transaction_id'];    //微信支付流水号

            //TODO 此时可以根据自己的业务逻辑 进行数据库更新操作
            $this->payNotifyOrderDeal($result);

        } else {
            $result = false;
        }
        // 返回状态给微信服务器
        if ($result) {
            $str = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        } else {
            $str = '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
        }
        echo $str;
        return $result;
    }


    /**
     * TODO 微信申请退款操作
     * 重要变量：
     *      $order_sn = '2018000082009' 自己服务器的订单编号
     *      $refund_fee                 需要退款的金额 (例:10.50)
     *      $wxPayResultJsonRes         前期微信支付成功后回调保存的数据，
     *                                  原本在数据库中以 json字符串的形式保存，
     *                                  此处是取出后再 json_decode('xxxx',true)转化为了arr数组形式
     * 据库中以 json字符串的形式为 ————
         * {"appid":"wx87xxxxxxxxbc0","bank_type":"CFT",
         * "cash_fee":"2","fee_type":"CNY","is_subscribe":"N",
         * "mch_id":"1xxxxxx02","nonce_str":"t8wcdduity6f6k5acng33wzv5z56o7sh",
         * "openid":"okxsf5YWzAzEPNoV31IRqft-fa1c","out_trade_no":"201xxxxxx2709M15362284007942",
         * "result_code":"SUCCESS","return_code":"SUCCESS","time_end":"20180906180644",
         * "total_fee":"2","trade_type":"JSAPI","transaction_id":"4200000171201809060657362048"}
     */
    public function payRefund(){
        $config = $this->config;
        $order_sn = I('post.sn')?I('post.sn'):'';
        $refund_fee = I('post.refund_fee')?I('post.refund_fee'):'0';

        /*-----TODO 此处是我项目业务的特定处理逻辑，仅供参考---------------高能注释------------------*/
        $orderModel = new OrderModel();
        //$wxPayResultJsonRes 请参考上面的介绍，自行获取
        $wxPayResultJsonRes = $orderModel->getWxPayResultJsonRes($order_sn);
        /*-----------------------------------------------------------------------------------*/
        if ($wxPayResultJsonRes && $refund_fee){
            $out_trade_no = $wxPayResultJsonRes['out_trade_no'];
            //$out_refund_no 商户退款单号 自定义而已
            $out_refund_no = $order_sn.'refund'.time();
            $total_fee = $wxPayResultJsonRes['total_fee'];

            //统一下单退款参数构造
            $unifiedorder = array(
                'appid' => $config['appid'],
                'mch_id' => $config['pay_mchid'],
                'nonce_str' => self::getNonceStr(),
                'out_trade_no' => $out_trade_no,
                // out_refund_no 这个建议要唯一，避免出现请求一次，实际退款两次的情况
                'out_refund_no' => $out_refund_no,
                'total_fee' => $total_fee,
                //此处refund_fee 注意前面不要使用 number_format（）转化成字符串 ，或者更改为： number_format(5000, 2, '.', '')
                'refund_fee' => intval(floatval($refund_fee) * 100),
            );
            $unifiedorder['sign'] = self::makeSign($unifiedorder);
            //请求数据
            $xmldata = self::array2xml($unifiedorder);
            $opUrl = "https://api.mch.weixin.qq.com/secapi/pay/refund";
            $res = self::curl_post_ssl_refund($opUrl, $xmldata);
            if (!$res) {
                self::return_err("Can't connect the server");
            }
            $content = self::xml2array($res);
            if (isset($content['result_code']) && strval($content['result_code']) == 'FAIL') {
                self::return_err(strval($content['err_code_des']));
            }
            if (isset($content['return_code']) && strval($content['return_code']) == 'FAIL') {
                self::return_err(strval($content['return_msg']));
            }
            self::return_data(array('data' => $content));
        }else{
            self::return_err('不符合退款订单！');
        }
    }

    //---------------------------------------------------------------用到的函数------------------------------------------------------------

    /**
     * 此方法是为了进行 微信退款操作的 专属定制哦
     * (嘁，其实就是照搬了 人家官方的PHP Demo代码咯)
     * TODO 尤其注意代码中涉及到的 "证书使用方式（二选一）"
     * TODO 证书的路径要求为 服务器中的绝对路径[我的服务器为 CentOS6.5]
     * TODO 证书是 在微信支付开发文档中有所提及，可自行获取保存
     */
    protected function curl_post_ssl_refund($url, $vars, $second=30,$aHeader=array())
    {
        $ch = curl_init();
        //超时时间
        curl_setopt($ch,CURLOPT_TIMEOUT,$second);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '10.206.30.98');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);
        //TODO 以下两种方式需选择一种
        /*------- --第一种方法，cert 与 key 分别属于两个.pem文件--------------------------------*/
        //默认格式为PEM，可以注释
        //curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLCERT,'/mnt/www/Public/certxxxxxxxxxxxxxxxxxxxx755/apiclient_cert.pem');
        //默认格式为PEM，可以注释
        //curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLKEY,'/mnt/www/Public/certxxxxxxxxxxxxxxxxxxxx755/apiclient_key.pem');
        /**
         * 补充 当找不到ca根证书的时候还需要rootca.pem文件
         * TODO 注意，微信给出的压缩包中，有提示信息：
         *      由于绝大部分操作系统已内置了微信支付服务器证书的根CA证书,
         *      2018年3月6日后, 不再提供CA证书文件（rootca.pem）下载
         */
        //curl_setopt($ch, CURLOPT_CAINFO,'/mnt/www/Public/certxxxxxxxxxxxxxxxxxxxx755/rootca.pem');

        /*----------第二种方式，两个文件合成一个.pem文件----------------------------------------*/
        //curl_setopt($ch,CURLOPT_SSLCERT,getcwd().'/all.pem');

        if( count($aHeader) >= 1 ){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        }

        curl_setopt($ch,CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$vars);
        $data = curl_exec($ch);
        if($data){
            curl_close($ch);
            return $data;
        }
        else {
            $error = curl_errno($ch);
            //echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }
    /**
     * 错误返回提示
     * @param string $errMsg 错误信息
     * @param string $status 错误码
     * @return  json的数据
     */
    protected function return_err($errMsg = 'error', $status = 0)
    {
        exit(json_encode(array('status' => $status, 'result' => 'fail', 'errmsg' => $errMsg)));
    }


    /**
     * 正确返回
     * @param    array $data 要返回的数组
     * @return  json的数据
     */
    protected function return_data($data = array())
    {
        exit(json_encode(array('status' => 1, 'result' => 'success', 'data' => $data)));
    }

    /**
     * 将一个数组转换为 XML 结构的字符串
     * @param array $arr 要转换的数组
     * @param int $level 节点层级, 1 为 Root.
     * @return string XML 结构的字符串
     */
    protected function array2xml($arr, $level = 1)
    {
        $s = $level == 1 ? "<xml>" : '';
        foreach ($arr as $tagname => $value) {
            if (is_numeric($tagname)) {
                $tagname = $value['TagName'];
                unset($value['TagName']);
            }
            if (!is_array($value)) {
                $s .= "<{$tagname}>" . (!is_numeric($value) ? '<![CDATA[' : '') . $value . (!is_numeric($value) ? ']]>' : '') . "</{$tagname}>";
            } else {
                $s .= "<{$tagname}>" . $this->array2xml($value, $level + 1) . "</{$tagname}>";
            }
        }
        $s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
        return $level == 1 ? $s . "</xml>" : $s;
    }

    /**
     * 将xml转为array
     * @param  string $xml xml字符串
     * @return array    转换得到的数组
     */
    protected function xml2array($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $result = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $result;
    }

    /**
     *
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return 产生的随机字符串
     */
    protected function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /**
     * 生成签名
     * @return 签名
     */
    protected function makeSign($data)
    {
        //获取微信支付秘钥
        $key = $this->config['pay_apikey'];
        // 去空
        $data = array_filter($data);
        //签名步骤一：按字典序排序参数
        ksort($data);
        $string_a = http_build_query($data);
        $string_a = urldecode($string_a);
        //签名步骤二：在string后加入KEY
        //$config=$this->config;
        $string_sign_temp = $string_a . "&key=" . $key;
        //签名步骤三：MD5加密
        $sign = md5($string_sign_temp);
        // 签名步骤四：所有字符转为大写
        $result = strtoupper($sign);
        return $result;
    }

    /**
     * 微信支付发起请求
     */
    protected function curl_post_ssl($url, $xmldata, $second = 30, $aHeader = array())
    {
        $ch = curl_init();
        //超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '10.206.30.98');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);


        if (count($aHeader) >= 1) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        }

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmldata);
        $data = curl_exec($ch);
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }
}
