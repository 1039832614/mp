<?php 
namespace app\sm\controller;
use app\base\controller\Sm;
use think\Db;
/**
 * 修改信息
 */
class Center extends Sm
{
	/**
	 * 初始化方法
	 * @return [type] [description]
	 */
	public function initialize()
	{

	}
	/**
	 * 修改用户信息
	 * @return [type] [description]
	 */
	public function alterInfo()
	{
		$data = input('post.');
		$uid = $data['uid'];
		unset($data['uid']);
		$res = Db::table('sm_user')
				->where('id',$uid)
				->update($data);
		if($res !== false){
			$this->result('',1,'修改成功');
		} else {
			$this->result('',0,'修改失败');
		}
	}
}