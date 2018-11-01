<?php 
namespace app\ubi\controller;
use app\base\controller\Ubi;
use think\Db;
/**
* 返回初始里程
*/
class Api extends Ubi
{

	public function index()
	{
		echo 1;exit;
		$obdid = input('get.obdid');

		$obdid = json_decode($obdid);
		// 获取起始里程
		$startMilege = Db::table('cb_user')->where('eq_num',$obdid)->value('km');

		if($startMilege){

			$this->result(['startMilege'=>$startMilege],1,'获取数据成功');
		}else{
			$this->result('',0,'暂无初始里程');
		}
	}
	
	
}
