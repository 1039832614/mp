<?php 
namespace app\sm\controller;
use app\base\controller\Sm;
use think\Db;

/**
 * 我的期权
 */
class Option extends Sm
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
	 * 获取期权列表
	 * @return [type] [description]
	 */
	public function getOption(){
		//判断当前用户的身份
		$person = Db::table('sm_user')
						->where('id',$this->uid)
						->field('person_rank,create_time')
						->find();
		$time['create_time'] = $person['create_time'];
		//截止倒计时 1年
		$time['stop_time'] = date("Y-m-d H:i:s",strtotime($time['create_time'])+1*365*24*60*60);
		$time['time'] = strtotime($time['stop_time']) - strtotime($time['create_time']);
		if($person['person_rank'] == 1)
		{
			//是服务经理
			//开发运营商的期权列表
			$list_yy = Db::table('sm_income')
						->where([
							'sm_id' => $this->uid,
							'type' => 1,
							'person_rank' => 1
						])
						->field('company,money,create_time  as time')
						->select();
			//开发维修厂的期权列表
			$list_cs = Db::table('sm_income')
						->alias('i')
						->join('cs_shop s','s.id = i.sid')
						->where([
							'sm_id' => $this->uid,
							'type' => 2,
							'if_finish' => 1
						])
						->field('s.company,i.money,i.create_time  as time')
						->group('company')
						->select();
			//合并数组
			$list = array_merge($list_cs,$list_yy);
			if($list) {
				$number =0;
				foreach ($list as $key => $value) {
					//字符串截取，只取小数点前面的
					$list[$key]['number'] = strstr($list[$key]['money'], '.',true);
					//定义前端展示内容
					$list[$key]['info'] = '开发'.$list[$key]['company'].'的期权奖励';
					$list[$key]['title'] = $list[$key]['number'].' 股';
					unset($list[$key]['money']);
					$number += $list[$key]['number'];
					$sum = '累计获得：'.$number.' 股期权';
				}
				$this->result(['list'=>$list,'leiji'=>$sum,'time'=>$time],1,'获取成功');
			} else {
				$this->result('',0,'暂无数据');
			}
		} else if($person['person_rank'] ==2)
		{
			//是运营总监
			//开发服务经理的期权列表
			$list_sm = Db::table('sm_income')
						->where([
							'sm_id' => $this->uid,
							'type' => 1,
							'person_rank' => 2
						])
						->field('company,money,create_time as time')
						->select();
			//开发维修厂的期权列表
			$list_cs = Db::table('sm_income')
						->alias('i')
						->join('cs_shop s','s.id = i.sid')
						->where([
							'sm_id' => $this->uid,
							'type' => 2,
							'if_finish' => 1
						])
						->field('s.company,i.money,i.create_time as time')
						->group('company')
						->select();
			//合并数组
			$list = array_merge($list_cs,$list_sm);
			if($list) {
				$number =0;
				foreach ($list as $key => $value) {
					//字符串截取，只取小数点前面的
					$list[$key]['number'] = strstr($list[$key]['money'], '.',true);
					$list[$key]['info'] = '开发'.$list[$key]['company'].'的期权奖励';
					//定义前端展示内容
					$list[$key]['title'] = $list[$key]['number'].' 股';
					unset($list[$key]['money']);
					$number += $list[$key]['number'];
					$sum = '累计获得：'.$number.' 股期权';
				}
				$this->result(['list'=>$list,'leiji'=>$sum,'time'=>$time],1,'获取成功');
			} else {
				$this->result('',0,'暂无数据');
			}
		} else {
		   $this->result('',0,'暂无数据');
		}
	} 
}