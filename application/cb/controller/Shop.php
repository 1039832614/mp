<?php 
namespace app\cb\controller;
use app\base\controller\Bby;
use think\Db;
use Config;
/**
* 汽修厂管理
*/
class Shop extends Bby
{
	/**
	 * 获取服务店铺列表
	 */
	public function getList()
	{
		$data = input('get.');
		$this->shopList($data,'cs');
	}

	/**
	 * 获取服务店铺详情
	 */
	public function getDetail()
	{
		$sid = input('get.sid');
		$data = Db::table('cs_shop')
				->alias('s')
				->join(['cs_shop_set'=>'t'],'s.id = t.sid')
				->field('company,photo,about,serphone,lng,lat,address')
				->where('s.id',$sid)
				->find();
		
		$data['photo'] = str_replace(['\\'], ["/"], $data['photo']);
		$data['photo'] = json_decode($data['photo'],true);
		
		if($data){
			$this->result($data,1,'获取数据成功');
		}else{
			$this->result('',0,'获取数据失败');
		}
	}

	/**
	 * 店铺技师列表
	 */
	public function getTns()
	{
		$sid = input('get.sid');
		// 构建where条件
		$this->tnList($sid,1);
	}

	/**
	 * 获取评论数量
	 */
	public function countComs()
	{
		$sid = input('get.sid');
		// 获取店铺评分
		$shop_star = Db::table('u_comment')->where('sid',$sid)->avg('shop_star'); 
		// 获取技师评分
		$tn_star = Db::table('u_comment')->where('sid',$sid)->avg('tn_star'); 
		// 获取总体评分
		$score = round(($shop_star+$tn_star)/2,1);
		// 返回前端数据
		$data = ['shop_star'=>intval($shop_star),'tn_star'=>intval($tn_star),'score'=>$score];
		$this->result($data,1,'获取数据成功');
	}
	

	/**
	 * 获取车主评论列表
	 */
	public function getComs()
	{
		$sid = input('get.sid');
		$page = input('get.page') ? : 1;
		// 获取每页条数
		$pageSize = Config::get('page_size');
		$count = Db::table('u_comment')->where('sid',$sid)->count();
		$rows = ceil($count / $pageSize);
		// 获取数据列表
		$list = Db::table('u_comment')
				->alias('c')
				->join(['u_user'=>'u'],'c.uid = u.id')
				->field('name,phone,shop_star,head_pic,c.create_time,content')
				->where('sid',$sid)
				->order('c.id desc')
				->page($page, $pageSize)
				->select();
		// 返回给前端
		if($count > 0){
			$this->result(['list'=>$list,'rows'=>$rows],1,'获取成功');
		}else{
			$this->result('',0,'暂无数据');
		}
	}

	/**
	 * 获取店铺上传的服务
	 */
	public function server()
	{
		
	}
}