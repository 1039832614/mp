<?php 
namespace app\cb\controller;
use app\base\controller\Bby;
use think\Db;
/**
* 评论功能
*/
class Comment extends Bby
{
	/**
	 * 添加评论
	 */
	public function add()
	{
		$data = input('post.');
		// 检测是否评论过
		$if_comment = Db::table('cs_income')->where('id',$data['bid'])->value('if_comment');
		if($if_comment < 1){
			// 插入数据
			$add = Db::table('u_comment')->insertGetId($data);
			// 邦保养记录奖励状态改变
			$up_comment = Db::table('cs_income')->where('id',$data['bid'])->setField('if_comment',1);
			// 开启事务
			Db::startTrans();
			// 好评奖励
			if($data['tn_star'] == 5 && $data['shop_star'] == 5){
				$up_balance = $this->getReward($data['sid']);
				if($add){
					$money = Db::table('u_comment')->where('id',$add)->setField('money',10);
				}else{
					$this->result('',1,'未添加评论');
				}
				$exp = ($money !== false && $up_comment !== false && $up_balance !== false);
			}else{
				$exp = ($add && $up_comment !== false);
			}
			// 进行事务判断
			if($exp){
				Db::commit();
				$this->result('',1,'您的评论提交成功');
			}else{
				Db::rollback();
				$this->result('',0,'提交异常，请您重新提交');
			}
		}else{
			$this->result('',0,'您已经评论过了！');
		}
	}

	/**
	 * 获取评论列表
	 */
	public function getList()
	{
		$page = input('get.page') ? : 1;
		$uid = input('get.uid');
		// 获取每页条数
		$pageSize = Config::get('page_size');
		$count = Db::table('u_comment')->where('uid',$uid)->count();
		$rows = ceil($count / $pageSize);
		// 获取数据列表
		$list = Db::table('u_comment')
				->alias('c')
				->join(['cs_shop'=>'s','c.sid = s.id'])
				->field('company,cotent,c.create_time')
				->where('c.uid',$uid)
				->order('c.id desc')
				->page($page, $pageSize)
				->select();
		// 返回给前端
		if($count > 0){
			$this->result(['list'=>$list,'rows'=>$rows],1,'获取成功');
		}else{
			$this->result('',0,'暂无数据');
		}
	}

	/**
	 * 店铺获得好评奖励
	 */
	public function getReward($sid)
	{
		// // 获取奖励金额
		// $reward  = 	Db::table('cs_shop')
		// 			->alias('s')
		// 			->join(['ca_agent_set'=>'a'],'s.aid = a.aid')
		// 			->where('s.id',$sid)
		// 			->value('shop_praise');
		// 维修厂获得奖励
		return Db::table('cs_shop')->where('id',$sid)->setInc('balance',10);
	}
}