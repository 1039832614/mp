<?php 
namespace app\st\controller;
use app\base\controller\Store;
use think\Db;
use Config;

class User extends Store
{

	/**
	 * 获取用户源信息
	 * @return [type] [description]
	 */
	public function userInfo()
	{
		$uid = input('get.uid');
		$info = Db::table('st_user')
				->where('id',$uid)
				->field('head_pic,nick_name,name,phone')
				->find();
		if($info) {
			$this->result($info,1,'获取数据成功');
		} else {
			$this->result('',0,'获取数据失败');
		}
	}
	/**
	 * 完善资料
	 */
	public function complete()
	{
		$data = input('post.');//姓名和手机号
		$uid = input('post.uid');
		unset($data['uid']);
		$validate = validate('User');
		if($validate->check($data)) {
			$res = Db::table('st_user')
					->where('id',$uid)
					->update($data);
			if($res !== false) {
				$this->result('',1,'完善成功');
			} else {
				$this->result('',0,'完善失败');
			}
		} else {
			$this->result('',0,$validate->getError());
		}
	}
	/**
	 * 关于我们
	 * @return [type] [description]
	 */
	public function companyInfo()
	{
		$info = [
			'company' => '车托邦',
			'detail' => '详情',
			'leader'  => '领导人'
		];
		return $info;
	}
}