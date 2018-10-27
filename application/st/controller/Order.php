<?php 
namespace app\st\controller;
use app\base\controller\Store;
use think\Db;

class Order extends Store
{
	/**
	 * 进程初始化
	 * @return [type] [description]
	 */
	public function initialize()
	{

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
	 * 预约
	 * @return [type] [description]
	 */
	public function appoint()
	{
		$data = input('post.');
		$validate = validate('Info');
		if($validate->check($data)) {
			$stock_number = Db::table('st_commodity_detail')
							->where('id',$data['specid'])
							->value('stock_number');
			//判断库存和用户预约的数量
			if($data['number'] < $stock_number){
				$data['ordernum'] = time().rand(10000,99999);
				$data['create_time'] = time();
				$re = Db::table('st_order')
						->strict(false)
						->insert($data);
				if($re) {
					$this->result('',1,'预约成功');
				} else {
					$this->result('',0,'预约失败');
				}
			} else {
				$this->result('',0,'库存不足');
			}
		} else {
			$this->result('',0,$validate->getError());
		}
	}
	/**
	 * 预约单列表
	 * @return [type] [description]
	 */
	public function orderList($uid,$page,$status)
	{
		$pageSize = 3;
		$count = Db::table('st_order')
					->where([
						'uid' => $uid,
						'status' => $status
					])
					->count();
		$rows = ceil($count / $pageSize);
		$list = Db::table('st_order')
					->alias('o')
					->join("st_commodity_detail d",'d.id = o.specid')
					->join('st_commodity c','c.id = o.cid')
					->where([
						'uid' => $uid,
						'o.status' => $status
					])
					->order('o.create_time desc')
					->page($page,$pageSize)
					->field('c.id as cid,o.id as oid,o.create_time,c.name,o.number,d.activity_price,o.status,c.pic,c.ifsigning,c.sid')
					->select();
		foreach ($list as $key => $value) {
			if($list[$key]['ifsigning'] == 0) {
				$list[$key]['shop'] = Db::table('st_shop')
						->alias('s')
						->join('st_shop_set t','s.id = t.sid')
						->where('s.id',$list[$key]['sid'])
						->field('s.company,t.serphone as phone,t.province,t.city,t.county,t.address,t.lat,t.lng')
						->find();
			} else {
				$list[$key]['shop'] = Db::table('cs_shop')
						->alias('s')
						->join('cs_shop_set t','s.id = t.sid')
						->where('s.id',$list[$key]['sid'])
						->field('s.company,t.serphone as phone,t.province,t.city,t.county,t.address,t.lat,t.lng')
						->find();
			}
		}
		foreach ($list as $key => $value) {
			$date = date("m月d日 H:i",$value['create_time']);
			$list[$key]['create_time'] = $date;
		}
		if($list) {
			$this->result(['list'=>$list,'rows'=>$rows],1,'获取数据成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
	/**
	 * 所有订单
	 * @return [type] [description]
	 */
	public function allList()
	{
		//获取当前用户的id
		$uid = input('get.uid');
		$page = input('get.page')? : 1;
		$status = ['0','1','2','3'];
		return $this->orderList($uid,$page,$status);
	}
	/**
	 * 待评价
	 * @return [type] [description]
	 */
	public function undList()
	{
		//获取当前用户的id
		$uid = input('get.uid');
		$page = input('get.page')? : 1;
		$status = 1;
		return $this->orderList($uid,$page,$status);
	}
	/**
	 * 进行中
	 * @return [type] [description]
	 */
	public function ingList()
	{
		//获取当前用户的id
		$uid = input('get.uid');
		$page = input('get.page')? : 1;
		$status = 0;
		return $this->orderList($uid,$page,$status);
	}
	/**
	 * 评价
	 */
	public function evaluate()
	{
		$data = input('post.');
		$oid = input('post.oid');
		unset($data['oid']);
		$validate = validate('Eva');
		if($validate->check($data)) {
			$data['create_time'] = time();
			Db::startTrans();
			$re = Db::table('st_evaluate')
					->strict(false)
					->insert($data);
			$res = Db::table('st_order')
						->where('id',$oid)
						->setField('status',3);
			if($re && $res){
				Db::commit();
				$this->result('',1,'提交成功');
			} else {
				Db::rollback();
				$this->result('',0,'提交失败');
			}
		} else {
			$this->result('',0,$validate->getError());
		}
	}
}