<?php 
namespace app\worker\controller;
use app\base\controller\Worker;
use think\Db;
use Msg\Msg;

/**
 * 技师获取信息
 */
class Msgs extends Worker
{
	/**
	 * 用户登录后，向库里插入新发送的系统消息
	 * @return [type] [description]
	 */
	public function msg(){
		$msg = new Msg();
		$uid = input('post.uid');
		$res = $msg->getUrMsg('7','tn_msg',$uid);
	}
	/**
	 * 消息列表
	 */
	public function msgList()
	{
		$msg = new Msg();
		$page = input('get.page') ? : 1;
		$uid = input('get.uid');
		$list = $msg->msgList('tn_msg',$uid,$page);
		if(count($list['list']) > 0){
			$this->result($list,1,'获取消息列表成功');
		}else{
			$this->result('',0,'暂无数据');
		}
	}

	/**
	 * 消息详情
	 */
	public function msgDetail()
	{
		$mid = input('get.mid');
		$uid = input('get.uid');
		$msg = new Msg();
		$datil = $msg->msgDetail('tn_msg',$mid,$uid,7);
		if($datil){
			$this->result($datil,1,'获取消息详情成功');
		}else{
			$this->result('',0,'获取消息详情失败');
		}
	}
	// /**
	//  * 删除系统消息,暂时不支持
	//  */
	// public function delMsg()
	// {
	// 	$mid = input('get.mid');
	// 	$uid = input('get.uid');
	// 	$res = Db::table('tn_msg')
	// 		   ->where('mid',$mid)
	// 		   ->where('uid',$uid)
	// 		   ->delete();
	// 	if($res){
	// 		$this->result('',1,'删除成功');
	// 	} else {
	// 		$this->result('',0,'删除失败');
	// 	}
	// }

	/**
	 * 判断是否有未读消息
	 */
	public function ifUnRead()
	{
		$uid = input('get.uid');
		$count = Db::table('tn_msg')
					->where(['uid'=>$uid,'status'=>0])
					->count();
		if($count > 0) {
			$this->result('',1,'您有未读的消息');
		} else {
			$this->result('',0,'没有未读的消息');
		}
	}
}