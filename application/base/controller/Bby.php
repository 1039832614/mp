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
	// public function shopList($data,$prefix)
	// {
	// 	$geo = new Geo();
 //    	$hash = $geo->encode_hash($data['lat'],$data['lng']);
 //    	$hv = substr($hash,0,1);
 //    	// 分页获取数据
 //    	//$page = $data['page'] ? : 1;
 //    	$list = Db::table($prefix.'_shop')
 //    			->alias('sp')
 //    			->join([$prefix.'_shop_set'=>'st'],'sp.id = st.sid')
 //    			->field('sid,company,photo,about,serphone,lng,lat')
 //    			->whereLike('hash_val',$hv.'%')
 //    			->where('audit_status',2)
 //    			//->page($page,Config::get('page_size'))
	// 			->select();
	// 	foreach ($list as $k => $v) {
	// 		$photo = str_replace(['\\'], ["/"], $v['photo']);
	// 		$list[$k]['photo'] = json_decode($photo,true);
	// 	}
 //    	if($list){
 //    		$res = $geo->sortByDistance($list,$data['lat'],$data['lng'],200);
 //    		$this->result($res,1,'获取数据成功');
 //    	}else{
 //    		$this->result('',0,'您所在地区暂无修理厂');
 //    	}
    	
	// }
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
    	//$page = $data['page'] ? : 1;
    	$list = Db::table($prefix.'_shop')
    			->alias('sp')
    			->leftjoin([$prefix.'_shop_set'=>'st'],'sp.id = st.sid')
    			// 2018 09 04 ，链接 u_card ， 添加 服务次数 service_num, 关注数量 degree , 星级 xing , 标签 major
    			->leftjoin('u_card cd','sp.id = cd.sid')
                ->leftjoin('u_comment d','sp.id = d.sid')
    			->field('st.sid,company,photo,about,serphone,lng,lat,sp.service_num,count(distinct(cd.uid)) degree,ceil(avg(d.shop_star)) xing,major')
    			->order('xing DESC')
    			->whereLike('hash_val',$hv.'%')
    			->where('audit_status',2)
    			->group('company')
    			//->page($page,Config::get('page_size'))
				->select();
		// arsort($list);
		foreach ($list as $k => $v) {
			$photo = str_replace(['\\'], ["/"], $v['photo']);
			$list[$k]['photo'] = json_decode($photo,true);

            // 如果星星为空 测试为
            // if(empty($list[$k]['xing'])){
            //     $list[$k]['xing'] = 3;
            // }
		}
        
        foreach ($list as $key => $value) {
            $list[$key]['lable'] = $this->match_chinese($value['major']);
        }

    	if($list){
    		$res = $geo->sortByDistance($list,$data['lat'],$data['lng'],200);
    		$this->result($res,1,'获取数据成功');
    	}else{
    		$this->result('',0,'您所在地区暂无修理厂');
    	}
    	
	}


	function match_chinese($chars,$encoding='utf8')
    {
        $pattern =($encoding=='utf8')?'/[\x{4e00}-\x{9fa5}a-zA-Z0-9]/u':'/[\x80-\xFF]/';
        // 获取所有字符
        $result = preg_replace($pattern,' ',$chars);
        // 切割成数组
        $result = explode(' ', $result);
        // 将字符全部替换成逗号
        $result = str_replace($result,',',$chars);
        // 切割字符
        $result = explode(',',$result);
        // 取出前三个
        $result = array_slice($result,0,3);
        // 删除空数据
        $result = array_filter($result);
        // if(empty($result)) $result = ['暂无标签'];
        // print_r($result);die;
        return $result;
    } 	
}