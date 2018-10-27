<?php 
namespace app\trip\controller;
use app\trip\controller\Main;
use think\Db;
use MAP\Map;
use Geo\Geo;
use think\Controller;
/**
 * 约驾首页
 */
class Main extends Controller
{
	/**
	 * 活动列表    200公里内的活动列表
	 * @return [type] [description]
	 *
	 */
	public function index(){
		//触发往期活动修改状态
		$this->endList();
		$page = input('get.page') ? : 1;
		$uid = input('get.uid');
		$pageSize = 10;	
		//总条数
		$counts = Db::table('yue_activity')
					->where('static',"1")
					->where('red_number','>','0')
					->where('deadline','>',date('Y-m-d H:i:s', time()))
					->count();
		$rows = ceil($counts/$pageSize);
		$list = Db::table('yue_activity')
					->alias('a')
					->join('yue_user u','a.uid = u.u_id')
					->field('id,u.u_id,head_image,nikename,sex,title,pv,path,start_time,motorcycle,pic,red_number,a.lng,a.lat,static')
					->where('static',"1" )
					->where('red_number','>','0')
					->where('deadline','>',date('Y-m-d H:i:s', time()))
					->page($page,$pageSize)
					->select();
		$geo = new Geo;
		foreach ($list as $key => $value) {
			//用户经纬度
			$location = $this->getUserLocation($uid);
			//获得用户与始发地距离
			$distance  = $geo->getDistance($location['lat'],$location['lng'],$list[$key]['lat'],$list[$key]['lng']);
			$list[$key]['distance'] = $distance;
			//活动倒计时
			$list[$key]['countTime'] = $this->countTime($list[$key]['id']);
			//图片
			if(!empty($list[$key]['pic'])){
				$list[$key]['pic'] = json_decode($list[$key]['pic']);
			}else{
				$list[$key]['pic'] = array();
			}
		}
		// 根据距离排序
		$count = count($list);
		$list = $this->sort($list,$count);
		if($list){
			$this->result(['list'=>$list,'rows'=>$rows],1,'获取成功');
		}else{
			$this->result('',0,'暂无数据');
		}
	}
	/**
	 * 约驾报名倒计时
	 * @return $data
	 */
	public function countTime($aid){
		//截至时间减发布时间
		$yue = Db::table('yue_activity');
		$deadline = $yue->where('id',$aid)->value('deadline');
		$ctime = strtotime($deadline)-time();
		$d = floor($ctime/86400);
		$h = floor(($ctime % (3600*24)) / 3600);
		$m = floor((($ctime % (3600*24)) % 3600) / 60);
		if($d>'0') {
    		return $d.'天'.$h.'小时';
		} else {
    		if($h >'0') {
      			return $h.'小时'.$m.'分钟';
   			} else {
    	    	if($m >'0'){
    	    		return $m.'分钟';
    	    	}else{
    	    		return '报名已截止';
    	    	}
   			}
		}
	}
	/**
	 * 活动列表详情    200公里内的活动列表
	 * @return [type] [description]
	 *
	 */
	public function detail(){
		$aid = input('get.aid');
		$uid = input('get.uid');
		//判断是否报名
		$tp = Db::table('yue_participant')
				->where('uid',$uid)
				->where('aid',$aid)
				->find();
		$list = Db::table('yue_activity')
				->alias('a')
				->join('yue_user u','a.uid = u.u_id')
				->field('id,uid,head_image,sex,nikename,time,path,title,start_time,end_time,red_number,motorcycle,content,thronheim,pic')
				->where('id',$aid)
				->select();
		$act = $list['0'];
		if(!empty($act['pic'])){
			$act['pic'] = json_decode($act['pic']);
		}else{
			$act['pic'] = array();
		}
		//获取已经参加的人的头像
		$taker = Db::table('yue_participant')->alias('p')
					->join('yue_user u','u.u_id = p.uid')
					->where('aid',$aid)
					->field('u.head_image,p.phone,wx_number')
					->select();
		if($taker){
			foreach ($taker as $key => $value) {
				$head_image[] = $value['head_image'];
				$phone[] = $value['phone'];
				$wx_number[] = $value['wx_number'];
			}
			$act['head_image1'] = $head_image;
			$act['phone1'] = $phone;
			$act['wx_number1'] = $wx_number;
		}else{
			$act['head_image1'] = array();
			$act['phone1'] = array();
			$act['wx_number1'] =array();
		}
		//倒计时
		$act['countTime'] = $this->countTime($act['id']);
		//是否报名
		if($tp){
			$act['tp'] = 1;
		}else{
			$act['tp'] = 0;
		}
		//是否是发布者
		$user = Db::table('yue_activity')
					->where('id',$aid)
					->where('uid',$uid)
					->find();
		$num =Db::table('yue_user')
				  ->where('u_id',$act['uid'])
				  ->field('phone,wx_number')
				  ->find();
		//联系方式是否显示
		$act['is_phone'] = $num['phone'];
		//微信号
		$act['wx_number'] = $num['wx_number'];
		if($user){
			$act['user'] = 1;
		}else{
			$act['user'] = 0;
		}
		if($act){
			$list = Db::table('yue_activity')->where('id',$aid)->setInc('pv');
			$this->result($act,1,'获取成功');
		}else{
			$this->result('',0,'暂无数据');
		}
	}

	/**
	 * 根据距离排序
	 * @return [type] [description]
	 *
	 */
	public function sort($list,$count){
		//把距离最小的放到前面
		//双重for循环, 每循环一次都会把一个最大值放最后
		for ($i = 0; $i < $count - 1; $i++) 
		{	
			//由于每次比较都会把一个最大值放最后, 所以可以每次循环时, 少比较一次
			for ($j = 0; $j < $count - 1 -  $i; $j++) 
			{	
				if ($list[$j]['distance'] > $list[$j + 1]['distance']) 
				{
					$tmp = $list[$j];
					$list[$j] = $list[$j + 1];
					$list[$j + 1] = $tmp;
				}
			}
		}
		return $list;
	}
	/**
	 * 查询用户的经纬度
	 */	
	public function getUserLocation($uid){
	    $user_location = Db::table('yue_user')
	    	                 ->where('u_id',$uid)
	    	                 ->field('lat,lng')
	    	                 ->find();
	    	return $user_location; 
	}
	/**
	 * 在点击首页触发往期风采 
	 * @return [type] [description] static  3
	 *
	 */
	public function endList(){
		$time = date('Y-m-d H:i:s', time());
		$act = Db::table('yue_activity')
				   ->where('end_time','<',$time)
				   ->update(['static' => '3']);
		}
	/**
	 * 往期风采
	 * @return [type] [description]
	 *
	 */
	public function past(){
		$page = input('get.page') ? : 1;
		$pageSize = 7;
		//总条数
		$count = Db::table('yue_activity')->where('static',"3")->count();
		$rows = ceil($count/$pageSize);
		$list = Db::table('yue_activity')
					->alias('a')
					->join('yue_user u','a.uid = u.u_id')
					->field('id,head_image,nikename,sex,title,path,start_time,end_time,pic,pv')
					->where('static',"3")
					->order('end_time desc')
					->page($page,$pageSize)
					->select();
		foreach ($list as $key => $value) {
			$list[$key]['count'] =  Db::table('yue_comment')->where('a_id',$list[$key]['id'])->count();
			if(!empty($list[$key]['pic'])){
				$list[$key]['pic'] =json_decode($list[$key]['pic']);
			}else{
				$list[$key]['pic'] =array();
			}
		}
		//时间最近的
		if($list){
			$this->result(['list'=>$list,'rows'=>$rows],1,'获取成功');
		}else{
			$this->result('',0,'暂无数据');
		}
	}
	/**
	 * 往期风采详情
	 * @return [type] [description]
	 *
	 */
	public function pastDetail(){
		$id = input('get.id');
		$detail = Db::table('yue_activity')
					->alias('a')
					->join('yue_user u','a.uid = u.u_id')
					->where('id',$id)
					->field('head_image,nikename,sex,title,path,start_time,end_time,pic,thronheim,number,time,content')
					->find();
					if(!empty($detail['pic'])){
						$detail['pic'] = json_decode($detail['pic']);
					}else{
						$detail['pic'] = array();
					}
		if($detail > 0){
			$list = Db::table('yue_activity')->where('id',$id)->setInc('pv');
			$this->result($detail,1,'获取成功');
		}else{
			$this->result('',0,'暂无数据');
		}				
	}
	/**
	 * 往期风采评论
	 * @return [type] [description]
	 *
	 */
	public function  discuss(){
		$comment = input('post.comment');
		$uid = input('post.uid');
		$aid = input('post.aid');
		if(!empty($comment)){
			$data = [
				'a_id'   => $aid,
				'u_id'   =>$uid,
				'comment'=>$comment,
			];
			$comment = Db::table('yue_comment')->insert($data);
			if($comment){
				$this->result($comment,1,'评论成功');
			}else{
				$this->result('',0,'评论失败');
			}
		}else{
			$this->result('',0,'评论不能为空');
		}
	}
	/**
	 * 往期风采某个活动评论列表
	 * @return [type] [description]
	 *
	 */
	public function disList(){
		$aid = input('get.aid');
		$list = Db::table('yue_comment')
					->alias('c')
					->join('yue_user u','c.u_id = u.u_id')
					->where('a_id',$aid)
					->field('comment,time,head_image,nikename')
					->order('time desc')
					->select();
		if($list){
			$this->result($list,1,'获取数据成功');
		}else{
			$this->result('',0,'暂无数据');
		}
	}

}