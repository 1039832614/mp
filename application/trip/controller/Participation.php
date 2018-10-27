<?php 
namespace app\trip\controller;
use app\trip\controller\Participation;
use think\Db;
use MAP\Map;
use think\Controller;
/**
 * 参与活动
 * @return [type] [description]
 */
class Participation extends Controller
{	

/**
 * 参与活动报名
 * @return [type] [description]
 */
	public function takePart(){
		$aid = input('get.id');
		$uid = input('get.uid');
		$user = Db::table('yue_user')
					->where('u_id',$uid)
					->field('name,wx_number,phone')
					->find();
		if(!empty($user['phone'])){
			//是否报名
				$tp = Db::table('yue_participant')
						->where([
							'uid'=>$uid,
							'aid'=>$aid,
							'phone'=>$user['phone'],
						])
						->find();
				if($tp){
					$this->result('',0,"已报名");
				}
			//查询可约人数，标题
			$activity = Db::table('yue_activity')
							->where('id',$aid)
							->field('red_number,title,uid')
							->find();
			if($activity['red_number']>0){
				$num = Db::table('yue_activity')
							->where('id',$aid)
							->setDec('red_number');
				$take = Db::table('yue_participant')
							->insert([
									'phone'=>$user['phone'],
									'aid'=>$aid,
									'isTake'=>'1',
									'uid'=>$uid,
								]);
					//发送消息
				$arr = [
					'title'   =>'活动参与通知',
					'time'    => date('Y-m-d H:i:s', time()),
					'content' => '您发布的“'.$activity['title'].'”活动,“'.$user['name'].'”参与了该活动,其方式联系:微信号：“'.$user['wx_number'].'”,用户手机号:'.$user['phone'].'。',
					'static'  => '0',
					'uid'     => $activity['uid'],
					];
					//给活动发布人发送消息
			$list = Db::table('yue_actnews')->insert($arr);
			$this->result('',1,"参与成功");
			}else{
				$this->result($phone,0,"参与人数已满");
			}	
		}else{
			$this->result('',0,"请先完善资料");
		}
	}
/**
 * 我参与的约驾活动的列表
 * @return [type] [description]
 */
	public function myTakePart()
	{	
		$page = input('get.page') ? : 1;
		$pageSize = 10;	
		$uid = input('get.uid');
		//总条数
		$counts =Db::table('yue_participant')
			       	->alias('p')
			       	->join('yue_activity a','a.id = p.aid')
			       	->where('p.uid',$uid)
					->count();
		$rows = ceil($counts/$pageSize);
		$res = Db::table('yue_participant')
			       ->alias('p')
			       ->join('yue_activity a','a.id = p.aid')
			       ->where('p.uid',$uid)
			       ->field('a.id,title,path,start_time,static')
			       ->page($page,$pageSize)
			       ->order('start_time desc')
			       ->select();
		if($res){
			$this->result(['res'=>$res,'rows'=>$rows],1,'获取数据成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
/**
 * 取消参与活动
 * @return [type] [description]
 */
	public function delPart(){
		$aid = input('get.aid');
		$uid = input('get.uid');
		$myTake = Db::table('yue_participant')
			          ->where('aid',$aid)
			          ->where('uid',$uid)
			          ->delete();
		if($myTake){
			$activity = Db::table('yue_activity')->where('id',$aid)->setInc('red_number');
			$act = Db::table('yue_activity')->where('id',$aid)->field('uid,title')->find();
			$arr = [
				'title'   =>'取消参与通知',
				'time'    => date('Y-m-d H:i:s', time()),
				'content' => '某人取消参与了“'.$act['title'].'”活动,请及时联系其他人参与',
				'static'  => '0',
				'uid'     => $act['uid'],
			];
			//给活动发布人发送消息
			$list = Db::table('yue_actnews')->insert($arr);
			$this->result('',1,'取消参与成功');
		}else{
			$this->result('',0,'取消参与失败');
		}
	}
	


}