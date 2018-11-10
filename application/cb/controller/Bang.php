<?php 
namespace app\cb\controller;
use app\base\controller\Bby;
use think\Db;
use Pay\Wx;
use Msg\Sms;
use Epay\BbyEpay;
use Config;
use think\facade\Log;
use think\facade\Cache;
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
     * 会员剩余次数
     * 
     */
    public function Vipsurplus()
    {
    	$uid = input('get.uid')?:die('缺少uid');
    	$sum = DB::table('u_member_table')
    	       ->where([
    	       	        'uid' => $uid,
    	       	        'pay_status' => 1,
    	                ])
    	       ->sum('pay_time');
    	  
    	$this->result($sum?:0, 1, '会员剩余次数');
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
		$res['price'] = $res['price'];
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
	 * 判断用户的车牌号 或者车类型有无重复
	 * @return [type] [description]
	 */
	public function plate()
	{
		$uid = input('post.uid');
		$car_cate_id = input('post.car_cate_id');
		$plate = input('post.plate','','strtoupper');

		if(!$uid || !$car_cate_id || !$plate){
			$this->result('',0,'缺少必要的参数');
		}
		// 判断该车牌和车型是否已被注册
		$user_card = Db::table('u_card')->where('plate',$plate)->field('car_cate_id,uid')->find();
		// print_r($user_card);exit;
		
		// 判断该用户之前是否有购买过邦保养卡
		// 根据用户id 获取之前用户购买的邦保养卡
		if(!empty($user_card)){
			if($user_card['uid'] != $uid){
				$this->result('',0,'该车牌已被其他车主绑定');
			}

			if($user_card['car_cate_id'] != $car_cate_id){
				$this->result('',0,'该车牌已绑定其他车型');
			} 
		}

		// 查看车服管家是否有人完善了该车牌的信息
		$car_plate = Db::table('cb_user bu')
					->join('u_user uu','bu.unionId = uu.unionId')
					->where('bu.plate',$plate)
					->field('bu.car_cate_id,uu.id')
					->find();

		// 判断该用户之前是否有购买过邦保养卡
		// 根据用户id 获取之前用户购买的邦保养卡
		if(!empty($car_plate)){
			if($car_plate['id'] != $uid){
				$this->result('',0,'该车牌已被其他车主绑定');
			}

			if($car_cate_id != $car_plate['car_cate_id']){
				$this->result('',0,'该车牌已绑定其他车型');
			} 
		}

		$this->result('',1,'可正常购买');
	}




	/**
	 * 微信支付
	 */
	public function pay() { 
		Log::record('错误信息');
		Log::save();
		$data = input('post.');
		// 车牌字母大写
		$data['plate'] = input('post.plate','','strtoupper');
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

		if($data['card_type'] == 1){
			// 一次卡工时费
			$data['hour_charge'] = ceil(($data['card_price']*4)*$charge/100);
		}else{
			// 四次卡工时费
			$data['hour_charge'] = ceil($data['card_price']*$charge/100);
		}
		
		// 删除掉data中的openid防止入库错误
		unset($data['openid']);
		// 生成唯一卡号
		$data['card_number'] = $this->createCardNum();
		// 判断用户是会员加购卡一起支付 1为一起支付   0为单一支付
		if($data['close'] !== 1){

			// 计算卡的价格入库
			// $data['card_price'] = $data['card_price'] - 365;
			$data['card_price'] = bcsub($data['card_price'] , 365, 2);
			// 根据用户id查询用户姓名电话
			$user_data = Db::table('u_user')->where('id',$data['uid'])->field('name,phone')->find();
			// 入库会员表
			$arr = [
				'uid'=>$data['uid'],
				'name'=>$user_data['name'],
				'phone'=>$user_data['phone'],
				'end_time'=>date('Y-m-d H:i:s',strtotime("+1 years",time())),
				'm_order'=>$this->createCardNum(),
				'trade_no'=>$this->wx->createOrder(),
				'member_table_status'=>1,
			];
			
			$memberId = Db::table('u_member_table')->insertGetId($arr);
			// 入库地址表// 获取用户uid  收件人姓名man  联系电话phone  address 省市县  details详细地址  市级id
			$member = [
				'uid'=>$data['uid'],
				'man'=>$data['close']['man'],
				'phone'=>$data['close']['phone'],
				'address'=>$data['close']['address'],
				'details'=>$data['close']['details'],
				// 'aid'=>$data['close']['aid'],
				//状态默认为已发货   如果用户没有付款成功也不会影响到总后台发货列表数据
				'status'=>2,
				'member'=>$memberId,
				// 下次小程序上传代码时修改
				'area'=>$data['close']['area'],
			];
            
			Db::table('u_winner')->insert($member);
		}else{
			$memberId = 0;
		}
		unset($data['close']);

    	$lastId = Db::table('u_card')->insertGetId($data);
    	if($lastId){
    
	        $result = $this->weixinapp($lastId,$openid,$memberId);
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
    public function weixinapp($lastId,$openid,$memberId) {  
        //统一下单接口  
        $unifiedorder = $this->unifiedorder($lastId,$openid,$memberId); 
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
    private function unifiedorder($lastId,$openid,$memberId) {  
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder'; 

        // 进行数据查询
        $data = Db::table('u_card')->field('sid,cate_name,trade_no,card_price,share_uid')->where('id',$lastId)->find();


        if($memberId == 0){
        	// 10.24 修改 price 为 card_price
        	// $card_price = $data['price']*100;
        	$data['card_price'] = $data['card_price']*100;
        }else{
        	// $card_price = ($data['price']+365)*100;
        	$data['card_price'] = bcadd($data['card_price'], 365, 2)* 100;
        }

        $parameters = array(  
            'appid' => Config::get('appid'),
            'mch_id' => Config::get('mch_id'),
            'nonce_str' => $this->wx->getNonceStr(), 
            'body' => $data['cate_name'].'邦保养卡',  
            'total_fee' => $data['card_price'],   // 10.24 修改金额
            'openid' => $openid,
            'out_trade_no'=> $data['trade_no'], 
            'spbill_create_ip' => '127.0.0.1', 
            'notify_url' => Config::get('notify_url'), 
            'trade_type' => 'JSAPI',
            'attach' => 'cid='.$lastId.'&sid='.$data['sid'].'&total='.$data['card_price'].'&share_uid='.$data['share_uid'].'&memberId='.$memberId,
        );  
        //统一下单签名  
        $parameters['sign'] = $this->wx->getSign($parameters);

        $xmlData = $this->wx->arrayToXml($parameters); 

        $return = $this->wx->xmlToArray($this->wx->postXmlCurl($xmlData, $url, 60)); 
        // print_r($return);exit;
        return $return;  
    }  


	/**
	 * 微信支付回调
	 */
	public function notify(){
		try {

			$xml =  file_get_contents("php://input");
			$data = $this->wx->xmlToArray($xml);
			$data_sign = $data['sign'];
			unset($data['sign']);
			// Db::startTrans();
			$sign = $this->wx->getSign($data);
				// 判断签名是否正确  判断支付状态
				if (($sign===$data_sign) && ($data['return_code']=='SUCCESS') && ($data['result_code']=='SUCCESS')){
						$attach = $this->wx->getStrVal($data['attach']);
						if(time() - Cache::get('card_time'.$attach['cid']) < 3600){
							exit;
							// 返回状态给微信服务器
							echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
							// exit;
							return $result;
						}
						// 
						Cache::set('card_time'.$attach['cid'],time());
						$result = $data;
						

						// 更新购卡状态
						Db::table('u_card')->where('id',$attach['cid'])->update(['transaction_id'=>$result['transaction_id'],'pay_status'=>1,'card_reward'=>10]);
						// 获取用户的卡类型
						$card_type = Db::table('u_card')->where('id',$attach['cid'])->field('card_type,card_price')->find();

						if($card_type['card_type'] == 1){
							Db::table('cs_shop')->where('id',$attach['sid'])->inc('card_sale_num')->inc('card_month')->update();
						}else{
							// 店铺售卡数增加
							Db::table('cs_shop')->where('id',$attach['sid'])->inc('card_sale_num')->inc('balance',10)->inc('card_month')->update();
						}
						
						//获取当前用户的uid xjm 2018.10.27
						$uid = Db::table('u_card')
								->where('id',$attach['cid'])
								->value('uid');
						//查询此用户是否是会员 xjm 2018.10.27
						$count = Db::table('u_member_table')
								->where('uid',$uid)
								->where('pay_time','>',0)
								->where('end_time','>',date('Y-m-d H:i:s'))
								->order('id desc')
								->limit(1)
								->count();
						//xjm 2018.10.27
						if($count > 0){
							//减去用户享受折扣价次数
							Db::table('u_member_table')
							->where('uid',$uid)
							->where('pay_time','>',0)
							->where('end_time','>',date('Y-m-d H:i:s'))
							->order('id desc')
							->setDec('pay_time');
						}
						//修改会员状态  改改收货地址状态
						if($attach['memberId'] != 0){
							Db::table('u_member_table')->where('id',$attach['memberId'])->update(['transaction_id'=>$result['transaction_id'],'pay_status'=>1]);

							Db::table('u_winner')->where('member',$attach['memberId'])->update(['status'=>0]);
						}
						// 分享车主获得奖励
						$this->shareReward($attach['cid'],$attach['share_uid']);

						// 给运营商服务经理市级代理售卡奖励
							
						$this->agentBlance($attach['sid'],$uid,$attach['cid'],$card_type['card_price'],$result);
						// 返回状态给微信服务器
						echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
						// exit;
						return $result;
					}else{
				
						$result = false;
					}
				
				// 返回状态给微信服务器
				echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
				// exit;
				return $result;
			} catch (Exception $e) {
			// 返回状态给微信服务器
			echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
			return $result;
		}
	
	}

	/**
	 * 	运营商市级代理售卡所得金额
	 * @param  [type] $share_uid [description]
	 * @return [type]            [description]
	 */
	public function agentBlance($sid,$uid,$cid,$total,$result)
	{
		// 查询用户本次购卡id
		$cid = Db::table('u_card')->where(['sid'=>$sid,'uid'=>$uid])->order('id desc')->limit(1)->value('id');
		// print_r($cid);exit;
		// 获取卡的信息
		$card_info = Db::table('u_card')->where('id',$cid)->field('car_cate_id,uid,trade_no')->find();
		// print_r($card_info);exit;
		//转盘抽奖  取消转发得抽奖次数
		$type = Db::table('u_user u')
				->join('u_card c','c.uid=u.id')
				->where('u.id',$card_info['uid']) 
				->where('c.id',$cid)
				->where('c.card_type',4)
				->inc('u.lottery')->update();

        $agent_set = Db::table('cs_shop')->where('id',$sid)->value('aid');
        $profit = Db::table('ca_agent_set')->where('aid',$agent_set)->value('profit');
		// if(empty($profit)){
		// 	// 返回状态给微信服务器
		// 	echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
		// 	return $result;
		// }
		$money = $total*$profit/100;
		//增加运营商售卡数量
		$ca_inc = Db::table('ca_agent')->where('aid',$agent_set)->inc('balance',$money)->inc('sale_card')->update();
		// 加入运营商收入记录
		$arr_inc = [
			'car_cate_id' => $card_info['car_cate_id'],
			'odd_number' => $card_info['trade_no'],
			'amount' => $money,
			'card_amount' => $total,
			'sid' => $sid,
			'uid' => $uid,
			'aid' => $agent_set,
			'form' => 1
		];
		$ca_inc_log = Db::table('ca_income')
						->strict(false)
						->insert($arr_inc);
		// 判断该运营商是否存在
		$agent_isset = Db::table('ca_agent')->where('aid',$agent_set)->count();
		// if($agent_isset <= 0){
		// 	// 返回状态给微信服务器
		// 	echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
		// 	return $result;
		// }
		// 查看运营商是否有市级代理
		$gid = Db::table('ca_agent')->where('aid',$agent_set)->field('gid')->find();
		// exit;
		// 获取运营商所供应地区的市级id
		$city = $this->agentSm($agent_set);
		// print_r($city);exit;
		// 判断是否是服务经理，审核状态已通过，不能是取消合作，管理奖励开启
		$sm_user = Db::table('sm_area')
					->where([
						'area'=>$city,//地区id
						'audit_status'=>1,//审核状态
						'is_exits'=>1,//是否取消合作  1未取消  2取消
						'sm_type'=>1,//1服务经理  2运营总监
						'admin_raw'=>1,//1管理奖励
						'sm_status'=>2,//身份状态为加盟状态
					])
					->where('sm_mold','<>',2)
					->count();
		//判断区域内是否有运营总监
		$sm_yy = Db::table('sm_area')
				  ->alias('a')
				  ->join('co_china_data d','d.pid = a.area')
				  ->join('sm_user u','u.id = a.sm_id')
				  ->where([
				  	'd.id' => $city,//地区
				  	'a.audit_status' => 1,//审核状态
				  	'is_exits' => 1,//是否总后台直接取消合作
				  	'sm_type' => 2,//1-服务经理，2-运营总监
				  	'admin_raw' => 1
				  ])
				  ->where('sm_mold','<>',2)
				  ->order('id')
				  ->limit(1)
				  ->field('a.sm_id,a.id,u.open_id,a.sm_profit,a.area')
				  ->find();
		//判断是否有运营总监
		if($sm_yy) {
			$sm_yy_reward = $this->smYReward($sid,$agent_set,$total,$cid,$sm_yy['sm_id'],$sm_yy['sm_profit'],$sm_yy['area'],$sm_yy['open_id']);
		}


		// 加入市级代理收入记录
		// 获取市级代理收入
		if($gid['gid'] !== 0){
			$income = $this->munInc($agent_set,$total);
			$supply = [
				'carmodel' => $card_info['car_cate_id'],// 车型
				'oddnumber'=> $card_info['trade_no'],//订单号
				'income'   => floor($income['income']),//收入
				'money'	   => $total,
				'sid'	   => $sid,
				'uid'	   => $uid,
				'aid'	   => $agent_set,
				'gid'	   => $income['gid'],
				'pro'	   => $income['profit'],
			];
			// print_r($supply);exit;
			// 增加市级代理的总金额
			$res = Db::table('cg_supply')->where('gid',$income['gid'])->inc('balance',floor($income['income']))->inc('sale_card')->update();
			$su_income = Db::table('cg_income')
							->strict(false)
							->insert($supply);
			
			// return $city;die();
			if($sm_user > 0){
				// 查询该地区有无服务经理
				// 获取服务经理分佣
				$sm = $this->smReward($sid,$agent_set,$total,$cid);

				// if($ca_inc_log && $su_income && $sm == ture){
				// xjm 2018.10.27 15:28
				if($ca_inc_log && $su_income && $sm == true){
					// // $this->result('',1,'成功');
					// // 返回状态给微信服务器
					// echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
					// return $result;
					return true;
				}else{
					// $this->result('',0,'失败');
					// 返回状态给微信服务器
					// echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
					// return $result;
					return false;
				}
			}else{
				if($ca_inc_log && $su_income){
					// $this->result('',1,'成功');
					// 返回状态给微信服务器
					// echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
					// return $result;
					return true;
				}else{
					// $this->result('',0,'失败');
					// 返回状态给微信服务器
					// echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
					// return $result;
					return false;
				}
			}	
			
		}else{
			// 获取运营商所供应地区的市级id
			$city = $this->agentSm($agent_set);
			// return $city;die();
			// $sm_user = Db::table('sm_area')->where('area',$city)->count();
			if($sm_user > 0){
				// 查询该地区有无服务经理
				// 获取服务经理分佣
				$sm = $this->smReward($sid,$agent_set,$total,$cid);
				if($ca_inc_log && $sm == true){
					// $this->result('',1,'成功');
					// 返回状态给微信服务器
					// echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
					// return $result;
					return true;
				}else{
					// $this->result('',0,'失败');
					// 返回状态给微信服务器
					// echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
					// return $result;
					return false;
				}
			}else{
				if($ca_inc_log){
					// $this->result('',1,'成功');
					// 返回状态给微信服务器
					// echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
					// return $result;
					return true;
				}else{
					// $this->result('',0,'失败');
					// 返回状态给微信服务器
					// echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
					// return $result;
					return false;
				}
			}	
			
		}
		
	}

	/**
	 * 运营总监获取售卡分佣
	 * @param  [type] $sid       [维修厂id]
	 * @param  [type] $aid       [运营商id]
	 * @param  [type] $total     [卡价格]
	 * @param  [type] $cid       [卡id]
	 * @param  [type] $sm_id     [运营总监id]
	 * @param  [type] $sm_profit [分佣比例]
	 * @param  [type] $area      [地区id]
	 * @param  [type] $open_id   [运营总监的open_id]
	 * @return [type]            [description]
	 */
	public function smYReward($sid,$aid,$total,$cid,$sm_id,$sm_profit,$area,$open_id)
	{
		//获取区域id（省级的），运营总监id，open_id,分成比例,sid,运营商名称，金额，
		$data = input('post.');
		//判断当前区域内是否有投诉
		$complaint = Db::table('sm_complaint')
					 ->alias('c')
					 ->join('sm_area a','a.area = c.pro_id')
					 ->where([
					 	'a.audit_status' => 1,
					 	'c.status' => 1,
					 	'c.pro_id' => $area
					 ])
					 ->count();
		if($complaint <= 0) {
			//没有未撤回的投诉
			// 查询运营商公司名称
			$yy_company = Db::table('ca_agent')->where('aid',$aid)->value('company');
			//获取维修厂地址及审核时间
			$address = Db::table('cs_shop_set ss')
				->join('cs_shop cs','ss.sid = cs.id')
				->where('ss.sid',$sid)
				->field('province,city,county,audit_time')
				->find();
			//金额
			$money = $total*$sm_profit/100;
			$trade_no = build_only_sn();
			//构建入库数据
			$arr = [
				'sm_id' => $sm_id,
				'odd_number' => $trade_no,
				'company' => $yy_company,
				'money' => $money,
				'address' => $address['province'].$address['city'].$address['county'],
				'type' => 3,
				'cid' => $cid,
				'person_rank' => 2,//运营总监
				'sid' => $sid,
				'if_finish' => 1
			];
			$res = Db::table('sm_income')
							->strict(false)
							->insert($arr);
			if($res !== false) {
				$epay = new BbyEpay();
						// $sm_yy_reward = $epay->sm_dibs($trade_no,$open_id,$money*100,'售卡奖励分佣');
				$sm_yy_reward = $epay->sm_dibs($trade_no,$open_id,1*100,'售卡奖励分佣');
				if($sm_yy_reward!==false) {
					return true;
				} else {
					return false;
				}
			}
		} else {
			//有投诉则不给钱
			return true;
		}
	}
	/**
	 * 服务经理奖励金
	 * @param  [type] $sid   [description]
	 * @param  [type] $aid   [description]
	 * @param  [type] $total [description]
	 * @param  [type] $cid   [description]
	 * @return [type]        [description]
	 */
	public function smReward($sid,$aid,$total,$cid)
	{
		// 获取运营商所供应地区
		// 获取运营商供应地区的市级id
		$city = $this->agentSm($aid);
		// 获取省级id 
		$province = Db::table('co_china_data')->where('id',$city)->value('pid');
		// 根据供应地区获取上级服务经理id 和管理奖励
		$sm_sm = $this->smAdmin($city,1);
		// 查询该服务经理有无该运营商未撤销的投诉
		$complaint = $this->smCom($sm_sm['sm_id'],$aid,1);
		// 查询运营商公司名称
		$yy_company = Db::table('ca_agent')->where('aid',$aid)->value('company');
		//获取维修厂地址及审核时间
		$address = Db::table('cs_shop_set ss')
			->join('cs_shop cs','ss.sid = cs.id')
			->where('ss.sid',$sid)
			->field('province,city,county,audit_time')
			->find();
		//判断是否有服务经理
		if($sm_sm) {
			// 如果有则没有分佣,没有则给服务经理和运营总监转账到零钱
			if($complaint <= 0){
				//查询该维修厂是否有未撤销的投诉
				$shop_com = $this->smCom($sm_sm['sm_id'],$sid,3);
				// 如果有则没有分佣,没有则给服务经理和运营总监转账到零钱
				if($shop_com <= 0){
					// 上面以判断是否开启管理奖励
					$money = $total*$sm_sm['sm_profit']/100;
					// 服务经理进行入库操作
					$trade_no = build_only_sn();
					$arr = [
						'sm_id'=>$sm_sm['sm_id'],
						'odd_number'=>$trade_no,
						'company'=>$yy_company,
						'money'=>$money,
						'address'=>$address['province'].$address['city'].$address['county'],
						'cid'=>$cid,
						'sid'=>$sid,
					];
					$res = Db::table('sm_income')
							->strict(false)
							->insert($arr);
					// 增加服务经理余额
					Db::table('sm_user')->where('id',$sm_sm['sm_id'])->setInc('balance',$money);
					// 获取服务经理的openid
					$sm_name = $this->openid($sm_sm['sm_id']);
					// print_r($sm_name);exit;
					if($res !== false){
						$epay = new BbyEpay();
						// $sm_reward = $epay->sm_dibs($trade_no,$sm_name['open_id'],$money*100,'服务经理售卡奖励分佣');
						$sm_reward = $epay->sm_dibs($trade_no,$sm_name['open_id'],1*100,'服务经理售卡奖励分佣');
						// return $sm_reward;die();
					}
					// 通过服务经理id 及 服务经理的地区判断服务经理该地区通过的时间
					$sm_audit = Db::table('sm_area')->where(['sm_id'=>$sm_sm['sm_id'],'area'=>$city])->value('audit_time');
					//查询该维修厂是否是服务经理后开发的维修厂 维修厂审核时间小于服务经理地区审核通过的时间
					if($address['audit_time'] > $sm_audit){
						
						// 判断该维修厂是否够20辆台任务
						$card = Db::table('u_card')->where('sid',$sid)->count();
						if($card == 20){
							// 修改此维修厂为开放奖励 身份id为服务经理 服务经理id 改为已完成状态
							Db::table('sm_income')->where(['sid'=>$sid,'sm_id'=>$sm_sm['sm_id'],'person_rank'=>1])->setField('if_finish',1);
							// 服务经理余额增加
							Db::table('sm_user')->where('sm_id',$sm_sm['sm_id'])->setInc('balance',2000);
						}
					}

					// // 根据服务经理id获取运营总监id和是否开启管理奖励 和分佣比例 查询否有加入团队
					// $sm_yy = Db::table('sm_team st')
					// 	 ->join('sm_area sa','st.sm_header_id = sa.sm_id')
					// 	 ->where('st.sm_member_id','like','%'.$sm_sm['sm_id'].'%')
					// 	 ->where(['sa.audit_status'=>1,'sa.sm_type'=>2,'admin_raw'=>1,'is_exits'=>1,'area'=>$province])
					// 	 ->where('sm_mold','<>',2)
					// 	 ->field('sm_id,sm_profit')
					// 	 ->find();

					// // 判断改运营总监所管辖的该地区管理奖励是否开启 开启则给运营总监分佣
					// if(!empty($sm_yy)){
					// 	$money = $total*$sm_yy['sm_profit']/100;
					// 	// 运营总监进行入库操作
					// 	$yy_trade_no = build_only_sn();
					// 	$yy_insert = [
					// 		'sm_id'=>$sm_yy['sm_id'],
					// 		'odd_number'=>$yy_trade_no,
					// 		'company'=>$sm_name['name'],
					// 		'money'=>$money,
					// 		'address'=>$address['province'].$address['city'],
					// 		'cid'=>$cid,
					// 		'person_rank'=>2,
					// 		'sid'=>$sid,
					// 		'uuid'=>$sm_sm['sm_id'],
					// 	];
					// 	$result = Db::table('sm_income')->insert($yy_insert);
					// 	// 查询运营总监的open_id 
					// 	$sy_yy = $this->openid($sm_yy['sm_id']);
					// 	if($result!==false){
					// 		$epay = new BbyEpay();
					// 		$yy_reward = $epay->sm_dibs($yy_trade_no,$sy_yy['open_id'],1,'售卡奖励分佣');
					// 	}
					// 	if($sm_reward && $yy_reward){
					// 		return true;
					// 	}
					// }
					if($sm_reward){
						return true;
					}
					
				}	
			}
			
		}
		
		
	}

	  /**
     * 生成订单号
     */
    public function createOrder(){
        $yCode = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J');
        return $yCode[intval(date('Y')) - 2017] . strtoupper(dechex(date('m'))) . date('d') . substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
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
			//转盘抽奖 2018.8.29 孙烨兰修改
			Db::table('u_user')->where('id',$uid)->setInc('lottery');
			// 获取系统设定的奖励金，目前暂定10，系统修改时请去掉此处代码注释，记得检查where中的id是否对应分享奖励
			// $reward = Db::table('am_system_setup')->where('id',5)->value('money');
			// 构建入库数据
			$data = [
				'uid'	=>	$uid,
				'cid'	=>	$cid,
				'buyer'	=>	$res['name'],
				'reward'	=> 10,
				'buyer_phone'	=> $res['phone'],
				'money'	=> $res['card_price']
			];
			// 进行入库操作
			$add = Db::table('u_share_income')->insert($data);
			// 入库后进行奖励
			if($add){
				$epay = new BbyEpay();
				$trade_no = build_only_sn();
				$epay->dibs($trade_no,$openid,10*100,'推荐成功奖励');
			}
			// // 查询分享用户是否有待激活的兑换码
			// $ex_count = Db::table('cs_gift')->field('id,excode')->where('uid',$uid)->where('status',0)->select();
			// // 如果有待激活的兑换码则进行激活操作
			// if(count($ex_count) > 0){
			// 	// 查询u_card 是否有该用户的id
			// 	// 激活兑换码
			// 	$active_code = Db::table('cs_gift')->where('id',$ex_count[0]['id'])->setField('status',1);
			// 	// 激活成功后向分享用户发送短信提醒，并清空分享次数
			// 	if($active_code !== false){
			// 		// 发送短信通知用户
			// 		$excode = $ex_count[0]['excode'];
			// 		$phone = $res['phone'];
			// 		$sms = new Sms();
			// 		$content = "您的兑换码【{$excode}】已被激活。";
			// 		$res = $sms->send_code($mobile,$content);
			// 		// 清空share_counts
			// 		Db::table('u_user')->where('id',$uid)->setField('share_counts',0);
			// 	}
			// }
		}
	}


	/**
	 * 市级代理收入
	 * @param  [type] $aid        [运营商id]
	 * @param  [type] $card_price [售卡金额]
	 * @return [type]             [description]
	 */
	public function munInc($aid,$card_price)
	{
		// 通过维修厂id获取运营商id  查询运营商的分成比例  根据该分成比例计算 市级供应商代理
		// 查询运营商所供应地区的市级id,查询运营商所绑定的市级代
		$data = Db::table('ca_area')->where('aid',$aid)->column('area');
		// 查询运营商绑定的市级代理
		$gid = Db::table('ca_agent')->where('aid',$aid)->value('gid');
		// 查询运营商所供应地区的市id
		$city = Db::table('co_china_data')->whereIn('id',$data)->value('pid');
		//查询市级代理所供应地区的分成比例
		$pro = Db::table('cg_increase')->where(['gid'=>$gid,'area'=>$city,'audit_status'=>1])->value('pro');
		// 市级代理id大于0 则有市级代理
		if($pro){ 
			// // 获取供应商分成比例
			// $divide = Db::table('ca_agent_set')->where('aid',$aid)->value('profit');
			// // 百分之20的利润-运营商的利润 = 市级代理的利润
			// $profit = 20 - $divide;
			// // 售卡金额 * 售卡利润  = 市级代理所得金额
			// $income = $card_price*$profit/100;
			$income = $card_price*$pro/100;
		}else{
			$income = 0;
			$pro = 0;
		}

		return ['income'=>$income,'profit'=>$pro,'gid'=>$gid];
	}


	/**
	 * 查询服务经理/运营总监的 id和是否开启管理奖励
	 * @param  [type] $city    [description]
	 * @param  [type] $sm_type [description]
	 * @return [type]          [description]
	 */
	private function smAdmin($city,$sm_type)
	{
		return Db::table('sm_area')
		->where(['area'=>$city,'sm_type'=>$sm_type,'audit_status'=>1,'admin_raw'=>1,'is_exits'=>1])
		->where('sm_mold','<>',2)
		->field('sm_id,admin_raw,sm_profit')
		->find();
	}


	/**
	 * 查询服务经理是否有运营商或维修厂的投诉
	 * @param  [type] $sm_id [服务经理id]
	 * @param  [type] $id    [运营商/维修厂id]
	 * @param  [type] $type  [1运营商  3维修厂]
	 * @return [type]        [description]
	 */
	private function smCom($sm_id,$id,$type)
	{
		return Db::table('sm_complaint')
		->where(['sm_id'=>$sm_id,'uid'=>$id,'status'=>1,'type'=>$type])
		->count();
	}


	/**
	 * 获取运营商的市级id 来获取服务经理id
	 * @param  [type] $aid [运营商id]
	 * @return [type]      [description]
	 */
	private function agentSm($aid)
	{
		// 获取运营商所供应地区
		$area = Db::table('ca_area')->where('aid',$aid)->limit(1)->value('area');
		// 获取运营商供应地区的市级id
		return $city = Db::table('co_china_data')->where('id',$area)->value('pid');
		// return  implode(',',$city);
	}


	/**
	 * 获取服务经理/运营总监的名称及openid
	 * @param  [type] $sm_id [description]
	 * @return [type]        [description]
	 */
	private function openid($sm_id)
	{
		return Db::table('sm_user')
				->where('id',$sm_id)
				->field('name,open_id')
				->find();
	}

	/**
	 * 体验
	 * @return [type] [description]
	 */
	public function exper()
	{
		// 获取用户iduid、openid、车牌省份、车牌地区、车牌号、车型id、车型名称、用油类型、店铺id、能量值、用油名称、用油id
		Db::startTrans();
		$data = input('post.');
			// 查询该车牌号有无体验过一次邦保养
			$count = Db::table('u_card')
					->where(['uid'=>$data['uid'],'plate'=>$data['plate'],'card_type'=>1])
					->count();
			if($count > 0){
				$this->result('',3,'此用户已无免费体验次数');
			}
			// 购卡类型和剩余次数
			$data['remain_times'] = 1;
			$data['card_type'] = 1;
			// 能量值
			$data['card_price'] = $data['trade_no']*3;
			// 获取该汽修厂工时费
			$data['hour_charge'] = $data['card_price']*$this->shopHours($data['sid'])/100;
			// 删除openid
			unset($data['openid']);
			$data['card_number'] = $this->createCardNum();
			$data['transaction_id'] = 0;
			$data['card_reward'] = 0;
			$cid = Db::table('u_card')->insertGetId($data);
			// 获取运营商id
			$aid = Db::table('cs_shop')->where('id',$data['sid'])->value('aid');
			// 减少运营商的体验次数
			$free_times = Db::table('ca_agent')->where(['aid'=>$aid])->where('free_times','>',0)->setDec('free_times');
			if($cid){
				Db::commit();
				// 返回前端卡id      用户id
				$this->result(['cid'=>$cid,'uid'=>$data['uid']],1,'参与成功');
			}else{
				Db::rollback();
				$this->result('',0,'参与失败');
			}
		
	}

	/**
	 * 维修厂的工时费百分比
	 * @return [type] [description]
	 */
	private function shopHours($sid)
	{
		return Db::table('cs_shop cs')
				->join('ca_agent_set ca','cs.aid = ca.aid')
				->where('cs.id',$sid)
				->value('shop_hours');
	}
}
