# 微信平台网页授权登录+自定义朋友圈分享
[![Support](https://img.shields.io/badge/support-PHP-blue.svg?style=flat)](http://www.php.net/)
[![Support](https://img.shields.io/badge/support-ThinkPHP-red.svg?style=flat)](http://www.thinkphp.cn/)

## 原理介绍
大家平常在朋友圈看到一些图文的时候，发现点进去需要授权登录，其实这是该公众号调用了微信的接口进行了二次开发，从而获取你的微信信息，并且能控制该图文分享时的图片、文字和跳转链接，达到推广的目的。微信接口文档:<a href="http://mp.weixin.qq.com/wiki/4/9ac2e7b1f1d22e9e57260f6553822520.html">http://mp.weixin.qq.com/wiki/4/9ac2e7b1f1d22e9e57260f6553822520.html</a>

## 需求简述
1. 点击该图文后进行微信授权登录；
2. 完成游戏后，分享朋友圈能自定义图片、标题和跳转链接；

## 实现流程
### 1、编写授权页面，活动图文先跳转至授权页面	
调用微信授权接口，带上AppID和回调地址参数

	/*
     * 授权页面
     * param1:action 回调方法
     */
    public function oauthIndex(){
        $action = $_REQUEST['action'];
        $AppID = 'xxxxxxx';
        $callback = U('WechatPlatform/'.$action);
        $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$AppID.'&redirect_uri='.$callback.'&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect';
        header('location:'.$url);
    }
### 2、用户授权成功后，微信会把code返回到回调地址中，利用code获取授权access_token和openid等信息
    /*
     * 回调页面
     * param1:code 回调授权码
     */
    public function callback(){
        //接收回调code
        $code = $_REQUEST['code'];
        if($code){
            //获取授权access_token、openid
            $re = $this->getOauthAccessToken($code);
            $access_token = $re['access_token'];
            $openid = $re['openid'];
            if($access_token){
                //获取微信用户信息
                $re = $this->getUserMsg($access_token,$openid);
                $this->assign('status',1);
                $this->assign('re',$re);
            }else{
                $this->assign('status',0);//返回授权失败
            }
        }else{
            $url = U('WechatPlatform/oauthIndex').'?action=callback';
            header('location:'.$url);
        }
        $this->display();
    }

	/*
     * 获取授权access_token (需要网页授权等用到)
     * param1：code 回调授权码
     */
    private  function getOauthAccessToken($code){
        $AppID = 'xxxxxxx';
        $AppSecret = 'xxxxxxx';
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$AppID.'&secret='.$AppSecret.'&code='.$code.'&grant_type=authorization_code';
        //curl
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,$url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_GET, 1);
        $result = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($result,true);
        return $result;
    }


### 3、通过access_token和openid获取微信用户信息
该接口会返回微信用户的昵称、性别、头像等相关信息，可以按需求进行入库、登录等操作

    /*
     * 获取微信用户信息
     * param1:access_token 授权access_token
     * param2:openid 微信用户标识码
     */
    private  function getUserMsg($access_token,$openid){
        $url = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN';
        //curl
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,$url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_GET, 1);
        $result = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($result,true);
        return $result;
    }
以上是php后端部分，已完成授权登录需求。

下面是JS前端部分，将要实现自定义朋友圈分享

### 4、在分享页加载微信js-sdk <a href="http://res.wx.qq.com/open/js/jweixin-1.0.0.js">http://res.wx.qq.com/open/js/jweixin-1.0.0.js</a>
文档地址:

**<a href="http://mp.weixin.qq.com/wiki/11/74ad127cc054f6b80759c40f77ec03db.html">http://mp.weixin.qq.com/wiki/11/74ad127cc054f6b80759c40f77ec03db.html</a>**

### 5、在调用wx.config方法前，先通过ajax在后端生成签名，然后调用wx.config进行验证，再调用wx.onMenuShareTimeline等分享接口，自定义分享内容
JS代码:

	<script src="http://res.wx.qq.com/open/js/jweixin-1.0.0.js" type="text/javascript"></script>
     <script src="js/jquery-1.11.1.min.js" type="text/javascript"></script>
     <script type="text/javascript" >
         //微信SDK:
         $(function(){
             var jsUrl = "{:U('WechatPlatform/h5')}";
             //获取签名
             $.ajax({
                 url:"{:U('WechatPlatform/getSign')}",
                 type:"POST",
                 async:false,
                 cache:false,
                 data:'jsUrl='+jsUrl,
                 success:function(data){
                     data = eval('('+data+')');
                     if(data.status == '1'){
                         var sign= data.sign;
                         var timeStamp = data.timeStamp;
                         var randStr = data.randStr;
                         //验证接口
                         wx.config({
                             debug: false, // 开启调试模式,调用的所有api的返回值会在客户端alert出来，若要查看传入的参数，可以在pc端打开，参数信息会通过log打出，仅在pc端时才会打印。
                             appId: 'xxxxxxx', // 必填，公众号的唯一标识
                             timestamp:timeStamp, // 必填，生成签名的时间戳
                             nonceStr: randStr, // 必填，生成签名的随机串
                             signature: sign,// 必填，签名，见附录1
                             jsApiList: [
                                 'onMenuShareTimeline',
                                 'onMenuShareAppMessage',
                                 'onMenuShareQQ',
                             ] // 必填，需要使用的JS接口列表，所有JS接口列表见附录2
                         });
                     }
                 }
             });
             wx.ready(function(){
                 //分享朋友圈接口
                 wx.onMenuShareTimeline({
                     title: '天蝎男神', // 分享标题
                     link: 'http://m.9kus.com/WechatPlatform/oauthIndex?action=starTest', // 分享链接
                     imgUrl: '{$resUrl}activity/starTest/images/man01.png', // 分享图标
                     success: function () {
                         // 用户确认分享后执行的回调函数
                     },
                     cancel: function () {
                         // 用户取消分享后执行的回调函数
                     }
                 });
                 //分享给微信好友
                 wx.onMenuShareAppMessage({
                     title: '天蝎男神', // 分享标题
                     desc: '', // 分享描述
                     link: 'http://m.9kus.com/WechatPlatform/oauthIndex?action=starTest', // 分享链接
                     imgUrl: '{$resUrl}activity/starTest/images/man01.png', // 分享图标
                     success: function () {
                         // 用户确认分享后执行的回调函数
                     },
                     cancel: function () {
                         // 用户取消分享后执行的回调函数
                     }
                 });
                 //分享到QQ
                 wx.onMenuShareQQ({
                     title: '天蝎男神', // 分享标题
                     desc: '', // 分享描述
                     link: 'http://m.9kus.com/WechatPlatform/oauthIndex?action=starTest', // 分享链接
                     imgUrl: '{$resUrl}activity/starTest/images/man01.png', // 分享图标
                     success: function () {
                         // 用户确认分享后执行的回调函数
                     },
                     cancel: function () {
                         // 用户取消分享后执行的回调函数
                     }
                 });
             });
         });
     </script>

php代码:

    /*
	 *获取签名
	 *param1:jsUrl 当前页面
	 */
	 public function getSign(){
			$jsUrl = $_REQUEST['jsUrl'];
            $timeStamp = time();
            //生成随机字符串
            $str = array_merge(range(0,9),range('a','z'),range('A','Z'));
            shuffle($str);
            $randStr = implode('',array_slice($str,0,16));//随机字符串
			//获取access_token
			$re = $this->getAccessToken();
			$access_token = $re['access_token'];
			//获取jsapi_ticket
			$url ='https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$access_token.'&type=jsapi';
			//curl
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL,$url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_GET, 1);
			$result = curl_exec($curl);
			curl_close($curl);
			$result = json_decode($result,true);
			$jsapi_ticket = $result['ticket'];
			//生成签名
			$str = 'jsapi_ticket='.$jsapi_ticket.'&noncestr='.$randStr.'&timestamp='.$timeStamp.'&url='.$jsUrl;
            $sign = sha1($str);
			$re = array();
			$re['status'] = 1;
			$re['sign'] = $sign;
            $re['timeStamp'] = $timeStamp;
            $re['randStr'] = $randStr;
			echo json_encode($re);exit;
	 }
#   w e c h a t S h a r e  
 