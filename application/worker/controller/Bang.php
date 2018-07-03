<?php 
namespace app\worker\controller;
use app\base\controller\Worker;
use think\Db;
use Exg\Exg;

/**
 * 技师进行邦保养操作
 */
class Bang extends Worker
{
	/**
	 * 初始化函数
	 * @return [type] [description]
	 */
	public function initialize()
	{
		parent::initialize();
		$uid = input('post.uid');
		$this->sid = Db::table('tn_user')
					->where('id',$uid)
					->value('sid');
		$this->Exg = new Exg();
	}

	/**
	 * 判断是否是认证技师	
	 * @return [type] [description]
	 */
	public function cert()
	{
		$uid = input('get.uid');
		$cert = Db::table('tn_user')
				->where('id',$uid)
				->where('cert',1)
				->find();
		if($cert){
			$this->result('',1,'已经过认证');
		} else {
			$this->result('',0,'请完善信息并联系维修厂认证');
		}
	}

	/**
	 * 技师拍照后，通过照片获取车牌号
	 * @return [type] [description]
	 */
	public function exg()
	{
		$pic = input('post.pic');
		$toPic = $this->Exg->exg($pic);
		if(!$toPic==0){
			$res = get_object_vars(json_decode($this->Exg->exg($pic)))['plates'];
		foreach ($res as $key => $value) {
				$cards[] = $value->txt; 
			}
			$this->result(['cards'=>$cards],1,'获取车牌号成功');
		} else {
			$this->result('',0,'获取车牌号失败，请重新选择照片');
		}
	}

	/**
	 * 输入车牌号后，返回这个车牌号对应的邦保养卡号 以及其他信息
	 */
	public function bang()
	{
		$data = input('post.');
		// 获取车牌号
		$plate = input('post.plate_number','','strtoupper');
		$validate = validate('PlateNumber');
		//验证车牌号是否正确
		if($validate->check($data)){
			//检测该车辆是否在当前汽修厂
			$count = Db::table('u_card')
					 ->where('sid',$this->sid)
					 ->where('plate',$plate)
					 ->count();
			if($count > 0){
				$info = $this->getCarInfo($plate);
				if(!empty($info)){
					$check = $this->checkOil($this->sid,$info['oid'],$info['litre']);
					if($check !== false){
					$this->result($info,1,'获取信息成功');
					} else {
						$this->result('',0,'该油品库存不足');
					}
				} else {
					$this->result('',0,'无可供使用的邦保养卡');
				}	
			} else {
				$this->result('',0,$plate.'不属于该汽修厂');
			}
		} else {
			$this->result('',0,$validate->getError());
		}
	}
	/**
	 * 向购买邦保养卡的人发送验证码
	 */
	public function vcode()
	{
		$phone = input('post.phone');
		$card_number = input('post.card_number');
		$code = $this->apiVerify();
		$content = "您邦保养卡号为【{$card_number}】参与本次保养的验证码为【{$code}】，请勿泄露给其他人。";
		$res = $this->sms->send_code($phone,$content,$code);
		$this->result('',1,$res);
	}

	/**
	 * 进行邦保养操作
	 */
	public function handle()
	{
		//获取提交过来的数据
		$data = input('post.');
		$check = $this->sms->compare($data['phone'],$data['code']);
		if($check !== false){
			//检测库存是否充足
			$oilCheck = $this->checkOil($this->sid,$data['oid'],$data['litre']);
			//如果库存充足，则进行邦保养操作
			if($oilCheck !== false){
				//获取运营商处设定的金额
				$rd = 	Db::table('cs_shop')
							->alias('s')
							->join(['ca_agent_set'=>'a'],'s.aid = a.aid')
							->field('shop_fund,shop_hours')
							->where('s.id',$this->sid)
							->find();
				//从总后台出获取设定的金额
				$reward = Db::table('am_system_setup')
						  ->where('type','技师邦保养奖励金额')
						  ->value('money');
				// 构建邦保养记录数据
				$arr = [
					'sid' => $this->sid,
					'odd_number' => build_order_sn(),
					'cid' => $data['cid'],
					'oil' => $data['oil'],
					'uid' => $data['userid'],			
					'litre' => $data['litre'],
					'filter' => $data['filter'],
					'grow_up' => $rd['shop_fund'],
					'hour_charge' => $rd['shop_hours'],
					'total' => $rd['shop_fund']+$rd['shop_hours']
				];
				//构建技师成长基金记录
				$trade_no = build_only_sn();
				$info = [
					'wid'         => $data['uid'],
					'mold'        => 1,
					'type'        => 1,
					'acid'        => $data['cid'],
					'reward'      => $reward,
					'trade_no'    => $trade_no,
					'create_time' => time()
				];
				//开启事务
				Db::startTrans();
				//减少用户卡的次数
				$card_des = Db::table('u_card')
							->where('id',$data['cid'])
							->setDec('remain_times');
				//汽修厂库存减少
				$ration_dec = Db::table('cs_ration')
							  ->where('sid',$this->sid)
							  ->where('materiel',$data['oid'])
							  ->setDec('stock',$data['litre']);
				//汽修厂账户余额增加
				$shop_inc = Db::table('cs_shop')
							->where('id',$this->sid)
							->setInc('balance',$arr['total']);
				//生成邦保养记录
				$bang_log = Db::table('cs_income')
							->where('sid',$this->sid)
							->insert($arr);
				//技师邦保养奖励金入库
				$worker_re = Db::table('tn_worker_reward')
						   ->insert($info);
				//事务提交判断
				if($card_des && $ration_dec && $shop_inc && $bang_log && $worker_re){
					Db::commit();
				// 获取技师的openid
				// $openid = Db::table('tn_user')->where('id',$data['uid'])->value('open_id');
				// $epay = new Epay();
				// $trade_no = build_only_sn();
				// $epay->dibs($trade_no,$openid,$reward,'技师邦保养奖励');
					$this->result('',1,'提交成功，成长基金稍后发放');
				} else {
					Db::rollback();
					$this->result('',0,'提交失败');
				}
			} else {
				Db::rollback();
				$this->result('',0,'该油品库存不足');
			}
		} else {
			$this->result('',0,'手机验证码无效或已过期');
		}
	}

	/**
	 * 检测库存油品是否充足
	 */
	public function checkOil($sid,$oid,$litre)
	{
		// 获取该油品库
		$stock = Db::table('cs_ration')->where(['materiel' => $oid,'sid' => $sid])->value('stock');
		// 检测该油品库存是否充足
		return ($stock < $litre) ? false : true;
	}

	/**
	 * 获取车辆信息
	 */
	public function getCarInfo($plate)
	{
		return 	Db::table('u_card')
				->alias('c')
				->join(['u_user'=>'u'],'c.uid = u.id')
				->join(['co_bang_data'=>'d'],'c.car_cate_id = d.cid')
				->join(['co_car_cate'=>'car'],'c.car_cate_id = car.id')
				->join(['co_bang_cate'=>'ba'],'c.oil = ba.id')
				->where('plate',$plate)
				->where('remain_times','>',0)
				->field('u.name,u.phone,u.id as userid,d.month,d.km,d.filter,d.litre,car.type,c.card_number,c.remain_times,ba.name as oil,c.oil as oid,c.id as cid,c.plate')
				->find();
	}
} 