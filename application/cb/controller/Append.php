<?php 
namespace app\cb\controller;
use app\base\controller\Bby;
use think\Db;

class Append extends Bby
{

	/**
	 * 获取用户关于邦保养会员的读取状态
	 * @return [type] [description]
	 */
	public function getAboutVipRead()
	{
		$uid = input('post.uid');
		$status = Db::table('u_user')
					->where('id',$uid)
					->value('about_vip_read');
		if($status == 0) {
			$this->result('',1,'用户尚未读取关于邦保养会员的信息');
		} else {
			$this->result('',0,'用户已读关于邦保养会员的信息');
		}
	}
	/**
	 * 更新用户关于邦保养会员的读取状态
	 * @return [type] [description]
	 */
	public function updateAboutVipRead()
	{
		$uid = input('post.uid');
		$up = Db::table('u_user')
					->where('id',$uid)
					->setField(['about_vip_read'=>1]);
		if($up !== false) {
			$this->result('',1,'更新成功');
		} else {
			$this->result('',0,'更新失败');
		}
	}
}