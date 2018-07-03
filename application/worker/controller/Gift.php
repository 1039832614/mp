<?php 
namespace app\worker\controller;
use app\base\controller\Worker;
use think\Db;
/**
 * 礼品兑换
 */
class Gift extends worker
{

	public function initialize()
	{
		$uid = input('post.uid');
		$this->uid = $uid;
		$this->sid = Db::table('tn_user')
					->where('id',$uid)
					->value('sid');
	}

	/**
	 * 输入兑换码，返回赠品名称
	 */
	public function getGiftName()
	{
		$excode = input('post.excode');
		//检测兑换码是否有效
		$res = $this->checkExCode($this->sid,$excode);
		//如果是店铺提供的服务
		$gift = Db::table('cs_gift')
				->where('excode',$excode)
				->value('gift_name');
		if($gift){
			$this->result($gift,1,'获取数据成功');
		} else {
			$this->result('',0,'获取赠品信息失败');
		}
	}
	/**
	 * 进行兑换礼品
	 */
	public function exGift()
	{
		$excode = input('post.excode');
		$gift_name = input('post.gift');
		//检测兑换码是否有效
		$res = $this->checkExCode($this->sid,$excode);
		if($res){
			//开启事务
			Db::startTrans();
			$card_number = Db::table('cs_gift')
								->alias('g')
								->join(['u_card'=>'c'],'g.cid = c.id')
								->where('excode',$excode)
								->value('card_number');
			//如果类别小于3则兑换赠品
			if($res['gcate'] < 3){
				//检测库存是否充足
				$this->checkRation($this->sid,$res['gid']);
				//如果充足则减少库存
				$se = Db::table('cs_ration')
					  ->where('sid',$this->sid)
					  ->where('materiel',$res['gid'])
					  ->setDec('stock',1);
			} else {
				//获取兑换服务名称
				$server_name = Db::table('cs_service')
								->where('sid',$this->sid)
								->value('service');
				//如果等于3则兑换自己的服务，添加收入记录
				//构建入库数据
				$info = [
					'sid'         => $this->sid,
					'ex_charge'   => 100,
					'excode'      => $excode,
					'card_number' => $card_number
				];
				$si = Db::table('cs_ex_income')
					  ->insert($info);
				//系统补助费100元
				$sa = Db::table('cs_shop')
				      ->where('id',$this->sid)
				      ->setInc('balance',100);
			}
			// 兑换码状态->失效，兑换信息改变
			$ex = Db::table('cs_gift')
					->where('excode',$excode)
					->update(['ex_time'=>time(),'status'=>2]);
			//构建技师兑换数据
			$worker = Db::table('tn_user')
					  ->where('id',$this->uid)
					  ->value('name');
			$arr = [
				'worker'      => $worker,
				'sid'         => $this->sid,
				'card_number' => $card_number,
				'gift_name'   => $gift_name
			];
			$re = Db::table('tn_gift')
				->insert($arr);
			// 进行事务处理
			// if(isset($se) && isset($ex)){
			// 	if($se !== false && $sx !== false){
			// 		Db::commit();
			// 		$this->result($gift,1,'兑换成功');
			// 	}else{
			// 		Db::rollback();
			// 		$this->result('',0,'兑换失败');
			// 	}
			// }else if(isset($si) && isset($sa) && isset($ex)){
			// 	if($si && $ex !== false && $sa !== false){
			// 		Db::commit();
			// 		$this->result($gift,1,'兑换成功');
			// 	}else{
			// 		Db::rollback();
			// 		$this->result('',0,'兑换失败');
			// 	}
			// }
			if($ex && $re){
				Db::commit();
				$this->result('',1,'兑换成功');
			} else {
				Db::rollback();
				$this->result('',0,'兑换失败');
			}		
		} 
	}


	/**
	 * 检测赠品库存是否充足
	 */
	public function checkRation($sid,$gid)
	{
		// 赠品库存是否充足
		$gs = Db::table('cs_ration')->where('sid',$sid)->where('materiel',$gid)->value('stock');
		// 如果库存充足则进行兑换
		if($gs <= 0) $this->result('',0,'该赠品库存不足，请进行补货后再进行兑换');
	}

	/**
	 * 检测兑换码是否有效
	 */
	public function checkExCode($sid,$excode)
	{
		// 查看兑换物品码是否有效
		$res = 	Db::table('cs_gift')
				->field('cid,gcate,gid')
				->where('excode',$excode)
				->where('sid',$sid)
				->where('status',1)
				->find();
		if($res){
			return $res;
		}else{
			$this->result('',0,'兑换码已失效或不属于本店');
		}
	}
}