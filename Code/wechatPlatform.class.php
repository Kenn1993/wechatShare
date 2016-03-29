<?php
/**
 * 微信公众平台类
 */
class WechatPlatformAction extends CommonAction{
    public function index(){
        //定义 token
        define("TOKEN", "weixin");
        if(isset($_GET["echostr"])){
            //调用验证方法
            $this->valid();
        }else{
            //调用自动回复方法
            $this->responseMsg();
        }
    }
    /*
     * 验证方法（第一次开启开发者模式时调用）
     */
    public function valid(){
        $echoStr = $_GET["echostr"];
        //valid signature , option
        if($this->checkSignature()){
            echo $echoStr;
            exit;
        }
    }

    /*
     * 自动回复
     */
    public function responseMsg(){
        $postStr = file_get_contents("php://input");
        if (!empty($postStr)){
            libxml_disable_entity_loader(true);
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $fromUsername = $postObj->FromUserName;
            $toUsername = $postObj->ToUserName;
            $keyword = trim($postObj->Content);
            $msgType = $postObj->MsgType;
            $time = time();
            $textTpl = "<xml>
							<ToUserName><![CDATA[%s]]></ToUserName>
							<FromUserName><![CDATA[%s]]></FromUserName>
							<CreateTime>%s</CreateTime>
							<MsgType><![CDATA[%s]]></MsgType>
							<Content><![CDATA[%s]]></Content>
							<FuncFlag>0</FuncFlag>
							</xml>";
            if($msgType=='event'){//关注时自动回复
                $msgType = "text";
                $contentStr = "亲，感谢您关注KennBlog";
                $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                echo $resultStr;
            }elseif($msgType=='text' && $keyword){
                    $msgType = "text";
                    $contentStr = "技术交流请到kennblog.caterest.com留言";
                    $resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
                    echo $resultStr;
            }else{
                echo "";
            }
        }else {
            echo "";
            exit;
        }
    }

    /*
     * 查询连接状态
     */
    private function checkSignature(){
        // you must define TOKEN by yourself
        if (!defined("TOKEN")) {
            throw new Exception('TOKEN is not defined!');
        }

        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];

        $token = TOKEN;
        $tmpArr = array($token, $timestamp, $nonce);
        // use SORT_STRING rule
        sort($tmpArr, SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );

        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }

    /*
     * 生成自定义菜单(获取非开发者模式下的菜单再生成一次)
     */
    public function createMenu(){
        //获取access_token
        $access_token = $this->getAccessToken();
        //获取原有菜单设置
        $url = 'https://api.weixin.qq.com/cgi-bin/get_current_selfmenu_info?access_token='.$access_token;
        //curl
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,$url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_GET,true);
        $result = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($result,true);
        //生成自定义菜单
        $postData=array();
        $postData['button'] = $result['selfmenu_info']['button'];
        //处理数据
        foreach($postData['button'] as $k=>$v){
            foreach($v['sub_button']['list'] as $k2=>$v2){
                if($v2['type']=='news'){
                    $v['sub_button']['list'][$k2]['type'] = 'view_limited';
                    $v['sub_button']['list'][$k2]['media_id'] = $v2['value'];
                    if($v2['news_info'] && $v2['value']){
                        unset($v['sub_button']['list'][$k2]['value']);
                        unset($v['sub_button']['list'][$k2]['news_info']);
                    }
                }
            }
            if($v['sub_button']){
                $postData['button'][$k]['sub_button'] = $v['sub_button']['list'];
            }
        }
        $json = json_encode($postData,JSON_UNESCAPED_UNICODE);
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$access_token;
        //curl
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,$url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POST,1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        $result = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($result,true);
        dump($result);
    }

    /*
     * 获取access_token
     */
    private  function getAccessToken(){
        $AppID = 'xxxxxxx';
        $AppSecret ='xxxxxxxx';
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$AppID.'&secret='.$AppSecret;
        //curl
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,$url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_GET, 1);
        $result = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($result,true);
        return $result['access_token'];
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
}

?>