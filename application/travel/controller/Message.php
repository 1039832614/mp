<?php 
namespace app\travel\controller;
use app\base\controller\Travel;
use think\Db;
/**
 * 约驾小程序 我的 消息通知
 */
class Message extends Travel
{
	function initialize(){
		$this->uid = input('get.uid');
	}
	/**
	 * 用户进入消息通知后，向库里插入新发送的
	 */
	public function msg(){
		$res = $this->getUrMsg($this->uid);
	}
	/**
	 * 消息列表全部
	 * @return [type] [description]
	 */
	public function msgL()
	{
		$list = $this->msgList($this->uid);
		if($list){
			$this->result(['list'=>$list],1,'获取列表成功');		
		} else {
			$this->result('',0,'获取列表失败');
		}
	}
	/**
	 * 获取消息详情
	 * @return 消息详情
	 */
	public function detail()
	{
		$mid = input('get.mid');
		$detail = $this->msgDetail($mid,$this->uid);
		if($detail){
			$this->result($detail,1,'获取消息详情成功');
		} else {
			$this->result('',0,'获取消息详情失败');
		}
	}
	/**
	 * 用户删除他的系统消息
	 * @return [type] [description]
	 */
	public function delMessage(){
		$mid = input('get.mid');
		$res = Db::table('yue_message_user')
		       ->where('uid',$this->uid)
		       ->where('mid',$mid)
		       ->delete();
		if($res){
			$this->result('',1,'删除成功');
		} else {
			$this->result('',0,'删除失败');
		}
	}
}