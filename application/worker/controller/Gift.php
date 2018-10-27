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
		if($excode) {
			//检测兑换码是否有效
			$res = $this->checkExCode($this->sid,$excode);
			//如果是店铺提供的服务
			$gift = Db::table('cs_gift')
					->where('excode',$excode)
					->value('gift_name');
			if($gift){
				$gift = stripcslashes($gift);
				$this->result($gift,1,'获取数据成功');
			} else {
				$this->result('',0,'获取赠品信息失败');
			}
		} else {
			$this->result('',0,'请输入内容');
		}
		
	}
	/**
	 * 进行兑换礼品
	 */
	public function exGift()
	{
		$data = input('post.');
		$excode = input('post.excode');
		if(empty($excode)){
			$this->result('',0,'请输入内容');
		}
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
								->value('g.card_number');
			//检测库存是否充足
			$gs = Db::table('cs_ration')
					->where('sid',$this->sid)
					->where('materiel',$res['gid'])
					->value('stock');
			if($gs <= 0){
				$this->result('',0,'该赠品库存不足，请进行补货后再进行兑换');
			}
			//如果充足则减少库存
			$se = Db::table('cs_ration')
					->where('sid',$this->sid)
					->where('materiel',$res['gid'])
					->setDec('stock',1);
			//兑换码失效，兑换信息改变
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
			if($se !== false && $ex !== false & $re !== false){
				Db::commit();
				$this->result('',1,'兑换成功');
			} else {
				$this->result('',0,'兑换失败');
			}
		} 
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
				->where('status',1)
				->find();
		if($res){
			return $res;
		}else{
			$this->result('',0,'兑换码不符合兑换条件');
		}
	}
}