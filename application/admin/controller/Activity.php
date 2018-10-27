<?php 
namespace app\admin\controller;

use think\Db;
use think\Controller;

/**
 * 约驾活动信息
 */
class Activity extends Base
{
	/**
	 * 约驾活动列表
	 * @return [type] [description]
	 */
	public function activity()
	{
		$list = Db::table('yue_activity')
		         ->alias('a')
		         ->join('yue_user u','a.uid = u.u_id')
		         ->order('a.time')
		         ->field('a.id,title,name,wx_number,static,time,red_number')
		         ->paginate(10);
		$this->assign('list',$list);
		return $this->fetch();         
	}
	/**
	 * 约驾活动详情
	 * @return [type] [description]
	 */
	public function detail(){
		$id = request()->param()['id'];
		$activity = Db::table('yue_activity')
		            ->alias('a')
					->join('yue_user u','u.u_id = a.uid')
					->where('a.id',$id)
					->field('a.id,title,motorcycle,time,thronheim,start_time,end_time,path,number,red_number,a.static,name,wx_number,sex,content')
					->find();		
		$pic = Db::table('yue_activity')		 
			   ->where('id',$id)
			   ->value('pic');
		$pic = json_decode($pic);
	    $this->assign('pic',$pic);
		$this->assign('activity',$activity);
		return $this->fetch();
	}
	/**
	 * 删除某个约驾活动
	 * @return [type] [description]
	 */
	public function delActivity(){
		$id = request()->param()['id'];
		$res = Db::table('yue_activity')
		       ->where('id',$id)
		       ->field('id')
		       ->find();
		Db::startTrans();
		if($res){
			Db::table('yue_activity')
			    ->where('id',$id)
			    ->delete();
			Db::table('yue_participant')
			    ->where('aid',$id)
			    ->delete();
			Db::commit();
			$this->success('删除成功','admin/activity/activity');
		} else {
			Db::rollback();
			$this->error('删除失败','admin/activity/activity');
		}
	}
    /**
	 * 审核操作
	 */
	public function check(){
		$id = request()->param()['id'];
		$res = Db::table('yue_activity')
		       ->where('id',$id)
		       ->update(['static' => 1]);
		if($res){
			$this->success('审核通过','admin/activity/activity');
		} else {
			$this->error('操作失败，请稍后重试','admin/activity/activity');
		}
	}
	/**
	 * 驳回操作
	 */
	public function reject(){
		$id = request()->param()['id'];
		$res = Db::table('yue_activity')
		       ->where('id',$id)
		       ->update(['static' => 2]);
		if($res){
			$this->success('已驳回','admin/activity/activity');
		} else {
			$this->error('操作失败，请稍后重试','admin/activity/activity');
		}
	}
}