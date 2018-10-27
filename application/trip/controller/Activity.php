<?php 
namespace app\trip\controller;
use app\trip\controller\Activity;
use think\Db;
use MAP\Map;
use think\File;
use think\Controller;
/**
 * 约驾活动信息
 */
class Activity extends Controller
{
	/**
	* 发布约驾活动
	 *@return $msg 成功或者失败
	 */
	public function activity(){
		$data = input("post.");
		//获取用户id
		$uid = $data['uid'];
		//时间判断
		$deadline = substr($data['deadline'],0,strlen($data['deadline'])-3).":00:00";
		if(time() > strtotime($deadline)){
			$this->result('',0,'截止时间不能早于当前时间');
		}
		if(strtotime($deadline) > strtotime($data['start_time'])){
			$this->result('',0,'出发时间不能早于截止时间');
		}
		if(strtotime($data['start_time']) > strtotime($data['end_time'])){
			$this->result('',0,'回来时间不能早于出发时间');
		}
		$validate = validate('Act');
		if($validate->check($data)){
			//始发地
			$data['thronheim'] = input('post.thronheim');
			//获取经纬度
			$maps = new Map;
			$data['lng'] =  $maps->maps($data['thronheim'])['lng'];
			$data['lat'] =  $maps->maps($data['thronheim'])['lat'];
			// 可约人数
			$data['red_number'] = $data['number'];
			//活动发布时间
			$data['time'] = date('Y-m-d H:i:s', time());
			if(!empty($data['pic'])){
				$data['pic']= json_encode($data['pic']);
			}else{
				$data['pic']= array();
			}
			//添加活动约驾
			$yue_activity = Db::table('yue_activity')->strict(false)->insert($data);
			if($yue_activity){
				$this->result($yue_activity,1,'发布成功,可在‘我的约驾’中查看');
			}else{
				$this->result('',0,'发布约驾失败，请稍后尝试');
			}
		}else{
			$this->result('',0,$validate->getError());
		}
	}

	/**
	 *调取我的汽车
	 *@return $msg 成功或者失败
	 */
	public function myCar(){
		$uid = input('get.uid');
		$car = DB::table('yue_car')
				->where('uid',$uid)
				->field("id,plate,brand,type")
				->select();
		if($car){
			$this->result($car,1,'获取成功');
		}else{
			$this->result('',0,"暂未添加我的汽车");
		}
	}
	/**
	 * 是否是邦保养VIP
	 * @return 
	 */
	public function vip($uid){
		//当前约驾人手机号
		$phone = Db::table('yue_user')->where('u_id',$uid)->value('phone');
		//查询u_user表中是否存在此电话
		$u_user = Db::table('u_user')->where('phone',$phone)->field('id')->find();
		$u_uid = $u_user['id'];
		//在u_card中查找这个用户是否购买了邦保养卡
		$u_card = Db::table('u_card')->where('uid',$u_uid)
					->where('pay_status','1')
					->field('uid,pay_status')
					->select();
		if($u_card){
				return 1;
			}else{
				return 0;
			}
	}
	/**
	 * 我的约驾列表  0-未审核 1-审核通过 2-审核未通过 3-往期活动 4-完成
	 * @return $data 用户发布的约驾信息 
	 */
	public function yuelist(){
		$uid = input('get.uid');
		$page = input('get.page') ? : 1;
		$pageSize = 10;	
		//总条数
		$counts = Db::table('yue_activity')
					->where('uid',$uid)
					->count();
		$rows = ceil($counts/$pageSize);
		$yue_list = Db::table('yue_activity')
					->where('uid',$uid)
					->field('id,title,path,time,static')
					->order('time desc')
					->page($page,$pageSize)
					->select();
		foreach ($yue_list as $key => $value) {
			if($yue_list[$key]['static']=='0'){
			$yue_list[$key]['static_name'] = '待审核';
		}else if($yue_list[$key]['static']=='1'){
			$yue_list[$key]['static_name'] = '已审核';
		}else if($yue_list[$key]['static']=='2'){
			$yue_list[$key]['static_name'] = '审核未通过';
		}else if($yue_list[$key]['static']=='3'){
			$yue_list[$key]['static_name'] = '已结束';
		}else if($yue_list[$key]['static']=='4'){
			$yue_list[$key]['static_name'] = '人数已满';
		}
		}
		if($yue_list){
			$this->result(['yue_list'=>$yue_list,'rows'=>$rows],1,'获取数据成功');
		}else{
			$this->result('',0,'暂无数据');
		}
	}
	/**
	 * 获取我的约驾活动详情
	 * @return  发起者的姓名，性别，头像，约驾活动创建时间,路线，浏览量，车型，活动详情，已参与人数，总人数，报名倒计时，
	 */
	public function yueDetails(){
		$aid = input('get.id');
		$list = Db::table('yue_activity ac')
					->join('yue_user us','ac.uid = us.u_id')
					->field('uid,head_image,sex,nikename,time,title,path,start_time,end_time,red_number,motorcycle,content,thronheim,pic,static,wx_number')
					->where('ac.id',$aid)
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
			$act['wx_number1'] = array();
		}
		return $act;
	}
	/**
	 * 上传约驾图片
	 * @return 
	 */
	public function image()
	{
		return upload("image","trip","https://mp.ctbls.com");
	}


}


