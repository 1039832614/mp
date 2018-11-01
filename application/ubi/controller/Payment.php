<?php 
namespace app\ubi\controller;
use app\base\controller\Ubi;
use think\Db;
use Pay\Wx;
use think\facade\Log;
use Epay\BbyEpay;
use Config;

    class Payment extends Ubi
{

    function initialize(){
//        parent::initialize();
        $this->wx = new Wx();
        $this->uid = input('post.uid');
    }

    /**
     * 微信小程序  支付代码
     */
	public function pay()
    {
        // 订单ID  用户ID 用户openid
        $data = input('post.');
        $order = Db::table('cb_privil_ser')->where('id',$data['id'])->find();
        if ($order['pay_status'] == 0 || $order['pay_status'] == 2){
            // 生成商户的系统订单号
            $trade_no = $this->wx->createOrder();
            //  更新该订单的商户自己的订单号
            Db::table('cb_privil_ser')->where('id',$data['id'])->update(['trade_no'=>$trade_no]);
            // openid  由 前台传回
            $lastId = $data['id'];
            $openid = $data['openid'];
            //  调用 微信小程序接口
            Db::commit();
            $result = $this->weixinapp($lastId,$openid);//  商户的生成的订单号
            $result['trade_no'] = $trade_no;
            //  订单ID
            $result['id'] = $data['id'];
            $this->result($result,1,'获取成功');
        }else{
            Db::rollback();
            $this->result('',0,'发起支付异常');
        }
    }
        /**
         * 微信小程序接口
         */
        public function weixinapp($lastId,$openid)
        {
            //统一下单接口
            $unifiedorder = $this->unifiedorder($lastId,$openid);
            $parameters = array(
                'appId' => Config::get('appid'), //小程序ID
                'timeStamp' => ''.time(), //时间戳
                'nonceStr' => $this->wx->getNonceStr(), //随机串
                'package' => 'prepay_id=' . $unifiedorder['prepay_id'], //数据包
                'signType' => 'MD5'//签名方式
            );
            //签名
            $parameters['paySign'] = $this->wx->getSign($parameters);
            return $parameters;
        }

        // 统一下单 接口
        public function unifiedorder($lastId,$openid)
        {
            //
            $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
            // 对订单进行查询
            $orderData = Db::table('cb_privil_ser')->field('price,trade_no')->where('id',$lastId)->find();
            $parameters = array(
                'appid' => Config::get('appid'),
                'mch_id' => Config::get('mch_id'),
                'nonce_str' => $this->wx->getNonceStr(),
                'body' => '订单支付',
                'total_fee' => $orderData['price']*100,
                'openid' => $openid,
                'out_trade_no'=> $orderData['trade_no'],
                'spbill_create_ip' => '127.0.0.1',
                'notify_url' => Config::get('notify_url'),  // 获取回调地址
                'trade_type' => 'JSAPI',
                'attach' => 'id='.$lastId.'total='.$orderData['price']  // 订单ID  订单金额
            );
            // 统一下单 签名
            $parameters['sign'] = $this->wx->getSign($parameters);
            $xmlData = $this->wx->arrayToXml($parameters);
            $return = $this->wx->xmlToArray($this->wx->postXmlCurl($xmlData,$url,60));
            return $return;
        }

    //  微信支付后回调
    public function notify()
    {
        $xml = file_get_contents("php://input");
        //  将 xml转换为数组
        $data = $this->wx->xmlToArray($xml);
        $data_sign = $data['sign'];
        unset($data['sign']);
        Log::error('错误信息');
        $sign = $this->wx->getSign($data);
        // 判断签名是否正确
        if (($sign === $data_sign) && ($data['return_code']=='SUCCESS') && ($data['return_code']=='SUCCESS')){
            $result = $data;
            $attach = $this->wx->getStrVal($data['attach']);
            //  更改 当前订单的状态
            Db::table('cb_privil_ser')
                ->where('id',$attach['id'])
                ->update(['pay_status'=>1,'transaction_id'=>$result['transaction_id'],'pay_time'=>date('Y-m-d H:i:s',time())]);
//            echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
//            return $result;
        }else{
            $result = false;
        }
        // 返回状态给微信服务器
        echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
        return $result;
    }
}