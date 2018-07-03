<?php 
namespace app\cb\controller;
use app\base\controller\Bby;
use think\Db;
use Config;
/**
* 投诉功能
*/
class Complain extends Bby
{
	/**
	 * 添加评论
	 */
	public function add()
	{
		$data = input('post.');
		// 检测是投诉论过
		$if_comment = Db::table('cs_income')->where('id',$data['bid'])->value('if_complain');
		// 获取运营商id
		$data['aid'] = Db::table('cs_shop')->where('id',$data['sid'])->value('aid');
		if($if_comment < 1){
			// 插入数据
			$add = Db::table('u_complain')->insert($data);
			// 邦保养记录奖励状态改变
			$up_complain = Db::table('cs_income')->where('id',$data['bid'])->setField('if_complain',1);
			// 更新投诉次数
			$up_count = Db::table('cs_shop')->where('id',$data['bid'])->setInc('complain_count');
			// 开启事务
			Db::startTrans();
			// 进行事务判断
			if($add && $up_complain !== false && $up_count !== false){
				Db::commit();
				$this->result('',1,'您的投诉提交成功');
			}else{
				Db::rollback();
				$this->result('',0,'提交异常，请您重新提交');
			}
		}else{
			$this->result('',0,'您已经投诉过了！');
		}
	}

	/**
	 * 获取投诉列表
	 */
	public function getList()
	{
		$page = input('get.page') ? : 1;
		$uid = input('get.uid');
		// 获取每页条数
		$pageSize = Config::get('page_size');
		$count = Db::table('u_complain')->where('uid',$uid)->count();
		$rows = ceil($count / $pageSize);
		// 获取数据列表
		$list = Db::table('u_complain')
				->alias('c')
				->join(['cs_shop'=>'s'],'c.sid = s.id')
				->field('company,content,c.create_time')
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
}