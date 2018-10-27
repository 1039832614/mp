<?php 
namespace app\sm\controller;
use app\base\controller\Sm;
use think\Db;

/**
 * 收入
 */
class Income extends Sm
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
	 * 运营总监的团队奖励
	 * @return [type] [description]
	 */
	public function headerTeamIncome()
	{
		//10.20 运营总监的团队奖励 显示为区域内的服务经理
		$page = input('post.page') ? :1;
		$pageSize = 5;
		//获取当前运营总监的区域
		$area = Db::table('sm_area')
				->where([
					'sm_id' => $this->uid,
					'audit_status' => 1
				])
				->order('id')
				->limit(1)
				->where('sm_mold','<>',2)
				->value('area');
		$count = Db::table('sm_area')
				->alias('a')
				->join('co_china_data d','d.id = a.area')
				->join('sm_user u','u.id = a.sm_id')
				->where([
					'a.audit_status' => 1,
					'd.pid' => $area 
				])
				->where('a.sm_mold','<>',2)
				->field('u.head_pic,u.name,d.name as address,a.create_time')
				->count();
		$list = Db::table('sm_area')
				->alias('a')
				->join('co_china_data d','d.id = a.area')
				->join('sm_user u','u.id = a.sm_id')
				->where([
					'a.audit_status' => 1,
					'd.pid' => $area 
				])
				->where('a.sm_mold','<>',2)
				->order('a.id desc')
				->field('u.head_pic,u.name,d.name as address,a.create_time')
				->select();
		$rows = ceil($count / $pageSize);
		if($list) {
			foreach ($list as $key => $value) {
				$list[$key]['money'] = '30000';
			}
			$this->result(['list'=>$list,'rows'=>$rows,'detail'=>'以实际发生为准'],1,'获取成功');
		} else {
			$this->result(['detail'=>'以实际发生为准'],0,'暂无数据');
		}
	}
	/**
	 * 运营总监的开发奖励
	 * @return [type] [description]
	 */
	public function headerExploitIncome()
	{
		$page = input('post.page') ? :1;
		$count = Db::table('sm_income')
					->alias('i')
					->join('cs_shop s','i.sid = s.id')
					->join('cs_shop_set ss','ss.sid = s.id')
					->where([
						'i.type' => 2,
						'i.person_rank' => 2,
						'i.sm_id' => $this->uid
					])
					->count();
		$pageSize = 5;
		$list = Db::table('sm_income')
					->alias('i')
					->join('cs_shop s','i.sid = s.id')
					->join('cs_shop_set ss','ss.sid = s.id')
					->where([
						'i.type' => 2,
						'i.person_rank' => 2,
						'i.sm_id' => $this->uid
					])
					->order('i.id desc')
					->field('s.company,i.money,ss.province,ss.city,ss.county,i.create_time')
					->page($page,$pageSize)
					->select();
		$rows = ceil($count / $pageSize);
		if($list) {
			foreach ($list as $key => $value) {
				$list[$key]['detail'] = $list[$key]['province'].$list[$key]['city'].$list[$key]['county'];
			}
			$this->result(['list'=>$list,'rows'=>$rows],1,'获取成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
	/**
	 * 运营总监的管理奖励（服务经理列表）
	 * @return [type] [description]
	 */
	public function headerManageIncome()
	{
		//维修厂名称，运营商名称，金额，时间，收入id
		$page = input('post.page') ? :1;
		$count = Db::table('sm_income')
				->alias('i')
				->join('cs_shop s','s.id = i.sid')
				->join('ca_agent a','a.aid = s.aid')
				->where([
					'person_rank' => 2,
					'i.sm_id' => $this->uid,
					'type' => 3
				])
				->count();
		$pageSize = 5;
		$list = Db::table('sm_income')
				->alias('i')
				->join('cs_shop s','s.id = i.sid')
				->join('ca_agent a','a.aid = s.aid')
				->where([
					'person_rank' => 2,
					'i.sm_id' => $this->uid,
					'type' => 3
				])
				->order('i.id desc')
				->field('s.company as shop_company,a.company as agent_company,i.money,a.province,a.city,i.create_time as time,i.id')
				->page($page,$pageSize)
				->select();
		$rows = ceil($count / $pageSize);
		if($list) {
			foreach ($list as $key => $value) {
				$list[$key]['detail'] = $list[$key]['province'].$value['city'];
			}
			$this->result(['list'=>$list,'rows'=>$rows],1,'获取成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
	
	/**
	 * 服务经理团队奖励
	 * @return [type] [description]
	 */
	public function smTeamIncome()
	{
		$page = input('post.page') ? :1;
		$where = [
					'sm_id' => $this->uid,
					'type' => 1,
					'person_rank' => 1,
				];
		$count = Db::table('sm_income')->where($where)->count();
		$pageSize = 5;
		$list = Db::table('sm_income')
				->where($where)
				->order('id desc')
				->field('company,address,money')
				->page($page,$pageSize)
				->select();
		$rows = ceil($count / $pageSize);
		if($list) {
			$this->result(['list'=>$list,'rows'=>$rows],1,'获取成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
	/**
	 * 服务经理的管理奖励
	 * @return [type] [description]
	 */
	public function smManageIncome()
	{
		//维修厂名称，运营商名称，金额，时间，收入id
		$page = input('post.page') ? :1;
		$count = Db::table('sm_income')
				->alias('i')
				->join('cs_shop s','s.id = i.sid')
				->join('ca_agent a','a.aid = s.aid')
				->join('cs_shop_set ss','ss.sid = s.id')
				->where([
					'person_rank' => 1,
					'i.sm_id' => $this->uid,
					'type' => 3
				])
				->count();
		$pageSize = 5;
		$list = Db::table('sm_income')
				->alias('i')
				->join('cs_shop s','s.id = i.sid')
				->join('ca_agent a','a.aid = s.aid')
				->join('cs_shop_set ss','ss.sid = s.id')
				->where([
					'person_rank' => 1,
					'i.sm_id' => $this->uid,
					'type' => 3
				])
				->order('i.id desc')
				->field('s.company as shop_company,a.company as agent_company,i.money,ss.province,ss.city,ss.county,i.create_time as time,i.id')
				->page($page,$pageSize)
				->select();
		$rows = ceil($count / $pageSize);
		if($list) {
			foreach ($list as $key => $value) {
				$list[$key]['detail'] = $list[$key]['province'].$value['city'].$list[$key]['county'];
			}
			$this->result(['list'=>$list,'rows'=>$rows],1,'获取成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
	/**
	 * 服务经理的服务收入详情
	 * @return [type] [description]
	 */
	public function smManageShopIncome()
	{
		//获取提交过来的运营商名称
		$data = input('post.');
		$list = Db::table('sm_income')
				->alias('i')
				->join('cs_shop s','i.sid = s.id')
				->join('ca_agent a','s.aid = a.aid')
				->where([
					'a.company' => $data['company'],
					'i.sm_id' => $this->uid,
					'i.type' => 3,
					'i.person_rank' => 1,
				])
				->field('i.id,s.company,i.create_time,i.money')
				->select();

		if($list) {
			$this->result($list,1,'获取成功');
		} else {
			$this->result('',0,'获取失败');
		}
	}
	/**
	 * 账单详情
	 * @return [type] [description]
	 */
	public function smManageIncomeDetail(){
		//获取提交过来的收入id
		$id = input('post.id');
		//返给前端地区，金额，收益来源：维修厂名称，车的品牌，类型，排量，时间，收益类型，收款方式，售卡金额
		$info = Db::table('sm_income')
				->alias('i')
				->join('cs_shop s','i.sid = s.id')
				->join('u_card c','c.id = i.cid')
				->join('co_car_cate cc','cc.id = c.car_cate_id')
				->join('co_car_menu cm','cm.id = cc.brand')
				->where([
					'i.id' => $id
				])
				->field('i.money,i.sm_id,i.sid,i.address,cc.type as car_type,cm.name as car_brand,cc.series,s.company,i.create_time,i.type,c.card_price')
				->find();
		if($info) {
			$info['profit'] = Db::table('cs_shop_set')
								->alias('ss')
								->join('co_china_data d','d.id = ss.county_id')
								->join('co_china_data cd','cd.id = d.pid')
								->join('sm_area a','a.area = cd.id')
								->where([
									'ss.sid' => $info['sid'],
									'a.pay_status' => 1,
									'a.audit_status' => 1
								])
								->value('a.sm_profit');
			$info['profit'] = $info['profit'].'%';			

			$info['mold'] = '微信零钱';
			$this->result($info,1,'获取成功');
		} else {
			$this->result('',0,'获取失败');
		}
	}
	/**
	 * 服务经理的开发奖励
	 * @return [type] [description]
	 */
	public function smExploitIncome()
	{
		$page = input('post.page') ? : 1;
		$where = [
					'sm_id' => $this->uid,
					'type' => 2,
					'person_rank' => 1
				];
		$count = Db::table('sm_income')
				->alias('i')
				->join('cs_shop s','s.id = i.sid')
				->where($where)
				->count();
		$pageSize = 3;

		//维修厂名称，运营商名称，金额，维修厂id
		$list = Db::table('sm_income')
				->alias('i')
				->join('cs_shop s','s.id = i.sid')
				->where($where)
				->order('i.id desc')
				->field('i.id,s.company as shop_company,i.company as agent_company,i.money,i.sid,i.if_finish,i.cash_status')
				->page($page,$pageSize)
				->select();
		
		if($list) {
			foreach ($list as $key => $value) {
			//获取每一个维修厂所在区域的服务经理是否开启了任务奖励，如果开启了任务奖励则可以进入到20辆车的详情页面
			$list[$key]['task_raw'] = Db::table('sm_area')
										->alias('a')
										->join('co_china_data d','d.pid = a.area')
										->join('cs_shop_set s','s.county_id = d.id')
										->where([
											's.sid' => $list[$key]['sid']
										])
										->value('task_raw');
			$task_raw[] = $list[$key]['shop_company'];
		}
		//统计数组中所有的维修厂的值，如果出现一次则默认不能跳转到20辆车的详情页；
		//如果出现过两次:则一次是开发奖励，一次是任务奖励，可以跳转到20辆车的详情页
		$a_task_raw = array_count_values($task_raw);
		foreach ($a_task_raw as $key => $value) {
			if($value == 1) {
				foreach ($list as $k => $v) {
					if($list[$k]['shop_company'] == $key) {
						$list[$k]['task_raw'] = 0;
					}
				}
			}  
		}
		//总页数
		$rows = ceil($count / $pageSize);
			$count_a = count($list);
			//双循环判断如果有这个维修厂出现了两次，且两个状态不都等于1，则小程序前端500的右上角提示为奖励任务进行中（注意，数据库中是已完成）
			for ($i=0; $i < $count_a; $i++) { 
				for ($j=0; $j < $count_a; $j++) { 
					if($list[$i]['shop_company'] == $list[$j]['shop_company'] && $list[$i]['money'] !== $list[$j]['money']) {
						
						if($list[$i]['if_finish'] !== 1 || $list[$i]['if_finish'] !== 1){

							$list[$i]['if_finish'] = 0;
							$list[$j]['if_finish'] = 0;
						}
					}
					
				}
			}
			//根据各个状态给列表的右下角定义文字叙述
			foreach ($list as $key => $value) {
				if($list[$key]['money'] == 500) {
					if($list[$key]['cash_status'] == 1){
						$list[$key]['detail'] = '已提现';
					} else if($list[$key]['cash_status'] == 2){
						$list[$key]['detail'] = '提现审核中';
					} else {
						$list[$key]['detail'] = '可提现';
					}
				}
				if($list[$key]['money'] == 2000) {
					if($list[$key]['if_finish'] == 0) {
						$list[$key]['detail'] = '暂无提现奖励';
					} 
					if($list[$key]['if_finish'] == 1) {
						if($list[$key]['cash_status'] == 1){
							$list[$key]['detail'] = '已提现';
						} else if($list[$key]['cash_status'] == 2){
							$list[$key]['detail'] = '提现审核中';
						} else {
							$list[$key]['detail'] = '可提现';
						}
					}
				}
			}
			$this->result(['list'=>$list,'rows'=>$rows],1,'获取成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
	/**
	 * 服务经理开发奖励详情 车信息
	 * @return [type] [description]
	 */
	public function smExploitDetail()
	{
		//获取提交过来的sid
		$data = input('post.');
		$page = input('post.page') ? : 1;
		$count = Db::table('u_card')
				->alias('c')
				->join('co_car_cate cc','c.car_cate_id = cc.id')
				->where([
					'c.sid' => $data['sid']
				])
				->order('c.id desc')
				->limit(20)
				->field('c.sale_time,c.plate,cc.type,cc.series')
				->count();
		$pageSize = 5;
		$list = Db::table('u_card')
				->alias('c')
				->join('co_car_cate cc','c.car_cate_id = cc.id')
				->where([
					'c.sid' => $data['sid']
				])
				->order('c.id desc')
				->limit(20)
				->page($page,$pageSize)
				->field('c.sale_time,c.plate,cc.type,cc.series')
				->select();
		$rows = ceil($count / $pageSize);
		if($list) {
			$this->result(['list'=>$list,'rows'=>$rows],1,'获取成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
	/**
	 * 申请提现
	 * @return [type] [description]
	 */
	public function cash()
	{
		$data = input('post.');
		if($data['money'] == 0) {
			$this->result('',0,'您的可提现金额为0');
		}
		if($data){
			Db::startTrans();
			$dec_balance = Db::table('sm_user')
						->where([
						 'id' => $this->uid
						])
						->setDec('balance',$data['money']);
			$sta = Db::table('sm_income')
					->where([
						'sm_id' => $this->uid,
						'if_finish' => 1
					])
					->update(['cash_status'=>2]);
			$smInfo = Db::table('sm_user')
					->where('id',$this->uid)
					->find();
			//构建申请提现入库数据
			$arr = [
				'odd_number' => build_only_sn(),
				'sm_id' => $this->uid,
				'bank_code' => $smInfo['bank_code'],
				'account' => $smInfo['account'],
				'account_name' => $smInfo['bank_name'],
				'money' => $data['money']
			];
			$re = Db::table('sm_apply_cash')
					->strict(false)
					->insert($arr);
			if($re !== false && $dec_balance !== false){
				Db::commit();
				$this->result('',1,'提交成功，请等待审核');
			}else{
				Db::rollback();
				$this->result('',0,'提交失败，请重新提交');
			}
		} else {
			$this->result('',0,'申请失败');
		}
		

	}

    /**
     * 获取该用户的总金额
     * @return [type] [description]
     */
    public function getMoney($sm_id,$type)
    {
    	$money = Db::table('sm_income')
    				->where([
    					'sm_id' => $sm_id,
    					'type' => $type,
    					'if_finish' => 1
    				])
    				->sum('money');
    	return $money;
    }
    /**
     * 获取用户的各项金额
     * @return [type] [description]
     */
    public function getTotalMoney()
    {
    	//获取用户当前的身份信息
    	$person_rank = Db::table('sm_user')
    					->where('id',$this->uid)
    					->value('person_rank');
    	if($person_rank == 2) {
    		//如果当前用户是运营总监
    		$area = Db::table('sm_area')
				->where([
					'sm_id' => $this->uid,
					'audit_status' => 1
				])
				->order('id')
				->limit(1)
				->where('sm_mold','<>',2)
				->value('area');
			$count = Db::table('sm_area')
					->alias('a')
					->join('co_china_data d','d.id = a.area')
					->join('sm_user u','u.id = a.sm_id')
					->where([
						'a.audit_status' => 1,
						'd.pid' => $area 
					])
					->where('a.sm_mold','<>',2)
					->count();
			$info['tdjl'] = $count * 30000;
    	} else {
    		$info['tdjl'] = $this->getMoney($this->uid,1);
    		
    	}
    	$info['kfjl'] = $this->getMoney($this->uid,2);
    	
    	$info['gljl'] = $this->getMoney($this->uid,3);
    	if($info){
    		$this->result($info,1,'获取成功');
    	} else {
    		$this->result('',0,'获取失败');
    	}
    }
    /**
     * 获取是否有未读的申请提现
     * @return [type] [description]
     */
    public function getCashMsg()
    {
    	$list = Db::table('sm_apply_cash')
    			->where([
    				'sm_id' => $this->uid,
    				'if_read' => 0
    			])
    			->where('audit_status','<>','0')
    			->field('id,audit_status')
    			->order('id asc')
    			->limit(1)
    			->find();
    	if($list){
    		$this->result($list,1,'获取成功');
    	} else {
    		$this->result('',0,'暂无数据');
    	}
    }
    /**
     * 读取提现审核信息
     * @return [type] [description]
     */
    public function readCashMsg()
    {
    	$id = input('post.id');
    	$re = Db::table('sm_apply_cash')
    			->where('id',$id)
    			->update(['if_read' => 1]);
    	if($re) {
    		$this->result('',1,'已读取');
    	} else {
    		$this->result('',0,'系统错误，请联系技术部');
    	}
    }
    /**
     * 获取审核提现的驳回理由，时间，审核人
     * @return [type] [description]
     */
    public function getCashReject()
    {
    	$id = input('post.id');
    	$info = Db::table('sm_apply_cash')
    			->where('id',$id)
    			->field('reason,audit_person,from_unixtime(audit_time) as audit_time')
    			->find();
    	$re = Db::table('sm_apply_cash')
    			->where('id',$id)
    			->update(['if_read' => 1]);
    	if($info) {
    		$this->result($info,1,'获取成功');
    	} else {
    		$this->result('',0,'获取失败');
    	}
    }
} 
