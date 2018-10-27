<?php 
namespace app\trip\controller;
use app\trip\controller\Updata;
use think\Db;
use think\File;
use think\Controller;
/**
 * 约驾修改
 */
class Updata extends Controller
{
/**
	 * 我的约驾活动信息修改
	 * @return $data 获取约驾信息
	 */
	public function actlist(){
		$aid = input('get.aid');
		$list = Db::table('yue_activity')
					->field('id,title,path,start_time,end_time,number,deadline,motorcycle,content,thronheim,pic')
					->where('id',$aid)
					->select();
		foreach ($list as $key => $value) {
			if(!empty($list[$key]['pic'])){
				$list[$key]['pic'] = json_decode($list[$key]['pic']);
			}else{
				$list[$key]['pic'] = array();
			}
		}
		return $list;
	}
	//执行修改
	public function yueUpdate(){
		$aid = input('post.aid');
		$data = input('post.');
		$num = Db::table('yue_activity')->where('id',$aid)->field('number,red_number')->find();
		if($num['number'] > $data['number']){
			$c = $num['number'] - $num['red_number'];
			if($data['number'] >= $c){
				$a = $num['number'] - $data['number'];
				$num['red_number']  = $num['red_number']- $a;
				$num_add = Db::table('yue_activity')->where('id',$aid)->update(['red_number'=>$num['red_number']]);
			}else{
				$this->result('',0,'总人数不能小于已参加人数');
			}
		}else{
			$b = $data['number'] - $num['number'];
			$num['red_number'] = $num['red_number'] + $b;
			$num_add = Db::table('yue_activity')->where('id',$aid)->update(['red_number'=>$num['red_number']]);
		}
		$validate = validate('Act');
		if($validate->check($data))
		{
			if(!empty($data['pic'])){
				$data['pic']= json_encode($data['pic']);
			}else{
				$data['pic']= array();
			}
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
		$res = Db::table('yue_activity')
				   ->where('uid',$data['uid'])
				   ->where('id',$aid)
				   ->strict(false)
				   ->update($data);
		//判断是否有参与者
			$part = Db::table('yue_participant')
				    ->where('aid',$aid)
				    ->where('isTake',1)
				    ->field('uid')
				    ->select();
			if(!empty($part)){
				if($res){
					$this->result('',2,'修改成功,是否提示活动参与者');
				}else{
					$this->result('',0,'修改失败');
				}
			}else{
				if($res){
					$this->result('',1,'修改成功');
				}else{
					$this->result('',0,'修改失败');
				}
			}
		}else{
	   			$this->result('',0,$validate->getError());
	   	}
	}
	/**
	 * 我的约驾活动信息修改用户是否发送活动修改通知
	 * @return $msg 成功或者失败
	 */
	public function news()
	{
		$aid = input('get.aid');
		//向参与活动者发送活动取消通知
		$part = Db::table('yue_participant')
				    ->where('aid',$aid)
				    ->field('uid')
				    ->select();
		foreach ($part as $key => $value) {
			$arr[] =$part[$key]['uid'];
		}
		$title = Db::table('yue_activity')
					->where('id',$aid)
					->value('title');
		foreach ($arr as $k => $v) {
				$arr1 = [
					'title'   =>'活动更改通知',
					'time'    => date('Y-m-d H:i:s', time()),
					'content' => '您参与的“'.$title.'”活动，发布者更改了部分活动内容，请及时查看，如有什么异议请联系发布者。',
					'static'  => '0',
					'uid'     => $v,
				];
				$list = Db::table('yue_actnews')->insert($arr1);
		}
		if($list){
			$this->result('',1,'发送成功');
		}else{
			$this->result('',0,'发送失败');
		}
	}
	/**
	 * 我的约驾活动信息删除
	 * @return $msg 成功或者失败
	 */
	public function cancel(){
		$aid = input('get.aid');
		$uid = input('get.uid');
		//判断是否有参与者
		$part = Db::table('yue_participant')
				->where('aid',$aid)
				->where('isTake',1)
				->field('uid')
				->select();
		//获取标题，图片
		$info = Db::table('yue_activity')
					->where('id',$aid)
					->field('title,pic')
					->find();
		if(!empty($part)){
			//向参与活动者发送活动取消通知
			foreach ($part as $key => $value) {
				$arr[] =$part[$key]['uid'];
			}
			foreach ($arr as $k => $v) {
				$arr1 = [
						'title'   =>'活动取消通知',
						'time'    => date('Y-m-d H:i:s', time()),
						'content' => '您参与的“'.$info['title'].'”活动，因发布者个人原因取消了约驾活动，若对您有什么影响，深感抱歉。',
						'static'  => '0',
						'uid'     => $v,
					];
				$add = Db::table('yue_actnews')->insert($arr1);
			}
			$delpart = Db::table('yue_participant')->where('aid',$aid)->delete();
			$list = Db::table('yue_activity')
						->where('uid',$uid)
						->where('id',$aid)
						->delete();
			if($delpart && $list){
				//删除图片
				$this->del(json_decode($info['pic']));
				$this->result('',1,'删除成功');
			}else{
				$this->result('',0,'删除失败');
			}
		}else{
			$list = Db::table('yue_activity')->where('uid',$uid)->where('id',$aid)->delete();
			$this->result('',1,'删除成功');
		}
	}
	/**
	 * 上传约驾图片
	 * @return 
	 */
	public function image()
	{
		return upload("image","trip","https://mp.ctbls.com");
	}

	public function del($img){
		if(!empty($img)){
			foreach ($img as $key => $value) {
				$image = str_replace('https://mp.ctbls.com', '.', $value);
			}
    		$del = unlink($image);
		}
	
	}



		



}


			
		