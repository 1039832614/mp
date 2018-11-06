<?php 
namespace app\cb\controller;
use app\base\controller\Bby;
use think\Db;
use Pay\Wx;
use Msg\Sms;
use Epay\BbyEpay;
use Config;
use think\facade\Log;
/**
* 够买会员卡
*/
class Member extends Bby
{
	

    /**
     * 进程初始化
     */
    function initialize()
    {
        $this->wx = new Wx();
    }


	/**
	 * 购买会员
	 * @return [type] [description]
	 */
	public function memberpay()
	{
		// 获取用户uid  收件人姓名man  联系电话phone  address 省市县  details详细地址  市级id
		$data = input('post.');
        
        $validate = validate('Address');
        if($validate->check($data)){
            // 根据用户id获取用户姓名（u_user表用户完善信息的姓名）
            $name_phone = Db::table('u_user')->where('id',$data['uid'])->field('name,phone,open_id')->find();

            $time = date('Y-m-d H:i:s',strtotime("+1 years",time()));

            $arr = [
                'uid' => $data['uid'],
                'name'=> $name_phone['name'],
                'phone'=>$name_phone['phone'],
                'end_time'=>$time,
                'trade_no'=>$this->wx->createOrder(),
                'm_order'=>$this->createCardNum(),
            ];
            $memberId = Db::table('u_member_table')->insertGetId($arr);
            if($memberId){
                // echo $memberId;
                $result = $this->weixinapp($memberId,$name_phone['open_id'],$data);
                $result['trade_no'] = $arr['trade_no'];
                $result['id'] = $memberId;
                $this->result($result,1,'获取成功');
            }else{
                $this->result($result,0,'发起支付异常');
            }

        }else{
            $this->result('',0,$validate->getError());
        }
		
		
	}   


	/**
     * 微信小程序接口
     */
    public function weixinapp($memberId,$openid,$data) {  
        //统一下单接口  
        $unifiedorder = $this->unifiedorder($memberId,$openid,$data); 
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
    private function unifiedorder($memberId,$openid,$data) {  
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';  
        // 进行数据查询
        $arr = Db::table('u_member_table')->field('id,name,trade_no,price')->where('id',$memberId)->find();

        $parameters = array(  
            'appid' => Config::get('appid'),
            'mch_id' => Config::get('mch_id'),
            'nonce_str' => $this->wx->getNonceStr(), 
            'body' => $arr['name'].'邦保养会员',  
            'total_fee' => $arr['price']*100, //2018-10-27线上
            // 'total_fee' => 1,
            'openid' => $openid,
            'out_trade_no'=> $arr['trade_no'], 
            'spbill_create_ip' => '127.0.0.1', 
            'notify_url' => 'https://mp.ctbls.com/cb/Member/notify', 
            'trade_type' => 'JSAPI',
            'attach' => 'cid='.$memberId.'&man='.$data['man'].'&phone='.$data['phone'].'&address='.$data['address'].'&details='.$data['details'].'&area='.$data['area'].'&uid='.$data['uid'],
        );  
        //统一下单签名  
        $parameters['sign'] = $this->wx->getSign($parameters);  
        $xmlData = $this->wx->arrayToXml($parameters);  
        // print_r($xmlData);exit;
        $return = $this->wx->xmlToArray($this->wx->postXmlCurl($xmlData, $url, 60)); 
        // print_r($return);exit;
        return $return;  
    }  


    /**
     * 生成唯一会员号
     */
    private function createCardNum(){
        static $i = -1;$i ++ ;
        $a = substr(date('YmdHis'), -12,12);
        $b = sprintf ("%02d", $i);
        if ($b >= 100){
            $a += $b;
            $b = substr($b, -2,2);
        }
        return $a.$b;
    }


    /**
	 * 微信支付回调
	 */
	public function notify(){
		
		$xml =  file_get_contents("php://input");
		$data = $this->wx->xmlToArray($xml);
		$data_sign = $data['sign'];
		unset($data['sign']);
		// Db::startTrans();
		$sign = $this->wx->getSign($data);
			// 判断签名是否正确  判断支付状态
			if (($sign===$data_sign) && ($data['return_code']=='SUCCESS') && ($data['result_code']=='SUCCESS')){
					$result = $data;
					$attach = $this->wx->getStrVal($data['attach']);
					//修改会员状态
					// Db::table('u_member_table')->where('id',$attach['memberId'])->update(['transaction_id'=>$result('transaction_id'),'pay_status'=>1]);
                    // xjm 2018.10.27 10:37 新增
                    Db::table('u_member_table')
                    ->where('id',$attach['cid'])
                    ->update([
                        'transaction_id' => $result['transaction_id'],
                        'pay_status' => 1
                    ]); 
                    // $arr = [
                    //     'aid'=>365,
                    //     'uid'=>$athach['uid'],
                    //     'man'=>$athach['man'],
                    //     'phone'=>$athach['phone'],
                    //     'address'=>$athach['address'],
                    //     'details'=>$athach['details'],
                    //     'area'=>$athach['area'],  // 地区id
                    //     'member'=>$memberId,

                    // ];
                    // xjm 2018.10.27 10:40新增
                    $arr = [
                        'aid'     => 365,
                        'uid'     => $attach['uid'],
                        'man'     => $attach['man'],
                        'phone'   => $attach['phone'],
                        'address' => $attach['address'],
                        'details' => $attach['details'],
                        'area'    => $attach['area'],  // 地区id
                        'member'  => $attach['cid'],

                    ];
					// 入库地址信息
					Db::table('u_winner')->insert($arr);
				}else{
			
					$result = false;
				}
			
			// 返回状态给微信服务器
			echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
			// exit;
			return $result;
	
	}


     /**
     * 省
     * @return [type] [description]
     */
    public function pro()
    {
        $data = Db::table('co_china_data')->where('pid',1)->field('id,name')->select();
        $this->result($data);
    }


    /**
     * 市
     * @return [type] [description]
     */
    public function city()
    {
        $city = input('post.pro');
        $data = Db::table('co_china_data')->where('pid',$city)->field('id,name')->select();
        $this->result($data);
    }


    /**
     * 县
     * @return [type] [description]
     */
    public function county()
    {
        $city = input('post.city');
        $data = Db::table('co_china_data')->where('pid',$city)->field('id,name')->select();
        $this->result($data);
    }



}