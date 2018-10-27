<?php 
namespace app\trip\controller;
use app\base\controller\Travel;
use think\Db;
use MAP\Map;
use Geo\Geo;
/**
 * 约驾
 */
class Verdict extends Travel
{

	/**
	 * 获取轮播图列表
	 */
	public function getBannerList()
	{
		$this->bannerList(2);//正式测试时，改为 gid 2
	}

	/**
	 * 获取轮播图详情
	 */	
	public function getBannerDetail()
	{
		$id = input('get.id');
		$this->bannerDetail($id);
	}
	

}