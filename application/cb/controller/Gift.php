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
	 * 获取豪华大礼包
	 */
	public function getGift()
	{
		$info = Db::table('co_bang_cate_about')->field('bc_id,name,cover,price,about')->where('bc_id',7)->find();
		$this->result($info,1,'获取成功');
	}

	/**
	 * 进行领取
	 */
	public function choice()
	{
		$data = input('get.');
		// print_r($data);exit;
		// 检测是否符合领取条件
		$card = Db::table('u_card')->field('card_type,uid,card_number')->where('id',$data['cid'])->find();
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
			'status' => 0,
			'card_number'=>$card['card_number']
		];
		// 进行入库操作
		if(Db::table('cs_gift')->insert($arr)){
			$this->result('',1,'领取礼品成功！');
		}else{
			$this->result('',0,'领取失败！');
		}
	}
}