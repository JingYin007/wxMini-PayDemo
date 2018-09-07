// pages/cart/payment/index.js
var util = require('../../../utils/util.js');
Page({

    /**
     * 页面的初始数据
     */
    data: {},
    /**
     * 生命周期函数--监听页面加载
     */
    onLoad: function (options) {

    },
    /**
     * 点击测试 支付效果
     */
    btnClickToPay: function () {
        var payInfo = {
            body: 'zhangTestWxPAy!',
            total_fee: 100,
            order_sn: sn ? sn : 0,
        }
        this.wxPay(payInfo);
    },

    /**
     * TODO 支付函数 【需要支付时调用此函数即可】
     * @param  {[type]} _payInfo [description]
     *  此处需要传入一个 json数组，是实际业务的数据，例如我传的类似：
     * var _payInfo = {
        body: 'zhangTestWxPAy!',
        total_fee: 100,
        order_sn: '10224302324',
      }
     * @return {[type]}          [description]
     */
    wxPay: function (_payInfo) {
        this.setData({
            loading: true
        });
        var payInfo = {
            body: '',
            total_fee: 0,
            order_sn: ''
        }
        //将所有可枚举属性的值从一个或多个源对象复制到目标对象,然后返回目标对象
        Object.assign(payInfo, _payInfo);
        if (payInfo.body.length == 0) {
            wx.showToast({
                icon: 'none',
                title: '支付信息描述错误'
            })
            return false;
        }
        if (payInfo.total_fee == 0) {
            wx.showToast({
                icon: 'none',
                title: '支付金额不能0'
            })
            return false;
        }
        if (payInfo.order_sn.length == 0) {
            wx.showToast({
                icon: 'none',
                title: '订单号不能为空'
            })
            return false;
        }
        var This = this;
        This.getOpenid(payInfo)
    },
    /**
     * 获取openID
     * 不管是微信网页支付还是小程序支付，都是需要获取当前用户的openID的
     */
    getOpenid: function (payInfo) {
        var self = this;
        wx.login({
            success: function (res) {
                var postData = {
                    code: res.code
                };
                util.http_post('pay/getOpenID', postData, (data) = > {
                    if(data.status
            )
                {
                    payInfo.openid = data.data.openid;
                    util.http_post('pay/prepay', payInfo, self.prepayCallBack);
                }
            else
                {
                    util.showToast(0, '网络繁忙，请稍后再试...')
                }
            })
            },
            fail: function (res) {
                util.showToast(0, '登录凭证获取失败，可稍后再试...')
            }
        });
    },
    /**
     * 微信预支付回调函数处理
     */
    prepayCallBack: function (data) {
        var that = this;
        console.log('prepayCallBack:');
        console.log(data);
        if (!data.status) {
            util.showToast(0, data['errmsg']);
            return false;
        } else {
            var postData = {
                prepay_id: data.data.data.prepay_id
            };
            util.http_post('pay/pay', postData, that.payCallBack);
        }
    },
    /**
     * 微信唤醒支付的回调操作
     */
    payCallBack: function (payResult) {
        var self = this;
        console.log(payResult);
        if (payResult.status != 0) {
            wx.requestPayment({
                'timeStamp': payResult.timeStamp.toString(),
                'nonceStr': payResult.nonceStr,
                'package': payResult.package,
                'signType': payResult.signType,
                'paySign': payResult.paySign,
                'success': function (succ) {
                    //此处做支付成功后的页面展示
                    console.log('Pay-Success');
                    // success && success(succ);
                    wx.redirectTo({
                        url: '/pages/cart/results/index?status=1',
                    });
                },
                'fail': function (err) {
                    //此处做支付失败后的页面展示
                    console.log('Pay-Fail');
                    console.log(err);
                    // fail && fail(err);
                    wx.redirectTo({
                        url: '/pages/cart/results/index?status=0',
                    });
                },
                'complete': function (comp) {
                    self.setData({
                        loading: false
                    })
                }
            })
        } else {
            console.log('Fail-Pay');
        }
    },
    /**
     * 生命周期函数--监听页面初次渲染完成
     */
    onReady: function () {
    },

})