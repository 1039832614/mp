<?php 
namespace app\cb\controller;
use app\base\controller\Bby;
use think\Db;
use Exg\Exg;
use Msg\Sms;
use Epay\BbyEpay;
use Config;
/**
* 转盘抽奖
*/
class Turntable extends Bby
{
	/**
	 * 点击抽奖
	 */
	public function get_gift(){ 
		$uid = input('post.uid');
		// 检测是否符合领取条件
		$card = Db::table('u_user')
				->where('id',$uid)
				->where('lottery','>','0')
				->find();
		if(!$card){
			$this->result('',0,'您没有抽奖次数！');
		}else{
			//拼装奖项数组 
		    // 奖项id，奖品，概率
		   	$prize_arr = Db::table('u_prize')
		   				->field('id,name,prob,image,price')
		   				->order('id desc')
		   				->limit('8')
		   				->select();
		   	$i = 0;
			 foreach ($prize_arr as $key => $value) {
			 		$prize_arr[$key]['num'] = $i;
			 		$i++;
			}
		    foreach ($prize_arr as $key => $val) {
		     	$arr[$val['num']] = $val['prob'];//概率数组  
		    }
		    $rid = $this->get_rand($arr);  //根据概率获取奖项id 
		    $res=[        	           //中奖项 
		    	'id'=> $prize_arr[$rid]['id'],
		    	'name' => $prize_arr[$rid]['name'],
		    	'image' => $prize_arr[$rid]['image'],
		    	'price' => $prize_arr[$rid]['price'],
		    	'prob'  => $prize_arr[$rid]['prob'],
		    	'num'  => $prize_arr[$rid]['num'],
		    ];
		    unset($prize_arr[$rid-1]); //将中奖项从数组中剔除，剩下未中奖项  
		    shuffle($prize_arr); //打乱数组顺序  
		    $result=[
		    	'id'=> $res['id'],
		    	'name' => $res['name'],
		    	'image' => $res['image'],
		    	'price' => $res['price'],
		    	'prob'  => $res['prob'],
		    	'num'  => $res['num'],
		    ];
		    if($result){
				$this->result($result,1,'成功抽奖');
			}else{
				$this->result('',0,'抽奖失败');
			}
		}
	}
	/**
	 * 点击领取
	 * @return [type] [description]
	 */
	public function choice()
	{
		$data = input('post.');
		// 获取当前用户的抽奖机会
		$count = Db::table('u_user')
				->where('id',$data['uid'])
				->value('lottery');
		if($count > 0) {
			if(!empty(cookie('time'.$data['uid']))){
				$this->result('',5,'请勿重复领取!');
			}else{
				cookie('time'.$data['uid'],time(),10);
				Db::startTrans();
				// 检测是否符合领取条件
				$card = Db::table('u_card')
						->field('id,sid,card_number')
						->order('id desc')
						->where('uid',$data['uid'])
						->find();
				$prize = Db::table('u_prize')
						->where('id',$data['id'])
						->field('name,draw,price')
						->find();
				if(empty(cookie('trade_no'.$data['uid']))){
					$trade_no = build_only_sn();
					cookie('trade_no'.$data['uid'],$trade_no,10);
				} else {
					$trade_no = cookie('trade_no'.$data['uid']);
				}
				// 构建插入数据
				if($card){
					$arr = [
						'uid'         => $data['uid'],      //用户id
						'cid'         => $card['id'],       //购卡id
						'sid'	      => $card['sid'],      //所在店铺
						'gid'	      => $data['id'],       //赠品id
						'gift_name'   => $prize['name'],    //赠品名称
						'status'      => 1,                 //未激活状态
						'card_number' => $card['card_number'],
						'trade_no'    => $trade_no
					];
				}else{
					$arr = [
						'uid'         => $data['uid'],   //用户id
						'cid'         => 0,              //购卡id
						'sid'	      => 0,              //所在店铺
						'gid'	      => $data['id'],    //赠品id
						'gift_name'	  => $prize['name'], //赠品名称
						'status'      => 1,              //未激活状态
						'card_number' => '',
						'trade_no'    => $trade_no
					];
				}
				
				if($prize['name'] =='光波炉'){
					$arr['excode']  = build_only_sn();   
					$arr['gcate']   = '1'; //赠品类型
				}else{
					$arr['excode'] = '';
					$arr['gcate']  = '2';
				}
				// 进行入库操作
				$list = Db::table('cs_gift')
							->insert($arr);
				//减少抽奖次数
				$dec_lo = Db::table('u_user')
							->where('id',$data['uid'])
							->setDec('lottery');
				//查询红包的id
				$price = Db::table('u_prize')
						->field('id')
						->where('name','红包')
						->select();
				foreach ($price as $key => $value) {
					$arrs[] = $value['id'];
				}
				if($list !== false && $dec_lo !== false){
					
					if(in_array($data['id'], $arrs)){
						//红包支付
						//判断当前用户点击领取的订单号是否在cookie中，下次必须在10秒以后才发放红包
						if(empty(cookie('if_pay'.$data['uid']))){
							Db::commit();
							//将订单号，存入cookie，时间为10秒
							cookie('if_pay'.$data['uid'],$trade_no,10);
							$this->money($trade_no,$prize['price'],$data['id'],$data['uid']);
						}
					}
					//是否到店领取
					if($prize['draw'] == 1){
						Db::commit();
						$this->result('',3,'领取礼品成功，成功邀请好友购卡即可激活兑换码，到店领取!');  
					}else{
						Db::commit();
						$this->result($arr['gid'],2,'领取礼品成功!');  //去填写地址
					}
				}else{
					Db::rollback();
					$this->result('',0,'领取失败'); 
				}
			}
		} else {
			$this->result('',0,'您无抽奖机会');
		}
		
	}
	
	  //计算中奖概率
	public function get_rand($proArr) {  
	   	$result = '';  
	   	//概率数组的总概率精度  
	   	$proSum = array_sum($proArr);
	  	//概率数组循环  
	   	foreach ($proArr as $key => $proCur) {  
		    $randNum = mt_rand(1, $proSum); //返回随机整数 
		    	if ($randNum <= $proCur) {  
		     		$result = $key;  
		     		break;  
		   		} else {  
		     		$proSum -= $proCur;  
	    		}  
	   }
	   unset ($proArr);  
	   return $result;  
	}
	/**
	 * 物品列表
	 */	
	public function index(){
		$uid = input('get.uid');
		$list = Db::table('u_prize')
				->field('id,name,image,price')
				->order('id desc')
	   			->limit('8')
	   			->select();
		
		$i = 0;
		 foreach ($list as $key => $value) {
		 		$list[$key]['num'] = $i;
		 		$i++;
		}
		$list['time'] = Db::table('u_user')->where('id',$uid)->value('lottery');
		if($list){
			$this->result($list,1,'获取成功');
		}else{
			$this->result('',0,'获取失败');
		}
	}
	/**
	 * 物品详情
	 */
	public function details(){
		$id = input('get.id');
		$list = Db::table('u_prize')
				->field('id,name,image,content,price')
				->where('id',$id)
				->find(); 
		if($list){
			$this->result($list,1,'获取成功');
		}else{
			$this->result('',0,'获取失败');
		}
	}
	/**
	 * 收货地址
	 */
	public function address(){
		//省市县，详细地址，收货人，手机号,用户id,赠品id
		$data = input('post.');
		// 实例化验证
		$validate = validate('Address');
		if($validate->check($data)){
			//市级id
			$data['area'] = Db::table('co_china_data')->where('name',$data['area'])->value('id');
			// 开启事务
			Db::startTrans();
			$list = Db::table('u_winner')->insert($data);
			if(!$list){
				Db::rollback();
				$this->result('',0,'领取失败');
			}
			$max_id = Db::table('cs_gift')
						->where('uid',$data['uid'])
						->order('id desc')
						->limit(1)
						->value('id');
			$arr = Db::table('cs_gift g')
					->join('u_winner w','g.gid = w.aid')
					->where('g.uid',$data['uid'])
					->where('g.id',$max_id)
					->update(['g.status'=>'2','g.ex_time'=>time()]);
			if($arr){
				Db::commit();
				$this->result('',1,'领取礼品成功,七天邮寄到家!');
			}else{
				Db::rollback();
				$this->result('',0,'领取失败');
			}
		}else{
			$this->result('',0,$validate->getError());
		}
	}	
	/**
	 * 所有中奖信息 0903 10:46 xjm
	 */
	public function winner_list(){
		$list = Db::table('cs_gift g')
				->join('u_user u','g.uid = u.id')
				->join('u_prize p','g.gid = p.id')
				->field('u.name as man,g.create_time,p.name as gift_name,p.price')
				->order('g.id desc')
				->limit(10)
				->select();
		if($list){
			$this->result($list,1,'获取成功');
		}else{
			$this->result('',0,'获取失败');
		}
	}
	/**
	 * 发放红包
	 */
	public function money($trade_no,$money,$id,$uid){
		$openid = Db::table('u_user')
					->where('id',$uid)
					->value('open_id');
		$epay = new BbyEpay();
		$epay->dibs($trade_no,$openid,1*100,'幸运大转盘获得金额');//测试金额
		// $epay->dibs($trade_no,$openid,$money*100,'幸运大转盘获得金额');
		$max_id = Db::table('cs_gift')
						->where('uid',$uid)
						->order('id desc')
						->limit(1)
						->value('id'); 
		$arr = Db::table('cs_gift')
				->where('uid',$uid)
				->where('id',$max_id)
				->update(['status'=>2,'ex_time'=>time()]);
		$this->result('',1,'提交成功,耐心等待24小时内红包到微信余额');
	}




}