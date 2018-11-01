<?php 
namespace app\cb\controller;
use app\base\controller\Bby;
use think\Db;
use Pay\Wx;
use Config;

/**
* 发票类
*/
class Receipt extends Bby
{
	/**
	 * 进程初始化
	 */
	function initialize()
	{
		$this->wx = new Wx();
	}

	
	/**
	 * 微信支付
	 */
	public function pay() { 
		$data = input('post.');
		$cid = Db::table('u_tax')->where('cid',$data['cid'])->count();
		if($cid > 0){
			$this->result('',2,'您已开具过发票');
		};
		// 系统订单号
	    $data['trade_no'] = $this->wx->createOrder();
		// 由于attach字符长度限制，先做入库处理
		$openid = $data['openid'];
		// 删除掉data中的openid防止入库错误
		unset($data['openid']);
    	$lastId = Db::table('u_tax')->insertGetId($data);
    	if($lastId){
	        $result = $this->weixinapp($lastId,$openid);
	        $result['trade_no'] = $data['trade_no'];
	        $this->result($result,1,'获取成功');
    	}else{
    		$this->result($result,0,'发起支付异常');
    	}
    }

	/**
     * 微信小程序接口
     */
    public function weixinapp($lastId,$openid) {  
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


    //统一下单接口  
    private function unifiedorder($lastId,$openid) {  
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';  
        // 进行数据查询
        $data = Db::table('u_tax')->field('id,fee,trade_no')->where('id',$lastId)->find();
        $parameters = array(  
            'appid' => Config::get('appid'),
            'mch_id' => Config::get('mch_id'),
            'nonce_str' => $this->wx->getNonceStr(), 
            'body' => '邦保养卡送达费',  
            'total_fee' => $data['fee']*100, 
            'openid' => $openid,
            'out_trade_no'=> $data['trade_no'], 
            'spbill_create_ip' => '127.0.0.1', 
            'notify_url' => 'https://mp.ctbls.com/cb/receipt/notify', 
            'trade_type' => 'JSAPI',
            'attach' => 'tid='.$data['id']
        );  
        //统一下单签名  
        $parameters['sign'] = $this->wx->getSign($parameters);  
        $xmlData = $this->wx->arrayToXml($parameters);  
        $return = $this->wx->xmlToArray($this->wx->postXmlCurl($xmlData, $url, 60));  
        return $return;  
    }  

    /**
	 * 微信支付回调
	 */
	public function notify(){
		$xml =  file_get_contents("php://input");
		$data = $this->wx->xmlToArray($xml);
		$data_sign = $data['sign'];
		unset($data['sign']);
		$sign = $this->wx->getSign($data);
		// 判断签名是否正确  判断支付状态
		if (($sign===$data_sign) && ($data['return_code']=='SUCCESS') && ($data['result_code']=='SUCCESS')) {
			$result = $data;
            $attach = $this->wx->getStrVal($data['attach']);
			Db::table('u_tax')->where('id',$attach['tid'])->setField('transaction_id',$data['transaction_id']);	
		}else{
			$result = false;
		}
		// 返回状态给微信服务器
		($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
		return $result;
	}

	
	
}