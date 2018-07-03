<?php
namespace app\travel\controller;
use app\base\controller\Travel;
use think\Db;
/**
 * 获取车的信息
 */
class Car extends Travel
{

	/**
	 * 获取所有的车的品牌
	 * @return  车的品牌
	 */
	public function menu(){
		$car = Db::table('co_car_menu')->field('id,name')->select();
		return $car;
	}
	/**
	 * 选中车的品牌后显示车的品牌中里面的所有型号
	 * @return 车的型号
	 */
	public function type()
	{
		$brand = input('get.id');
		return Db::table('co_car_cate')->where('brand',$brand)->field('id as typeId,type')->select();
	}
	/**
	 * 增加我的汽车
	 * @return $msg 状态值和消息
	 */
	public function addMyCar()
	{
		//获取信息
		$data = input('post.');
		$validate = validate('License');
		if($validate->check($data)){
			$res = Db::table('yue_user_car')->strict(false)->insert($data);
			if($res){
				$this->result('',1,'添加我的汽车成功');
			} else {
				$this->result('',0,'添加我的汽车失败，请稍后重试');
			}
		} else {
			$this->result('',0,$validate->getError());
		}
		
	}
	/**
	 * 我的汽车列表
	 * @return 我已经添加的汽车
	 */
	public function myCarList(){
		$uid = input('get.uid');
		$list = Db::table('yue_user_car')
		       ->where('uid',$uid)
		       ->field('car_brand,car_type,car_license')
		       ->select();
		if(!$list || empty($list)){
			$this->result('',0,'暂无数据');
		} else {
			$this->result($list,1,'获取数据成功');
		}
	}
}

