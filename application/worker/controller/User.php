<?php 
namespace app\worker\controller;
use app\base\controller\Worker;
use think\Db;

/**
 * 技师完善信息，更换店面，成长基金
 */
class User extends Worker
{

	/**
	 * 获取用户头像
	 * @return [type] [description]
	 */
	public function userInfo()
	{
		$uid = input('get.uid');
		$userInfo = Db::table('tn_user')
					->where('id',$uid)
					->field('head,wx_head,repair,name,phone,server,sid,skill')
					->find();
		if($userInfo){
			$this->result(['userInfo'=>$userInfo],1,'获取技师用户头像成功');
		} else {
			$this->result('',0,'获取技师头像失败，请重试');
		}
	}
	/**
	 * 上传头像
	 */
	public function uploadPic()
	{
		return $this->uploadImage('image','head','https://mp.ctbls.com');
	}
	/**
	 * 获取维修厂列表
	 */
	public function shop()
	{
		$uid = input('get.uid');
		$data = $this->getLocation($uid,"tn_user");
		$data['page'] = input('get.page');
		if(!empty($data)){
			//获取经纬度成功
			//距离，维修厂照片，维修厂id，维修厂名字，维修厂简介
			$this->shopList($data,'cs');
		} else {
			//获取技师的经纬度失败
			$this->result('',0,'获取技师的经纬度失败，请刷新页面，再进行这个操作');
		}
	}
	/**
	 * 完善个人信息
	 */
	public function updateWorker()
	{
		$data = input('post.');
		//头像，车型，姓名，电话，从业时间，所属维修厂，技能介绍
		$uid = input('post.uid');
		unset($data['uid']);
		$validate = validate('UserInfo');
		if($validate->check($data)){
			$res = Db::table('tn_user')
					->where('id',$uid)
					->update($data);
			if($res){
				$this->result('',1,'完善成功，等待审核');
			} else {
				$this->result('',0,'未发现修改');
			}
		} else {
			$this->result('',0,$validate->getError());
		}
	}

	/**
	 * 技师原有维修厂的信息，以及是否认证
	 */
	public function shopDetail()
	{
		$uid = input('get.uid');
		$shop = Db::table('tn_user')	
				->alias('u')
				->join('cs_shop s','u.sid = s.id')
				->where('u.id',$uid)
				->field('company,sid')
				->find();
		if($shop){
			$this->result(['shop'=>$shop],1,'获取数据成功');
		} else {
			$this->result('',0,'请完善个人信息');
		}
	}
	/**
	 * 技师申请换店
	 */
	public function exShop()
	{
		$data = input('post.');
		$validate = validate('ExShop');
		if($validate->check($data)){
			$data['create_time'] = time();
			$res = Db::table('tn_exshop') 
					->insert($data);
			$ue = Db::table('tn_user')
					->where('id',$data['uid'])
					->update(['sid'=>$data['sid'],'cert'=>0]);
			if($res && $ue){
				$this->result('',1,'申请换店成功，请等待审核');
			} else {
				$this->result('',0,'申请换店失败，请重试');
			}
		} else {
			$this->result('',0,$validate->getError());
		}
	}
	// /**
	//  * 技师申请换店列表
	//  * @param  [type] $uid    技师id
	//  * @param  [type] $status 状态值，0-等待，1-通过，2-驳回
	//  * @return [type]         [description]
	//  */
	// public function exShopList($uid,$status)
	// {
	// 	$list = Db::table('tn_exshop')
	// 			->alias('e')
	// 			->join('cs_shop s','e.new_shop = s.id')
	// 			->where('e.status',$status)
	// 			->where('e.uid',$uid)
	// 			->order('e.create_time desc')
	// 			->field('e.reason,e.create_time,s.company,e.audit_time')
	// 			->select();
	// 	foreach ($list as $key => $value) {
	// 		$date = date("Y-m-d H:i:s",$value['create_time']);
	// 		$list[$key]['create_time'] = $date;
	// 	}
	// 	if($list){
	// 		$this->result(['list'=>$list],1,'获取列表成功');
	// 	} else {
	// 		$this->result('',0,'暂无信息');
	// 	}
	// }

	// /**
	//  * 申请换店通过列表
	//  */
	// public function passList()
	// {
	// 	$uid = input('get.uid');
	// 	$this->exShopList($uid,1);
	// }

	// /**
	//  * 申请换店驳回列表
	//  */
	// public function rejectList()
	// {
	// 	$uid = input('get.uid');
	// 	$this->exShopList($uid,2);
	// }

	// /**
	//  * 申请换店等待列表
	//  */
	// public function waitList()
	// {
	// 	$uid = input('get.uid');
	// 	$this->exShopList($uid,0);
	// }

	/**
	 * 成长基金中，邦保养服务列表
	 * @return [type] [description]
	 */
	public function bangRewardList()
	{
		$uid = input('get.uid');
		//车型，成长基金，时间
		$list = Db::table('tn_worker_reward')
				->alias('r')
				->join('u_card c','r.acid = c.id')
				->join('co_car_cate e','c.car_cate_id = e.id')
				->where('wid',$uid)
				->where('r.type',1)
				->order('r.create_time desc')
				->field('r.id,r.reward,r.create_time,e.type')
				->select();
		foreach ($list as $key => $value) {
			$date = date("Y-m-d H:i:s",$value['create_time']);
			$list[$key]['create_time'] = $date;
		}
		if($list){
			$this->result(['list'=>$list],1,'获取列表成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
	/**
	 * 文章奖励列表
	 * @return [type] [description]
	 */
	public function articleRewardList()
	{
		$uid = input('get.uid');
		// //文章标题，奖励金，时间
		$list = Db::table('tn_worker_reward')
				->alias('r')
				->join('tn_article a','r.acid = a.aid')
				->where('r.wid',$uid)
				->where('r.type',2)
				->order('r.create_time desc')
				->field('r.id,r.reward,r.create_time,a.aid,a.title')
				->select();
		foreach ($list as $key => $value) {
			$date = date("Y-m-d H:i:s",$value['create_time']);
			$list[$key]['create_time'] = $date;
		}
		if($list){
			$this->result(['list'=>$list],1,'获取列表成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
}
