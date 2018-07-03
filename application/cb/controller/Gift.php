<?php 
namespace app\cb\controller;
use app\base\controller\Bby;
use think\Db;
/**
* 邦保养赠品管理
*/
class Gift extends Bby
{
	/**
	 * 获取店铺提供的服务
	 */
	public function getServer()
	{
		$sid = input('get.sid');
		$data = Db::table('cs_service')->field('service as gift_name,cover,price,id as gid')->where('sid',$sid)->find();
		if($data){
			$data['gcate'] = 3;
			$this->result($data,1,'获取数据成功');
		}else{
			$this->result('',0,'获取数据异常');
		}	
	}

	/**
	 * 获取系统提供的赠品
	 */
	public function getGift()
	{
		$this->getSysGift(7,1);
	}

	/**
	 * 系统提供的OBD服务
	 */
	public function getObd()
	{
		$this->getSysGift(8,2);
	}
	

	/**
	 * 选择赠品操作
	 */
	public function choice()
	{
		$data = input('get.');
		// 检测是否符合领取条件
		$card = Db::table('u_card')->field('card_type,uid')->where('id',$data['cid'])->find();
		if($card['card_type'] < 4) $this->result('',0,'四次卡才可以领取礼品！');
		// 检测是否已经领取过
		$count = Db::table('cs_gift')->where('cid',$data['cid'])->find();
		if($count) $this->result('',0,'请勿重复领取');
		// 构建插入数据
		$arr = [
			'uid' => $card['uid'],
			'cid' => $data['cid'],
			'sid'	=> $data['sid'],
			'gcate'	=> $data['gcate'],
			'gid'	=> $data['gid'],
			'gift_name'	=> $data['gift_name'],
			'excode' => build_only_sn(),
			'status' => 2
		];
		// 进行入库操作
		if(Db::table('cs_gift')->insert($arr)){
			$this->result('',1,'领取礼品成功！');
		}else{
			$this->result('',0,'领取失败！');
		}
	}

	/**
	 * 获取赠品列表
	 */
	public function getList()
	{
		$sid = input('get.sid');
		// 获取系统赠品
		$gift = Db::table('co_bang_cate_about')->where('bc_id',7)->value('name');
		// 获取OBD管家
		$obd = Db::table('co_bang_cate_about')->where('bc_id',8)->value('name');
		// 检测店铺是否提供的服务
		$count = Db::table('cs_service')->where('sid',$sid)->count();
		if($count > 0){
			// 获取店铺服务
			$server = Db::table('cs_service')->where('sid',$sid)->value('service');
			// 返回前端数据
			$this->result(['gift'=>$gift,'obd'=>$obd,'server'=>$server],1,'获取数据成功');
		}else{
			$this->result(['gift'=>$gift,'obd'=>$obd],1,'获取数据成功');
		}
		
	}

	/**
	 * 获取系统赠品
	 */
	public function getSysGift($bcid,$gcate)
	{
		$data = Db::table('co_bang_cate_about')->field('name as gift_name,cover,price,bc_id as gid')->where('bc_id',$bcid)->find();
		if($data){
			$data['gcate'] = $gcate;
			$this->result($data,1,'获取数据成功');
		}else{
			$this->result('',0,'获取数据异常');
		}	
	}
}