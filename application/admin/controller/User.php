<?php 
namespace app\admin\controller;

use think\Db;
use think\Controller;

class User extends Base
{
	/**
	 * 约驾小程序用户列表
	 * @return [type] [description]
	 */
	public function userList(){
		$user = Db::table('yue_user')
		        ->order('u_time')
				->paginate(10);
	    $this->assign('user',$user);
		return $this->fetch();	
	}
	/**
	 * 用户详情
	 */
	public function userTakeDetail(){
		$id = request()->param()['id'];
		$user = Db::table('yue_user')
		        ->where('u_id',$id)
				->find();
		$this->assign('user',$user);
		return $this->fetch();		
	}
	
}