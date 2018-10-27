<?php 
namespace app\trip\controller;
use app\trip\controller\Personal;
use think\Db;
use MAP\Map;
use think\Controller;
/**
 * 约驾个人中心
 */
class Personal extends Controller
{
	/**
	 * 完善资料
	 * @return [type] [description]
	 *
	 */
	public function add_user(){
		$data = input('post.');
		$uid = input('post.u_id');
		$validate = validate('User');
		if($validate->check($data)){
			$res = Db::table('yue_user')
						->field('u_id,name,phone,status,wx_number')
						->where('u_id',$uid)
						->data(['status'=>'1'])
						->update($data);
			if($res !== false){
				$this->result('',1,'保存成功');
			}else{
				$this->result('',0,'成功失败');
			}
		}else{
			$this->result('',0,$validate->getError());
		}
	}
	/**
	 * 是否完善资料
	 * @return 
	 */
	public function perfect(){
		$uid = input('get.uid');
		$yue_user = Db::table('yue_user')
						->where('u_id',$uid)
						->where('status','1')
						->field('u_id,status,phone,wx_number,name')
						->find();
		if($yue_user){
			$this->result($yue_user,1,'保存成功');
		}else{
			$this->result('',0,'您还没有完善个人信息，请去完善');
		}
	}
	/**
	 * 添加我的汽车
	 * @return [type] [description]
	 *
	 */
	public function addCar(){
		$data = input('post.');
		$plate = input('post.plate');
		$data['plate']= strtoupper($plate);
		$validate = validate('Car');
		if($validate->check($data)){
			$res = Db::table('yue_car')->strict(false)->insert($data);
			if($res){
				$this->result('',1,'添加我的汽车成功');
			} else {
				$this->result('',0,'添加我的汽车失败，请稍后重试');
			}
		}else{
			$this->result('',0,$validate->getError());
		}
	}
	/**
	 * 获取所有的车的品牌
	 * @return  车的品牌
	 */
	public function menu(){
		$car = Db::table("co_car_menu")->field("id,name")->select();
		return $car;
	}
	/**
	 * 获取选中车的车型
	 * @return  车的车型
	 */
	public function type(){
		$brand = input('get.id');
		$type = Db::table('co_car_cate')
				->where('brand',$brand)
				->field('id as t_id,brand,type')
				->group('type')
				->order('t_id asc')
				->select();
		return $type;
	}
	/**
	 * 我的汽车列表
	 * @return 我已经添加的汽车
	 */
	public function car(){
		$uid = input('get.uid');
		$list = Db::table('yue_car')
					->where("uid",$uid)
					->field("plate,brand,type,line")
					->select();
		if(!$list || empty($list)){
			$this->result('',0,'暂无数据');
		}else{
			$this->result($list,1,'获取数据成功');
		}
	}
	/**
	 * 我的汽车删除
	 * @return 
	 */
	public function car_del(){
		$id = input('get.id');
		$list = Db::table('yue_car')->where('id',$id)->delete();
		if($list){
			$this->result('',1,'删除成功');
		}else{
			$this->result('',0,'删除失败');
		}
	}
	/**
	 * 免责声明
	 * @return 
	 */
	public function disclaimer(){
		$list = Db::table('yue_disclaimer')->where('id',1)->select();
		if($list){
			$this->result($list,1,'获取成功');
		}else{
			$this->result('',0,'获取失败');
		}
	}
	
}