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
		$res = $this->coMsg->getUrMsg('7',$this->table,$this->uid);
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
			foreach ($list as $key => $value) 
			{
			$date = date("Y-m-d H:i:s",$value['create_time']);
			$list[$key]['create_time'] = $date;
		 	}
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
			$datil['create_time'] = date("Y-m-d H:i:s",$datil['create_time']);
			$this->result($datil,1,'获取消息详情成功');
		}else{
			$this->result('',0,'获取消息详情失败');
		}
	}
	/**
	 * 删除系统消息
	 */
	public function delMsg()
	{
		$mid = input('get.mid');
		$uid = input('get.uid');
		$res = Db::table('tn_msg')
			   ->where('mid',$mid)
			   ->where('uid',$uid)
			   ->delete();
		if($res){
			$this->result('',1,'删除成功');
		} else {
			$this->result('',0,'删除失败');
		}
	}
}