<?php 
namespace app\sm\controller;
use app\base\controller\Sm;
use think\Db;
use Msg\Msg;

class MsgY extends Sm
{
	/**
	 * 获取消息列表
	 * @return [type] [description]
	 */
	public function getMsgs()
	{	
		//获取提交过来的uid
		$uid = input('post.uid');
		//初始化信息拓展类
		$Msg = new Msg();
		$person_rank = Db::table('sm_user')
						->where('id',$uid)
						->value('person_rank');
		if($person_rank == 1 || $person_rank == 4) {
			//是服务经理
			//更新最新消息
			$mids = $Msg->getUrMsg(11,'sm_msg_f',$uid);
			//获取未读消息
			$data = Db::table('sm_msg_f')
					->alias('f')
					->join('am_msg m','f.mid = m.id')
					->where('uid',$uid)
					->where('status',0)
					->order('mid')
					->limit(1)
					->find();
			//返回数据给前端
			if($data){
	            $this->result($data,1,'获取未读消息成功');
	        }else{
	            $this->result('',0,'暂无未读消息');
	        }
		} 
		if($person_rank == 2 || $person_rank == 5) {
			//是运营总监
			//更新最新消息
			$mids = $Msg->getUrMsg(12,'sm_msg_y',$uid);
			//获取未读消息
			$data = Db::table('sm_msg_y')
					->alias('f')
					->join('am_msg m','f.mid = m.id')
					->where('uid',$uid)
					->where('status',0)
					->order('mid')
					->limit(1)
					->find();
			//返回数据给前端
			if($data){
	            $this->result($data,1,'获取未读消息成功');
	        }else{
	            $this->result('',0,'暂无未读消息');
	        }
		}
	}
	/**
	 * 消息列表
	 * @return [type] [description]
	 */
	public function msgList()
	{
		$msg = new Msg();
		$page = input('post.page') ? : 1;
		$uid = input('post.uid');
		$person_rank = Db::table('sm_user')
						->where('id',$uid)
						->value('person_rank');
		if($person_rank == 1 || $person_rank == 4) {
			$list = $msg->msgList('sm_msg_f',$uid,$page);
		} else {
			$list = $msg->msgList('sm_msg_y',$uid,$page);
		}
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
		$mid = input('post.mid');
		$uid = input('post.uid');
		$msg = new Msg();
		$person_rank = Db::table('sm_user')
						->where('id',$uid)
						->value('person_rank');
		if($person_rank == 1 || $person_rank == 4) {
			$datil = $msg->msgDetail('sm_msg_f',$mid,$uid,1);
		} else {
			$datil = $msg->msgDetail('sm_msg_y',$mid,$uid,1);
		}
		if($datil){
			$this->result($datil,1,'获取消息详情成功');
		}else{
			$this->result('',0,'获取消息详情失败');
		}
	}

}