<?php
/**
 * @desc: bc alipay jsapi
 * 1.oauth认证获取user_id，参考资料: https://doc.open.alipay.com/docs/doc.htm?treeId=220&articleId=105337&docType=1#s5
 * 2.支付宝jsapi调起支付，参考资料：
 *    https://myjsapi.alipay.com/jsapi/native/trade-pay.html
 *    https://doc.open.alipay.com/docs/doc.htm?&docType=1&articleId=105591
 *
 * @author: jason
 * @since:  2017-09-12 16:30
 */
define("AOP_SDK_WORK_DIR", "/tmp/");

require_once 'lib/aop/AopClient.php';
require_once 'lib/aop/request/AlipaySystemOauthTokenRequest.php';
require_once 'lib/aop/request/AlipayTradeCreateRequest.php';
//require_once 'lib/aop/request/AlipayUserUserinfoShareRequest.php';

header("Content-type: text/html; charset=utf-8");
$alipay_app_id = 'xxxxxx'; //支付宝开放平台创建应用的APP ID

if(!isset($_GET['auth_code'])){
    $redirect_uri = urlencode('http://www.xxx.com/demo/alipay.jsapi.php');
    $url = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=$alipay_app_id&scope=auth_userinfo&redirect_uri=$redirect_uri";
    header('Location:'.$url);
}else {
    try {
        $aop = new AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $alipay_app_id;
        $aop->rsaPrivateKey = 'xxxxxxx'; //创建应用配置的私钥（请填写开发者私钥去头去尾去回车，一行字符串；和验签名方式保持一致，推荐RSA2方式）
        $aop->alipayrsaPublicKey='xxxxxxx'; //创建应用配置的支付宝公钥（请填写支付宝公钥，一行字符串；和验签名方式保持一致，推荐RSA2方式）
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset = 'UTF-8';
        $aop->format = 'json';

        //获取access_token
        $request = new AlipaySystemOauthTokenRequest ();
        $request->setGrantType("authorization_code");
        $request->setCode($_GET['auth_code']);
        $result = $aop->execute($request);

        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        if (isset($result->$responseNode->user_id)) {
            $user_id = $result->$responseNode->user_id;
        } else {
            echo '<pre>';
            print_r($result);die;
        }

        //获取use info
        /*$access_token = $result->$responseNode->access_token;
        $request = new AlipayUserUserinfoShareRequest ();
        $result = $aop->execute ($request, $access_token);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        if(isset($result->$responseNode)){
            echo '<pre>';
            print_r($result->$responseNode);
        } else {
            echo '<pre>';
            print_r($result);die;
        }*/

        $data = array();
        $data["out_trade_no"] = "phpdemo" . time() * 1000;
        $data["total_amount"] = 0.01; //单位元
        $data["buyer_id"] = 'xxxxxxxxxx';
        $data["subject"] = '支付测试';
        $data["body"] = "测试";
        //$data["buyer_id"] = "2088102146225135";
        //$data["goods_detail"] = array(
        //    'goods_id' => 'apple-01',
        //    'goods_name' => 'Iphone6',
        //    'quantity' => '',
        //    'price' => 1.00, //单位元
        //    'goods_category' => 1,
        //    'body' => '',
        //    'show_url' => ''
        //);
        //$data["extend_params"] = (object)array('sys_service_provider_id' => '2088511833207846 ');
        //$data["timeout_express "] = '90m';
        $request = new AlipayTradeCreateRequest ();
        $request->setNotifyUrl('http://example.com/notify.php');
        $request->setBizContent(json_encode($data));
        $result = $aop->execute($request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        if (isset($result->$responseNode->trade_no)) {
            $trade_no = $result->$responseNode->trade_no ;
        } else {
            echo '<pre>';
            print_r($result);die;
        }
    } catch (Exception $e) {
        exit($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>BeeCloud支付宝JSAPI</title>
</head>
<script type="text/javascript">
    //调用支付宝JS api 支付
    function jsApiCall() {
        AlipayJSBridge.call("tradePay",{
            tradeNO: '<?php echo $trade_no;?>'
        }, function(data){
            /**
             *  名称              类型                      描述
             *  resultCode      string               支付结果
             ‘9000’: 订单支付成功;
             ‘8000’: 正在处理中;
             ‘4000’: 订单支付失败;
             ‘6001’: 用户中途取消;
             ‘6002’: 网络连接出错
             ‘99’: 用户点击忘记密码快捷界面退出(only iOS since 9.5)
             *  callbackUrl     bool                 交易成功后应跳转到的url；一般为空, 除非交易有特殊配置
             *  memo            bool                 收银台服务端返回的memo字符串
             *  result          bool                 收银台服务端返回的result字符串
             */
            //alert(JSON.stringify(data));
            if ('9000' == data.resultCode) {
                alert("支付成功");
            }else if ('6001' == data.resultCode) {
                alert("取消支付");
            }else if ('4000' == data.resultCode) {
                alert("支付失败");
            }else if ('8000' == data.resultCode) {
                alert("正在处理中...");
            }
            // 通过jsapi关闭当前窗口
            AlipayJSBridge.call('closeWebview');
        });
    }
    function callpay() {
        if (typeof AlipayJSBridge == "undefined"){
            if( document.addEventListener ){
                document.addEventListener('AlipayJSBridgeReady', jsApiCall, false);
            }else if (document.attachEvent){
                document.attachEvent('AlipayJSBridgeReady', jsApiCall);
            }
        }else{
            jsApiCall();
        }
    }
</script>
<body onload="callpay();"> </body>
</html>