<?php 
namespace app\cb\controller;
use app\base\controller\Bby;
use think\Db;
use Pay\Wx;
use Epay\Epay;
use Config;

/**
* 邦保养操作
*/
class Bang extends Bby
{
	/**
	 * 进程初始化
	 */
	function initialize()
	{
		$this->wx = new Wx();
	}

	/**
	 * 获取汽车品牌
	 */
	public function getBrand()
	{
		$data = Db::table('co_car_menu')->field('id as brand_id,name,abbr')->select();
		if($data){
			$this->result( $data,1,'获取成功');
		}else{
			$this->result('',0,'获取数据异常');
		}
	}

	/**
	 * 获取汽车类型
	 */
	public function getAudi()
	{
		$bid = input('get.brand_id');
		$res = Db::table('co_car_cate')->field('type')->where('brand',$bid)->select();
		$data = array_unique(array_column($res,'type'));
		if($data){
			$this->result($data,1,'获取成功');
		}else{
			$this->result('',0,'获取数据异常');
		}
	}

	/**
	 * 获取汽车排量
	 */
	public function getDpm()
	{
		$bid = input('get.brand_id');
		$type = input('get.type');
		$res = Db::table('co_car_cate')->where('brand',$bid)->where('type',$type)->field('series')->select();
        $data = array_unique(array_column($res,'series'));
        if($data){
			$this->result($data,1,'获取成功');
		}else{
			$this->result('',0,'获取数据异常');
		}
	}

	/**
	 * 获取油品
	 */
	public function getOil()
	{
		$data = input('get.');
		$car_cate_id = 	Db::table('co_car_cate')
						->where('brand',$data['brand_id'])
						->where('type',$data['type'])
						->where('series',$data['series'])
						->value('id');
		$res = Db::table('co_bang_data')
				->where('cid',$car_cate_id)
				->field('cid,price,oil')
				->find();
		$newOil = $this->newOil($res['oil']);
		$res['oil'] = $newOil['name'];
		$res['oid'] = $newOil['id'];
		$res['km'] = $newOil['km'];
		$res['price'] = $res['price']+100;
		if($res){
			$this->result($res,1,'获取成功');
		}else{
			$this->result('',0,'获取数据异常');
		}
	}


	/**
	 * 油品升级
	 */
	public function oils()
	{
		$oid = input('get.oid');
		$data = Db::table('co_bang_cate')
				->alias('c')
				->join(['co_bang_cate_about'=>'a'],'a.bc_id = c.id','LEFT')
				->where('pid',1)
				->where('c.id','>=',$oid)
				->order('c.id asc')
				->field('c.id as oid,c.name as oil,cover')
				->select();
		// 进行加价
		$add_price = [0,50,75,125];
		if($data){
			foreach ($data as $k => $v) {
				$data[$k]['add_price'] = $add_price[$k];
			}
			$this->result($data,1,'获取成功');
		}else{
			$this->result('',0,'油品已是最高级');
		}
	}

	/**
	 * 进行油品替换
	 */
	public function newOil($oil)
	{
		$arr = ['1号','2号','3号','4号'];
		$kms = ['7500','10000','10000','10000'];
		$res = Db::table('co_bang_cate')->where('pid',1)->field('id,name')->select();
		foreach ($arr as $k => $v) {
			if(strpos($oil, $v) !== false){
				$name = $res[$k];
			}
			$km = $kms[$k];
		}
		$name['km'] = $km;
		return $name;
	}

	/**
	 * 获取油品详情
	 */
	public function oilDetail()
	{
		$oid = input('get.oid');
		$data = Db::table('co_bang_cate_about')->where('bc_id',$oid)->field('name,cover,about')->find();
		if($data){
			$this->result($data,1,'获取成功');
		}else{
			$this->result($data,0,'暂无数据');
		}
	}

	/**
	 * 微信支付
	 */
	public function pay() { 
		$data = input('post.');
		// 系统订单号
	    $data['trade_no'] = $this->wx->createOrder();
	    // 剩余次数与购卡次数相同
	    $data['remain_times'] = $data['card_type'];
		// 由于attach字符长度限制，先做入库处理
		$openid = $data['openid'];
		// 获取工时费
		$charge = Db::table('cs_shop')
				  ->alias('s')
				  ->join(['ca_agent'=>'a'],'s.aid = a.aid')
				  ->join(['ca_agent_set' => 'as'],'a.aid = as.aid')
				  ->value('shop_hours');
		$data['hour_charge'] = $data['card_price']*$charge/100;
		// 删除掉data中的openid防止入库错误
		unset($data['openid']);
		// 生成唯一卡号
		$data['card_number'] = $this->createCardNum();
    	$lastId = Db::table('u_card')->insertGetId($data);
    	if($lastId){
	        $result = $this->weixinapp($lastId,$openid);
	        $result['trade_no'] = $data['trade_no'];
	        $result['cid'] = $lastId;
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

    /**
     * 生成唯一卡号
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


    //统一下单接口  
    private function unifiedorder($lastId,$openid) {  
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';  
        // 进行数据查询
        $data = Db::table('u_card')->field('sid,cate_name,trade_no,card_price,share_uid')->where('id',$lastId)->find();
        $parameters = array(  
            'appid' => Config::get('appid'),
            'mch_id' => Config::get('mch_id'),
            'nonce_str' => $this->wx->getNonceStr(), 
            'body' => $data['cate_name'].'邦保养卡',  
            'total_fee' => $data['card_price']*100, 
            'openid' => $openid,
            'out_trade_no'=> $data['trade_no'], 
            'spbill_create_ip' => '127.0.0.1', 
            'notify_url' => Config::get('notify_url'), 
            'trade_type' => 'JSAPI',
            'attach' => 'cid='.$lastId.'&sid='.$data['sid'].'&total='.$data['card_price'].'&share_uid='.$data['share_uid']
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
			// 更新购卡状态
			Db::table('u_card')->where('id',$attach['cid'])->update(['transaction_id'=>$attach['transaction_id'],'pay_status'=>1]);
			// 店铺售卡数增加
			Db::table('cs_shop')->where('id',$attach['sid'])->setInc('card_sale_num');
			// 运营商获取利润
			$adata = Db::table('cs_shop')
					  ->alias('s')
					  ->join(['ca_agent_set'=>'a'],'s.aid = a.aid')	
					  ->field('s.aid,profit')
					  ->where('s.id',$attach['sid'])
					  ->find();
			$money = $attach['total']*$adata['profit']/100;
			$ca_inc = Db::table('ca_agent')->where('aid',$adata['aid'])->inc('balance',$money)->inc('sale_card')->update();
			// 分享车主获得奖励
			// $this->shareReward($attach['cid'],$attach['share_uid']);
		}else{
			$result = false;
		}
		// 返回状态给微信服务器
		($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
		return $result;
	}


	/**
	 * 车主获得奖励
	 */
	public function shareReward($cid,$uid)
	{
		if($uid == 0){
			
		}else{
			// 获取分享车主的openid
			$openid = Db::table('u_user')->where('id',$uid)->value('open_id');
			// 获取卡的总金额和购买人姓名和电话
			$res = Db::table('u_card')
					 ->alias('c')
					 ->join(['u_user'=>'u'],'c.uid = u.id')
					 ->field('card_price,u.name,u.phone')
					 ->where('c.id',$cid)
					 ->find();
			// 获取系统设定的奖励金，目前暂定20，系统修改时请去掉此处代码注释，记得检查where中的id是否对应分享奖励
			// $reward = Db::table('am_system_setup')->where('id',5)->value('money');
			// 构建入库数据
			$data = [
				'uid'	=>	$uid,
				'cid'	=>	$cid,
				'buyer'	=>	$res['name'],
				'reward'	=> 20,
				'buyer_phone'	=> $res['phone'],
				'money'	=> $res['card_price']
			];
			// 进行入库操作
			$add = Db::table('u_share_income')->insert($data);
			// 入库后进行奖励
			if($add){
				$epay = new Epay();
				$trade_no = build_only_sn();
				$epay->dibs($trade_no,$openid,1*100,'车主分享奖励');
			}
		}
	}

}
