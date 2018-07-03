<?php
namespace app\base\controller;
use app\base\controller\Base;
use think\Db;
use Geo\Geo;
use Config;

/**
* 
*/
class Bby extends Base
{
	/**
	 * 获取店铺技师
	 */
	public function tnList($sid,$repair)
	{
		// 构建where条件
		$where = ['sid'=>$sid,'cert'=>1,'repair'=>$repair];
		// 获取技师个数
		$count = Db::table('tn_user')->where($where)->count();
		if($count > 0){
			// 获取技师列表
			$list = Db::table('tn_user')->field('name,server,head,wx_head')->where($where)->select();
			$this->result(['count'=>$count,'list'=>$list],1,'获取数据成功');
		}else{
			$this->result('',0,'该店暂无技师');
		}
	}

	/**
	 * 获取推荐店铺
	 * 传入数据lat,lng,page,prefix
	 */
	public function shopList($data,$prefix)
	{
		$geo = new Geo();
    	$hash = $geo->encode_hash($data['lat'],$data['lng']);
    	$hv = substr($hash,0,1);
    	// 分页获取数据
    	$page = $data['page'] ? : 1;
    	$list = Db::table($prefix.'_shop')
    			->alias('sp')
    			->join([$prefix.'_shop_set'=>'st'],'sp.id = st.sid')
    			->field('sid,company,photo,about,serphone,lng,lat')
    			->whereLike('hash_val',$hv.'%')
    			->where('audit_status',2)
    			->page($page,Config::get('page_size'))
				->select();
		foreach ($list as $k => $v) {
			$photo = str_replace(['\\'], ["/"], $v['photo']);
			$list[$k]['photo'] = json_decode($photo,true);
		}
    	if($list){
    		$res = $geo->sortByDistance($list,$data['lat'],$data['lng'],200);
    		$this->result($res,1,'获取数据成功');
    	}else{
    		$this->result('',0,'您所在地区暂无修理厂');
    	}
    	
	}
	
}