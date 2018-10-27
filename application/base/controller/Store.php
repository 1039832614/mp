<?php 
namespace app\base\controller;
use app\base\controller\Base;
use Msg\Sms;
use think\Db;
class Store extends Base
{
	function initialize()
	{

	}
	/**
	 * 更新用户经纬度
	 * @param  [type] $data  [description]
	 * @param  [type] $table [description]
	 * @return [type]        [description]
	 */
	public function updateUserCoord($data,$table)
	{
		$uid = $data['uid'];
		unset($data['uid']);
		$res = Db::table($table)
				->where('id',$uid)
				->update($data);
		if($res !== false) {
			$this->result('',1,'更新成功');
		} else {
			$this->result('',0,'更新失败');
		}
	}
	/**
	 * 查询用户的经纬度
	 */	
	public function getUserLocation($uid){
	    $user_location = Db::table('st_user')
	    	                 ->where('id',$uid)
	    	                 ->field('lat,lng')
	    	                 ->find();
	    	return $user_location; 
	}
	/**
	 * 根据排序
	 * @return [type] [description]
	 *
	 */
	public function sort($list,$count,$mold){
		//把距离最小的放到前面
		//双重for循环, 每循环一次都会把一个最大值放最后
		for ($i = 0; $i < $count - 1; $i++) 
		{	
			//由于每次比较都会把一个最大值放最后, 所以可以每次循环时, 少比较一次
			for ($j = 0; $j < $count - 1 -  $i; $j++) 
			{	
				if ($list[$j]["$mold"] > $list[$j + 1]["$mold"]) 
				{
					$tmp = $list[$j];
					$list[$j] = $list[$j + 1];
					$list[$j + 1] = $tmp;
				}
			}
		}
		return $list;
	}
}