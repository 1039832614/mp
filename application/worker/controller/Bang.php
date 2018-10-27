<?php 
namespace app\worker\controller;
use app\base\controller\Worker;
use think\Db;
use Exg\Exg;
use Epay\WorkerEpay;

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
		$pic = str_replace("https://mp.ctbls.com", '.', $pic);
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
	 * 上传车牌号图片
	 */
	public function uploadPic()
	{
		return $this->uploadImage('image','plate','https://mp.ctbls.com');
	}
	/**
	 * 输入车牌号后，返回这个车牌号对应的邦保养卡号 以及其他信息
	 */
	public function bang()
	{
		$data = input('post.');
		// 获取车牌号
		$plate = input('post.plate_number','','strtoupper');
		//检测该车辆是否在当前维修厂
		$count = Db::table('u_card')
					 ->where('sid',$this->sid)
					 ->where('plate',$plate)
					 ->where('pay_status',1)
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
				$this->result('',0,$plate.'邦保养次数为0');
			}	
		} else {
			$this->result('',0,$plate.'该卡无效或不属于该汽修厂');
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
							->field('shop_fund,shop_hours,shop_hours,s.aid')
							->where('s.id',$this->sid)
							->find();
				// 获取卡的总金额
				$price = Db::table('u_card')->where('id',$data['cid'])->value('card_price');
				// $shop_fund = $price*$rd['shop_fund']/100;
				// 构建邦保养记录数据
				$arr = [
					'sid' => $this->sid,
					'odd_number' => build_order_sn(),
					'cid' => $data['cid'],
					'oil' => $data['oil'],
					'uid' => $data['userid'],
					'litre' => $data['litre'],
					'filter' => $data['filter'],
					// 'grow_up' => $shop_fund,
					'hour_charge' => $data['hour_charge'],
					'total' => $data['hour_charge']+$data['filter']
				];
				//构建技师成长基金记录
				$trade_no = build_only_sn();
				$info = [
					'wid'         => $data['uid'],
					'mold'        => 1,
					'type'        => 1,
					'acid'        => $data['cid'],
					'reward'      => 10,
					'trade_no'    => $trade_no,
					'create_time' => time()
				];
				// 可提现收入
				$money = $data['hour_charge']+$data['filter'];
				//开启事务
				Db::startTrans();
				//减少用户卡的次数
				$card_dec = Db::table('u_card')
							->where('id',$data['cid'])
							->setDec('remain_times');
				//维修厂库存减少
				$ration_dec = Db::table('cs_ration')
							  ->where('sid',$this->sid)
							  ->where('materiel',$data['oid'])
							  ->setDec('stock',$data['litre']);
				//维修厂账户余额增加服务次数增加
				$shop_inc = Db::table('cs_shop')
							->where('id',$this->sid)
							->inc('balance',$money)
							->inc('service_num',1)
							->update();
				// 运营商邦保养次数增加
				$service_num = Db::table('ca_agent')
								->where('aid',$rd['aid'])
								->inc('service_time',1)
								->update();
				//生成邦保养记录
				$bang_log = Db::table('cs_income')
							->strict(false)
							->insert($arr);
				//技师邦保养奖励金入库
				$worker_re = Db::table('tn_worker_reward')
						   ->insert($info);
				//事务提交判断
				if($card_dec && $ration_dec && $shop_inc && $bang_log && $worker_re && $service_num){
					Db::commit();
				// 获取技师的openid
				$openid = Db::table('tn_user')->where('id',$data['uid'])->value('openid');
				$epay = new WorkerEpay();
			    $epay->dibs($trade_no,$openid,10*100,'技师邦保养奖励');
//					$this->result('',1,'提交成功,请为车主进行服务,24小时内奖励金到账');
                    //  2018.09.08  张乐召  修改
					$this->result('',1,'提交成功,获得10元奖励金');
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
	 * 获取车辆信息 ,
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
				->where('c.pay_status',1)
				->field('u.name,u.phone,u.id as userid,d.month,d.km,d.filter,d.litre,car.type,car.id as car_type_id,c.card_number,c.remain_times,ba.name as oil,c.oil as oid,c.id as cid,c.plate,hour_charge')
				->find();
	}

	
	/**
	 * 根据车的品牌，车的型号，返回车的详细类型
	 */
	public function getCarType(){
		$data = input('get.');
		//获取车的品牌、车型、排量
		$car = Db::table('co_car_cate')
				     ->alias('c')
					 ->join(['co_car_menu'=>'m'],'c.brand = m.id')
					 ->where('c.id',$data['car_type_id'])
					 ->field('c.series as pl,c.type,m.name as brand')
					 ->find();
		$pl = $this->exNum($car['pl']);//排量的数字
		$type = explode('-', $car['type']);
		//查看数组长度
		if(count($type)>1){
			$type = $type[1];
		} else {
			$type = $type[0];
		}
		$list = Db::table('co_car_detail')
				->where([
					['brand','=',$car['brand']],
					['type','like',"%$pl%"],
					['series','like',"%$type%"]
				])
				->field('id,type')
				->group('type')
				->select();
		if($list) {
			$this->result(['list'=>$list],1,'获取数据成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
	/**
	 * 获取详细的车型数据
	 */
	public function getDetail()
	{
		$id = input('get.id');
		$car = Db::table('co_car_detail')
			   ->where('id',$id)
			   ->find();
		if($car){
			$this->result($car,1,'获取数据成功');
		} else {
			$this->result('',0,'获取数据失败');
		}
	}

	/**
	 * 去掉空格和TFSI
	 */
    function exNum($str=''){
    	$tfsi = str_replace('T', '', $str);
		$tfsi = str_replace('F', '', $tfsi);
		$tfsi = str_replace('S', '', $tfsi);
		$tfsi = str_replace('I', '', $tfsi);
		$tfsi = str_replace(' ', '', $tfsi);
        return $tfsi;
    }


    /**
     * 删除车牌图片
     */
    public function delImage()
    {
    	$image = input('get.image');
    	$image = str_replace('https://mp.ctbls.com', '.', $image);
    	$del = unlink($image);
    	if($del) {
    		$this->result('',1,'删除成功');
    	} else {
    		$this->result('',0,'删除失败');
    	}
    }
} 