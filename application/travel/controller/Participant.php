<?php 
namespace app\travel\controller;
use app\base\controller\Travel;
use think\Db;

class Participant extends Travel
{
    /**
     * 参与活动
     * @return [type] [description]
     */
	public function takePart()
	{
		$data = input('post.');
		$validate = validate('Part');
		if($validate->check($data))
		{
			$activity = Db::table('yue_activity')
		         	 	->where('id',$data['aid'])
		          		->find();
			//活动剩余人数
			$anumber = $activity['anumber'] - $data['number'];
			//更新活动信息表
			$res = Db::table('yue_activity')
		        ->where('id',$data['aid'])
		        ->update(['anumber'=>$anumber]);
			//向参与约驾活动信息表中添加新的数据
			$take = Db::table('yue_participant')
			        ->strict(false)
			        ->insert($data);
			if($take){
				$this->result(['aid'=>$data['aid']],1,'参与成功');
			} else {
				$this->result('',0,'参与约驾活动失败，请稍后重试');
			}
		} else {
			$this->result('',0,$validate->getError());
		}
	}
	/**
	 * 我参与的约驾活动的列表
	 */
	public function myTakePart()
	{
		$uid = input('get.uid');
		$res = Db::table('yue_participant')
		       ->alias('p')
		       ->join('yue_activity a','a.id = p.aid')
		       ->where('p.uid',$uid)
		       ->field('a.id,title,path,start_time')
		       ->select();
		if($res){
			$this->result($res,1,'获取数据成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
	/**
	 * 取消已经参与的约驾活动
	 */
	public function delMyTakePart()
	{
		$aid = input('get.aid');
		$uid = input('get.uid');
		$myTake = Db::table('yue_participant')
		          ->where('aid',$aid)
		          ->where('uid',$uid)
		          ->find();	
		//这个用户的参与人数
		$num = $myTake['number'];
		Db::startTrans();
		//恢复活动剩余人数
		if($myTake)
		{
			$res = Db::table('yue_participant')
		           ->where('aid',$aid)
		           ->where('uid',$uid)
		           ->delete();
			$activity = Db::table('yue_activity')
			        ->where('id',$aid)
			        ->find();
		   	//活动总人数
		    $number = $activity['number'];
	    	//活动剩余人数
		    $anumber = $activity['anumber'];
	    	//剩余人数 = 现在的剩余人数 + 这个用户之前输入的参与人数
	    	$anumber = $anumber + $num;
	    	if($anumber > $number)
	    	{
				$anumber = $number;
	    	}
	    	$re = Db::table('yue_activity')
	    	      ->where('id',$aid)
	    	      ->update(['anumber' => $anumber]);
	    	Db::commit(); 
	    	if($res && $re){
	    		Db::commit();
	    		$this->result('',1,'取消成功');
	    	} else {
	    		Db::rollback();
	    		$this->result('',0,'提交失败');
	    	}
		} else {
			Db::rollback();
			$this->result('',0,'提交失败');
		}
	}
}