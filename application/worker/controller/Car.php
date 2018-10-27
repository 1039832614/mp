<?php 
namespace app\worker\controller;
use app\base\controller\Worker;
use think\Db;

class Car extends Worker
{
	/**
	 * 获取所有的车的品牌
	 * @return [type] [description]
	 */
	public function getBrand(){
		$list = Db::table('co_car_detail')
				->group('brand')
				->field('brand')
				->select();
		return $list;
	}
	/**
	 * 获取某个品牌下的所有车型
	 * @return [type] [description]
	 */
	public function getSeries(){
		$key = input('get.brand');
		$list = Db::table('co_car_detail')
				->where('brand',$key)
				->group('series')
				->field('series')
				->select();
		return $list;
	}
	/**
	 * 获取年限和排量
	 * @return [type] [description]
	 */
	public function getType(){
		$data = input('get.');
		$list = Db::table('co_car_detail')
				->where('series',$data['series'])
				->where('brand',$data['brand'])
				->group('type')
				->field('type')
				->select();
		return $list;
	}
	/**
	 * 获取详情
	 * @return [type] [description]
	 */
	public function getDetail(){
		$data = input('get.');
		$detail = Db::table('co_car_detail')
					->where('type',$data['type'])
					->where('series',$data['series'])
					->where('brand',$data['brand'])
					->limit(1)
					->find();
		return $detail;
	}
}