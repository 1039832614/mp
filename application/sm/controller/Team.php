<?php 
namespace app\sm\controller;
use app\base\controller\Sm;
use think\Db;

/**
 * 团队
 */
class Team extends Sm
{
	/**
	 * 初始化方法
	 * @return [type] [description]
	 */
	public function initialize()
	{
		$this->uid = input('post.uid');
	}

	/**
	 * 服务经理团队首页
	 * @return [type] [description]
	 */
	public function smTeamIndex()
	{
		//如果当前当前服务经理的区域（省）内有团队
		$team = Db::table('sm_area')
				->alias('a')
				->join('co_china_data d','d.id = a.area')
				->join('sm_team t','t.pro = d.pid')
				->join('co_china_data cd','cd.id = d.pid')
				->join('sm_user u','u.id = t.sm_header_id')
				->where([
					'a.sm_id' => $this->uid,
					'a.audit_status' => 1
				])
				->where('a.sm_mold','<>',2)
				->field('t.id as team_id,a.id,cd.name as province,team_name,u.name as header,t.sm_member_id')
				->group('team_id')
				->select();
		if(!empty($team)){
			foreach ($team as $key => $value) {
				$team[$key]['join_status'] = 1;
			}
			$this->result($team,1,'获取成功');
		} else {
			//如果服务经理区域内尚无团队则返回暂无数据
			$this->result('',0,'你所在的区域暂无团队');
		}
		
	}
	/**
	 * 运营总监的团队首页
	 * @return [type] [description]
	 */
	public function headerTeamIndex()
	{
		//查找该运营总监的区域
		$area = Db::table('sm_area')
					->alias('sa')
					->join('co_china_data d','d.id = sa.area')
					->where([
						'sm_id'        => $this->uid,
						'audit_status' => 1,
						'sm_type'      => 2
					])
					->where('sa.sm_mold','<>',2)
					->field('sa.id,d.name as province,d.id as pro_id,sm_status')
					->select();
		if(empty($area)) {
			$this->result('',0,'您当前无区域');
		}
		//获取该运营总监的团队
		$team = Db::table('sm_team')
				->alias('t')
				->join('sm_area a','a.area = t.pro')
				->where([
					't.sm_header_id' => $this->uid
				])
				->where('a.sm_mold','<>',2)
				->field('t.id as team_id,t.team_name,sm_member_id,t.pro as pro_id,sm_status')
				->find();
		// return $team;die();
		if($team){
			//如果创建过团队了，则不能再次创建。
			$list['add_status'] = 0;
			//团队成员信息：服务经理头像，区域，电话，售卡数量，所在市id
			$memberInfo = Db::table('sm_area')
					->alias('a')
					->join('co_china_data d','d.id = a.area')
					->join('sm_user u','u.id = a.sm_id')
					->join('co_china_data cd','cd.pid = d.id')
					->leftJoin('cs_shop_set ss','ss.county_id = cd.id')
					->leftJoin('u_card uc','uc.sid = ss.sid')
					->where([
						'a.audit_status' => 1,
						'd.pid' => $team['pro_id']
					])
					->where('a.sm_mold','<>',2)
					->field("u.name,a.sm_id,u.phone,u.head_pic,d.name as city,a.area as city_id")
					->group('city_id')
					->select();
			foreach ($memberInfo as $key => $value) {
				$memberInfo[$key]['number'] = $this->getCardNumberA($memberInfo[$key]['sm_id']);
				$memberInfo[$key]['detail'] = '已售'.$memberInfo[$key]['number'].'张';
			}
		} else {
			$list['add_status'] = 1;
			$memberInfo = '';
		}
		$list['area'] = $area;
		$list['team'] = $team;
		$list['memberInfo'] = $memberInfo;
		if(!empty($list['area'])){
			$this->result($list,1,'获取成功');
		} else {
			$this->result('',0,'等待总后台审核区域。。。');
		}
	}
	/**
	 * 获取服务经理区域内的售卡数量
	 * @return [type] [description]
	 */
	public function getCardNumberA($sm_id)
	{
		$shop = Db::table('sm_area')
					->alias('a')
					->join('co_china_data d','d.pid = a.area')
					->join('cs_shop_set s','s.county_id = d.id')
					->where([
						'a.audit_status' => 1,
						'a.sm_id' => $sm_id
					])
					->where('a.sm_mold','<>',2)
					->distinct(true)
					->column('sid');
		$count = count($shop);
		$number = 0;
		for ($i=0; $i < $count; $i++) { 
			$card_num = Db::table('u_card')
						->where([
							'pay_status' => 1,
							'sid' => $shop[$i]
						])
						->count();
			$number += $card_num;
		}
		return $number;
	}
	/**
	 * 创建团队
	 * @return [type] [description]
	 */
	public function createTeam()
	{
		$data = input('post.');
		$validate = validate('Team');
		if($validate->check($data)){
			$data['team_name'] = '仲达集团-邦保养'.$data['province'].'运营中心';
			$count = Db::table('sm_team')
						->where('team_name',$data['team_name'])
						->count();
			if($count == 0) {
				$data['sm_header_id'] = $this->uid;
				unset($data['uid']);
				$re = Db::table('sm_team')
						->strict(false)
						->insert($data);
				if($re !== false) {
					$this->result('',1,'创建成功');
				} else {
					$this->result('',0,'创建失败，请联系技术部');
				}
			} else {
				$this->result('',0,'该地区已有团队，不可重复创建');
			}
		} else {
			$this->result('',0,$validate->getError());
		}
	}
	/**
	 * 修改团队信息
	 * @return [type] [description]
	 */
	public function alterTeam()
	{
		$data = input('post.');
		$validate = validate('Team');
		if($validate->check($data)) {
			$id = $data['team_id'];
			unset($data['team_id']);
			unset($data['uid']);

			$re = Db::table('sm_team')
					->where([
						'id' => $id
					])
					->update($data);
			if($re !== false) {
				$this->result('',1,'修改成功');
			} else {
				$this->result('',0,'修改失败');
			}
		} else{
			$this->result('',0,$validate->getError());
		}
	}
	/**
	 * 获取团队区域内的服务经理
	 * @return [type] [description]
	 */
	public function getTeamAreaSm()
	{
		//获取提交过来的团队id
		$id = input('post.team_id');

		$list = Db::table('sm_team')
					->alias('t')
					->join('co_china_data d','d.id = t.pro')
					->join('co_china_data cd','cd.pid = d.id')
					->join('sm_area a','a.area = cd.id')
					->join('sm_user u','u.id = a.sm_id')
					->where([
						'a.pay_status' => 1,
						'a.audit_status' => 1,
						't.id' => $id
					])
					->where('a.sm_mold','<>',2)
					->field('u.name,u.id as uuid,u.head_pic,cd.name as city,a.sm_status')
					->select();
		$member = Db::table('sm_team')
					->where('id',$id)
					->value('sm_member_id'); 
		$member = explode(',', $member);
		foreach ($list as $key => $value) {
			//获取被邀请且同意的次数
			
			$status_add = Db::table('sm_team_invite')
						->where([
							'team_id' => $id,
							'sm_member_id' => $value['uuid'],
							'type' => [1,2]
						])
						->count();
			//获取被删除的次数
			$status_delete = Db::table('sm_team_invite')
								->where([
									'team_id' => $id,
									'sm_member_id' => $value['uuid'],
									'type' => [3,4]
								])
								->count();
			// return $status_add;die();
			//当添加的比删除的多的时候为已邀请
			if(($status_add - $status_delete) > 0) {
				$list[$key]['invite_status'] = 1;
			} else {
				$list[$key]['invite_status'] = 0;
			}
		}

		if($list) {
			$this->result($list,1,'获取成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
	
	/**
	 * 上传负责人正面照片
	 * @return [type] [description]
	 */
	public function uploadLeaderImg()
	{
		return $this->uploadImage('image','article','https://mp.ctbls.com');
	}

	/**
	 * 上传二维码
	 * @return [type] [description]
	 */
	public function uploadCode()
	{	
		return $this->uploadImage('image','article','https://mp.ctbls.com');
	}

	/**
	 * 更新团队二维码
	 * @return [type] [description]
	 */
	public function updateCode()
	{
		$data = input('post.');
		$re = Db::table('sm_team')
				->where([
					['id','=',$data['team_id']],
					['sm_header_id','=',$this->uid]
				])
				->update(['qrcode'=>$data['qrcode']]);
		if($re !== false ) {
			$this->result('',1,'更新成功');
		} else {
			$this->result('',0,'更新失败');
		}
	}
	/**
	 * 获取二维码
	 * @return [type] [description]
	 */
	public function getCode()
	{
		$data = input('post.');
		$qrcode = Db::table('sm_team')
					->where('id',$data['id'])
					->value('qrcode');
		if(!empty($qrcode)){
			$this->result($qrcode,1,'获取成功');
		} else {
			$this->result('',0,'暂无二维码');
		}
	}

	/**
	 * 获取团队信息
	 * @return [type] [description]
	 */
	public function getTeamInfo()
	{
		$team_id = input('post.team_id');
		$team = Db::table('sm_team')
				->alias('t')
				->join('sm_user u','u.id = t.sm_header_id')
				->where('t.id',$team_id)
				->field('t.id,t.team_name,t.leader_img,t.leader,t.sm_header_id,t.sm_member_id,t.status,t.pro,t.team_slogan,t.team_mission,t.team_vision,t.leader_resume,t.qrcode,u.phone')
				->find();
		if($team) {
			//获取区域内的成员
			$member = Db::table('sm_user')
						->alias('u')
						->join('sm_area a','u.id = a.sm_id')
						->join('co_china_data d','d.id = a.area')
						->where([
							'd.pid' => $team['pro']
						])
						->where('a.sm_mold','<>',2)
						->field('u.id,u.head_pic,u.name,d.name as area,a.create_time')
						->select();
			
			if($member) {
				foreach ($member as $key => $value) {
					$member[$key]['create_time'] = substr($member[$key]['create_time'],0,10);
				}
				$team['member']['code'] = 1;
	 			$team['member']['list'] = $member; 
			} else {
				//没有团队成员
				$team['member']['code'] = 0;
	 			$team['member']['list'] = ''; 
			}
			$this->result($team,1,'获取成功');
		} else {
			$this->result('',0,'获取失败');
		}
	}
	/**
	 * 删除成员
	 * @return [type] [description]
	 */
	public function delMember()
	{
		//获取提交过来的成员的id以及成员id
		$data = input('post.');
		$member_id = Db::table('sm_team')
					->where('id',$data['id'])
					->value('sm_member_id');
		$member_id = explode(',', $member_id);
		foreach ($member_id as $key => $value) {
			if($value == $data['member_id']) {
				unset($member_id[$key]);
			}
		}
		$member_id = implode(',',array_values($member_id)); //数组重新拍序然后转为字符串
		$re = Db::table('sm_team')
				->where('id',$data['id'])
				->setField(['sm_member_id'=>$member_id]);
		if($re !== false) {
			$this->result('',1,'删除成员成功');
		} else {
			$this->result('',0,'删除成员失败');
		}
	}
	/**
	 * 申请加入团队
	 * @return [type] [description]
	 */
	public function joinTeam()
	{
		//获取团队的id 以及 队长id 服务经理 的id 然后进行入库。
		$data = input('post.');
		//构建入库数据
		$arr = [
			'team_id' => $data['team_id'],
			'sm_header_id' => $data['sm_header_id'],
			'sm_member_id' => $this->uid
		];
		$res = Db::table('sm_team_invite')
				->strict(false)
				->insert($arr);
		if($arr) {
			$this->result('',1,'申请成功');
		} else {
			$this->result('',0,'申请失败');
		}
	}
	/**
	 * 申请退出团队
	 * @return [type] [description]
	 */
	public function quitTeam()
	{
		//获取团队的id 以及 队长id 服务经理的id 然后进行入库
		$data = input('post.');
		//构建入库数据
		$arr = [
			'team_id' => $data['team_id'],
			'sm_header_id' => $data['sm_header_id'],
			'sm_member_id' => $this->uid,
			'type' => 3
		];
		$res = Db::table('sm_team_invite')
				->strict(false)
				->insert($arr);
		if($arr) {
			$this->result('',1,'申请成功');
		} else {
			$this->result('',0,'申请失败');
		}
	}
	/**
	 * 运营总监获取申请列表
	 * @return [type] [description]
	 */
	public function getJoinList()
	{
		//获取提交过来的团队id，以及用户id
		$data = input('post.');
		$list = Db::table('sm_team_invite') 
				->alias('i')
				->join('sm_user u','u.id = i.sm_member_id')
				->join('sm_area a','a.sm_id = i.sm_member_id')
				->join('co_china_data d','a.area = d.id')
				->where([
					'i.status' => 0,
					'i.sm_header_id' => $this->uid,
					'i.team_id' => $data['team_id'],
					'i.type' => [2,3]
				])
				->where('a.sm_mold','<>',2)
				->field('i.id as invite_id,u.name,u.id as sm_member_id,u.phone,u.head_pic,i.type,u.sex,i.type,d.name as city')
				->group('u.name')
				->select();
		if(!empty($list)){
			$this->result($list,1,'获取成功');
		} else {
			$this->result('',0,'暂无更多');
		}
	}
	/**
	 * 判断是否是这个团队的队长
	 * @return [type] [description]
	 */
	public function judgeTeamHeader()
	{
		//用户id ， 团队id
		$data = input('post.');
		$re  = Db::table('sm_team')
				->where([
					'id' => $data['team_id'],
					'sm_header_id' => $this->uid
				])
				->count();
		if($re > 0) {
			$this->result('',1,'您是队长身份');
		} else {
			$this->result('',0,'您是成员身份');
		}
	}
	/**
	 * 通过加入团队的申请或退出
	 * @return [type] [description]
	 */
	public function handleInvite()
	{
		//获取提交过来的成员id 和 团队id
		$data = input('post.');
		//判断该成员是申请加入还是申请退出
		$type = Db::table('sm_team_invite')
				->where([
					'team_id' => $data['team_id'],
					'sm_member_id' => $data['sm_member_id']
				])
				->order('id desc')
				->limit(1)
				->value('type');
		$member_id = Db::table('sm_team')
					->where('id',$data['team_id'])
					->value('sm_member_id');
		if(empty($member_id)) {
			$member_id = array();
		}  else {
			$member_id = explode(',', $member_id);
		}

		if($type == 2){
			//申请加入团队的
			array_push($member_id,$data['sm_member_id']);
		}
		if($type == 3) {
			//申请退出团队的
			foreach ($member_id as $key => $value) {
				if($value == $data['sm_member_id']) {
					unset($member_id[$key]);
				}
		    }
		}

		$member_id = implode(',',array_values($member_id)); //数组重新拍序然后转为字符串
		$re = Db::table('sm_team')
				->where('id',$data['team_id'])
				->update(['sm_member_id'=>$member_id]);
		$res = Db::table('sm_team_invite')
					->where('id',$data['invite_id'])
					->update(['status'=>1]);
		if($re !== false && $res !== false) {
			
			$this->result('',1,'操作成功');
		} else {
			$this->result('',0,'操作失败');
		}
	}
	/**
	 * 获取用户信息
	 * @return [type] [description]
	 */
	public function getInfo(){
		$info = $this->getSminfo($this->uid);
		if(!empty($info)) {
			$this->result($info,1,'获取成功');
		} else {
			$this->result('',0,'获取失败');
		}
	}
	/**
	 * 邀请服务经理加入
	 * @return [type] [description]
	 */
	public function inviteSmJoin()
	{
		//获取提交过来的团队id ， 队长id ，成员id 
		$data = input('post.');
		//构建入库数据
		$arr = [
			'team_id' => $data['team_id'],
			'sm_header_id' => $this->uid,
			'sm_member_id' => $data['uuid'],
			'type' => 1
		];
		$re = Db::table('sm_team_invite')
				->strict(false)
				->insert($arr);
		if($re) {
			$this->result('',1,'邀请成功');
		} else {
			$this->result('',0,'邀请失败');
		}
	}
	/**
	 * 判断服务经理有无被邀请加入团队
	 * @return [type] [description]
	 */
	public function ifInvite()
	{
		$info = Db::table('sm_team_invite')
				->alias('i')
				->join('sm_team t','t.id = i.team_id')
				->join('sm_user u','u.id = i.sm_header_id')
				->where([
					'i.sm_member_id' => $this->uid,
					'i.status'       => 0,
					'i.type'         => 1
				])
				->field('i.id as invite_id,u.head_pic,u.name,t.team_name,t.id as team_id')
				->select();
		if($info) {
			$this->result($info,1,'获取成功');
		} else {
			$this->result('',0,'暂无更多');
		}
	}
	/**
	 * 服务经理同意加入团队
	 * @return [type] [description]
	 */
	public function agreeInvite()
	{
		//获取提交过来的服务经理id，邀请id，团队id
		$data = input('post.');

		$sm_member_id = Db::table('sm_team')
						->where([
							'id' => $data['team_id']
						])
						->value('sm_member_id');
		if(empty($sm_member_id)) {
			$sm_member_id = array();
		} else {
			$sm_member_id = explode(',', $sm_member_id);
		}
		
		array_push($sm_member_id, $this->uid);
		$sm_member_id = implode(',',array_values($sm_member_id));
		$up = Db::table('sm_team')
				->where('id',$data['team_id'])
				->update(['sm_member_id'=>$sm_member_id]);
		$re = Db::table('sm_team_invite')
				->where('id',$data['invite_id'])
				->update(['status'=>1]);
		if($up !== false && $re !== false) {
			$this->result('',1,'加入成功');
		} else {
			$this->result('',0,'加入失败');
		}
	}
	/**
	 * 判断服务经理在当前的团队中有没有被投诉
	 * @return [type] [description]
	 */
	public function ifSmComplaint()
	{
		//获取提交过来的团队省份
		$data = input('post.');

		$info = Db::table('sm_complaint')
				->alias('c')
				->join('sm_team t','t.pro = c.pro_id')
				->join('sm_user u','u.id = t.sm_header_id')
				->where([
					'c.sm_id' => $this->uid,
					'c.pro_id' => $data['pro'],
					'c.status' => 1
				])
				->order('c.id desc')
				->limit(1)
				->value('u.phone');
		if($info) {
			$this->result($info,1,'获取成功');
		} else {
			$this->result('',0,'获取失败');
		}
	}
	/**
	 * 运营总监查看自己团队内成员有无被投诉且未处理状态
	 * @return [type] [description]
	 */
	public function ifComplaint()
	{
		$data = input('post.');
		$sm_member_id = Db::table('sm_team')
						->where([
							'sm_header_id' => $this->uid
						])
						->value('sm_member_id');
		if(empty($sm_member_id)) {
			$sm_member_id = array();
		} else {
			$sm_member_id = explode(',', $sm_member_id);
		}
		$count = count($sm_member_id);
		if($count !== 0) {
			for ($i=0; $i < $count; $i++) { 
			$res[] = Db::table('sm_complaint')
						->where([
							'status' => 1,
							'sm_id' => $sm_member_id[$i]
						])
						->count();
			}
			$co = count($res);
			for ($i=0; $i < $co; $i++) { 
				if(!$res[$i] == 0){
					$a[] = 1;
				} else {
					$a[] = 0;
				}
			}
			$re = in_array(1, $a);
			if($re) {
				$this->result('',1,'您的团队成员受到投诉，您的收益已被暂停。');
			} else {
				$this->result('',0,'暂无投诉');
			}
		} else {
			$this->result('',0,'暂无投诉');
		}
	}
	/**
	 * 删除成员
	 * @return [type] [description]
	 */
	public function deleteMember()
	{
		//获取提交过来的成员id以及团队id
		$data = input('post.');
		if(!empty($data)) {
			Db::startTrans();
			//构建删除成员入库数据
			$arr = [
				'type' => 4,
				'team_id' => $data['team_id'],
				'sm_header_id' => $data['uid'],
				'sm_member_id' => $data['sm_member_id']
			];
			//入库删除成员信息
			$add = Db::table('sm_team_invite')
					->strict(false)
					->insert($arr);
			//获取所有的成员id
			$member_id = Db::table('sm_team')
					->where('id',$data['team_id'])
					->value('sm_member_id');
			//将成员id的字符串格式替换为数组格式
			if(empty($member_id)) {
				$member_id = array();
			}  else {
				$member_id = explode(',', $member_id);
			}
			//进行遍历，如果和前端提交的成员id相同，则删除该成员id
			foreach ($member_id as $key => $value) {
				if($value == $data['sm_member_id']) {
					unset($member_id[$key]);
				}
		    }
		    //数组重新拍序然后转为字符串
		    $member_id = implode(',',array_values($member_id)); 
		    //更新当前团队的成员id
			$re = Db::table('sm_team')
				->where('id',$data['team_id'])
				->update(['sm_member_id'=>$member_id]);
			if($re !== false && $add !== false) {
				Db::commit();
				$this->result('',1,'删除成功');
			} else {
				Db::rollback();
				$this->result('',0,'删除失败');
			}
		} else {
			$this->result('',0,'删除失败');
		}
	}
	/**
	 * 获取当前服务经理有无被删除团队
	 * @return [type] [description]
	 */
	public function getDeleteMsg()
	{
		$info = Db::table('sm_team_invite')
				->alias('i')
				->join('sm_user u','u.id = i.sm_header_id')
				->join('sm_team t','t.id = i.team_id')
				->where([
					'i.type' => 4,
					'i.status' => 0,
					'i.sm_member_id' => $this->uid
				])
				->order('i.id')
				->limit(1)
				->field('u.head_pic,u.name,team_name,i.id as invite_id')
				->find();
		if($info) {
			$info['detail'] = '已将您移除团队';
			$this->result($info,0,'获取成功');
		} else {
			$this->result('',1,'暂无更多');
		}
	}
	/**
	 * 服务经理已读删除成员消息
	 * @return [type] [description]
	 */
	public function readDeleteMsg()
	{
		$id = input('post.invite_id');
		$res = Db::table('sm_team_invite')
				->where([
					'id' => $id
				])
				->update([
					'status' => 1,
					'audit_time' => time()
				]);
		if($res !== false) {
			$this->result('',1,'已读');
		} else {
			$this->result('',0,'读取失败');
		}
	}
}