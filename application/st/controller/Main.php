<?php 
namespace app\st\controller;
use app\base\controller\Store;
use think\Db;
use Geo\Geo;
use Config;

class Main extends Store
{
	public function initialize()
	{
		$this->uid = input('get.uid');
		$this->page = input('get.page')? : 1;
	}
	/**
	 * 搜索商品
	 */
	public function selectGoods($uid,$key,$mold)
	{
		$list = Db::table('st_commodity')
				->alias('c')
				->join('st_commodity_detail d','d.cid = c.id')
				->where([
					['c.name','like',"%$key%"],
					['c.s_state','=',1]
				])
				->group('c.id')
				->field('c.name,c.id,c.pic,c.stocknum,c.virtualnum,d.market_price,d.activity_price,c.ifsigning,c.sid')
				->select();
		$geo = new Geo;
		foreach ($list as $key => $value) {
			if($list[$key]['ifsigning'] == 0) {
				$list[$key]['shop'] = Db::table('st_shop')
						->alias('s')
						->join('st_shop_set t','s.id = t.sid')
						->where('s.id',$list[$key]['sid'])
						->field('s.company,t.province,t.city,t.county,t.address,t.lat,t.lng')
						->find();
			} else {
				$list[$key]['shop'] = Db::table('cs_shop')
						->alias('s')
						->join('cs_shop_set t','s.id = t.sid')
						->where('s.id',$list[$key]['sid'])
						->field('s.company,t.province,t.city,t.county,t.address,t.lat,t.lng')
						->find();
			}
			//用户经纬度
			$location = $this->getUserLocation($uid);
			//获取用户和维修厂的距离
			$list[$key]['distance'] = $geo->getDistance($location['lat'],$location['lng'],$list[$key]['shop']['lat'],$list[$key]['shop']['lng']);
		}
		//根据距离排序
		$count = count($list);
		$list = $this->sort($list,$count,$mold);
		if($list) {
			$this->result($list,1,'获取数据成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
	/**
	 * 搜索产品，距离排序
	 * @return [type] [description]
	 */
	public function selDistance()
	{
		$uid = input('post.uid');
		$key = input('post.key');
		return $this->selectGoods($uid,$key,'distance');
	}
	/**
	 * 搜索产品，价格排序
	 * @return [type] [description]
	 */
	public function selMoney()
	{
		$uid = input('post.uid');
		$key = input('post.key');
		return $this->selectGoods($uid,$key,'activity_price');
	}
	/**
	 * 首页产品列表
	 * 主图，维修厂名，距离，产品名称，优惠价，市场价，已售数量
	 */
	public function goodsList($sign,$page,$table,$table_set)
	{
		$pageSize = 5;
		$count = Db::table('st_commodity')
				 ->where([
				     'status'=>1,
				 	's_state' => 1,
				 	'ifsigning' => $sign,
				 ])
				 ->count();
		$rows = ceil($count / $pageSize);
		$list = Db::table('st_commodity')
				->alias('c')
				->join('st_commodity_detail d','d.cid = c.id')
				->join("$table s",'c.sid = s.id')
				->join("$table_set t",'t.sid = c.sid')
				->where([
                    'status'=>1,
					's_state' => 1,
					'ifsigning' => $sign
				])
				->order('c.id desc')
				->page($page,$pageSize)
				->field('c.name,c.id,c.pic,c.stocknum,c.virtualnum,d.market_price,d.activity_price,s.company,t.lng,t.lat')
				->group('c.id')
				->select();
		$geo = new Geo;
		foreach ($list as $key => $value) {
			//用户经纬度
			$location = $this->getUserLocation($this->uid);
			//获取用户和维修厂的距离
			$list[$key]['distance'] = $geo->getDistance($location['lat'],$location['lng'],$list[$key]['lat'],$list[$key]['lng']);
		}
		//根据距离排序
		$count = count($list);
		$list = $this->sort($list,$count,'distance');
		if($list) {
			$this->result(['list'=>$list,'rows'=>$rows],1,'获取列表成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
	/**
	 * 仲达精选产品
	 * @return [type] [description]
	 */
	public function signList()
	{
		return $this->goodsList(1,$this->page,"cs_shop","cs_shop_set");
	}
	/**
	 * 其他产品
	 * @return [type] [description]
	 */
	public function unSignList()
	{
		return $this->goodsList(0,$this->page,"st_shop","st_shop_set");
	}
	/**
	 * 商品详情
	 */
	public function goodsDetail()
	{
		$id = input('get.id');
		//产品信息
		$goods = Db::table('st_commodity')
					->where('id',$id)
					->field('id,name,pic,sid,detail,virtualnum,stocknum,ifsigning')
					->find();
		//产品规格
		$goodsDetail = Db::table('st_commodity_detail')
						->where('cid',$id)
						->field('id as specid,standard,standard_detail,sell_number,stock_number,market_price,activity_price')
						->select();
		if($goods['ifsigning'] == 1) {
			//签约维修厂
			$shop = Db::table('cs_shop')
					->alias('c')
					->join('cs_shop_set s','s.sid = c.id')
					->where('c.id',$goods['sid'])
					->field('c.company,s.photo,s.lng,s.lat,s.province,s.city,s.county,s.address,s.about as detail,s.serphone')
					->find();
		} else {
			//合作维修厂
			$shop = Db::table('st_shop')
					->alias('c')
					->join('st_shop_set s','s.sid = c.id')
					->where('c.id',$goods['sid'])
					->field('c.company,s.photo,s.detail,
						s.lng,s.lat,s.province,s.city,s.county,s.address,s.serphone')
					->find();
		}
		$shop['photo'] = json_decode($shop['photo']);
		$geo = new Geo;
		//用户经纬度
		$location = $this->getUserLocation($this->uid);
		//获取用户和维修厂的距离
		$shop['distance'] = $geo->getDistance($location['lat'],$location['lng'],$shop['lat'],$shop['lng']);
		if($goods || $goodsDetail || $shop) {
			$this->result(['goods'=>$goods,'goodsDetail'=>$goodsDetail,'shop'=>$shop],1,'获取数据成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
	/**
	 * 获取商品评价
	 */
	public function getEvaluate()
	{
		$id = input('get.id');
		$evaluate = Db::table('st_evaluate')
					->alias('e')
					->join('st_user u','u.id = e.uid')
					->where([
						'cid' => $id,
						'isshow' => 2
					])
					->field('u.name,u.nick_name,u.head_pic,e.create_time,e.content,e.class')
					->select();
		foreach ($evaluate as $key => $value) {
			$evaluate[$key]['create_time'] = date('Y-m-d H:i:s',$evaluate[$key]['create_time']);
		}
		$count = count($evaluate);
		if($count > 0){
			$this->result(['evaluate'=>$evaluate,'count'=>$count],1,'获取数据成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
}