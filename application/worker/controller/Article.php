<?php 
namespace app\worker\controller;
use app\base\controller\Worker;
use think\Db;
use think\Controller;
use Config;
/**
 * 技师发布文章
 */
class Article extends Worker
{
	/**
	 * 文章图片上传
	 * @return [type] [description]
	 */
	public function uploadPic()
	{
		return upload('image','article','https://mp.ctbls.com/public');
	}
	/**
	 * 技师发布文章	
	 */
	public function addArticle()
	{
		//title,thumb,content,uid,mold,create_time,
		//标题，文章配图，内容，用户id，文章类型，创建时间，
		$data = input('post.');
		$validate = validate('Article');
		if($validate->check($data))
		{
			$data['create_time'] = time();
			$res = Db::table('tn_article')
			       ->strict(false)
			       ->insert($data);
			if($res){
				$this->result('',1,'发布文章成功，请耐心等待审核，审核通过后奖励金会自动发到您的微信余额');
			} else {
				$this->result('',0,'发布文章失败，请检查');
			}
		} else {
			$this->result('',0,$validate->getError());
		}
	}

	/**
	 * 我发布的文章列表
	 * @return [type] [description]
	 */
	public function myArticleList()
	{
		$uid = input('get.uid');

		$list = Db::table('tn_article')
				->alias('a')
				->join('tn_user u','u.id = a.uid')
		        ->where('uid',$uid)
		        ->order('create_time desc')
		        ->field('aid,title,create_time,a.status,u.name,thumb')
		        ->select();
		foreach ($list as $key => $value) {
			if(!$list[$key]['status'] == 0){
				$list[$key]['reward'] = Db::table('tn_worker_reward')
						  ->where([
						  	['wid'  ,'=',$uid],
						  	['type' ,'=',2],
						  	['acid' ,'=',$value['aid']]
						  ])
						  ->value('reward');
			}
		}
		foreach ($list as $key => $value) {
			$date = date("Y-m-d H:i:s",$value['create_time']);
			$list[$key]['create_time'] = $date;
		}
		if($list){
			$this->result(['list' => $list],1,'获取信息成功');
		} else {
			$this->result('',0,'获取信息失败,您尚未发布文章');
		}
	}

	/**
	 * 某个文章详情
	 */
	public function articleDetail()
	{
		$aid = input('get.aid');
		$article = Db::table('tn_article')
		           ->alias('a')
		           ->join('tn_user u','a.uid = u.id')
		           ->field('u.name,u.wx_head,a.aid,a.title,a.thumb,a.content,a.create_time')
		           ->where('aid',$aid)
		           ->find();
		$article['create_time'] = date("Y-m-d H:i:s",$article['create_time']);
		return $article;
	}

	/**
	 * 首页商用车文章列表
	 * @return [type] [description]
	 */
	public function commercialArticleList()
	{
		$page = input('get.page')? : 1;
		$this->articleList($page,2);
	}
	/**
	 * 文章列表
	 * @param  
	 * @return [type]       [description]
	 */
	public function articleList($page,$mold){
		
		$pageSize = Config::get('page_size');
		$count = Db::table('tn_article')
				->where([
					'mold'   => $mold,
					'status' => [1,2]
				])
				->count();
		$rows = ceil($count / $pageSize);
		$list = Db::table('tn_article')
				->alias('a')
				->join('tn_user u','u.id = a.uid')
				->where([
					'a.mold' => $mold,
					'a.status' => [1,2]
				])
				->order('a.create_time desc')
				->page($page,$pageSize)
				->field('aid,title,thumb,u.name,status,a.create_time')
				->select();
		foreach ($list as $key => $value) {
			if($value['status']==2){
				$list[$key]['reward'] = Db::table('tn_worker_reward')
									->where('type',2)
									->where('mold',$mold)
									->where('acid',$value['aid'])
									->value('reward');
			} else {
				$list[$key]['reward'] = null;
			}
		}
		foreach ($list as $key => $value) {
			$date = date("Y-m-d H:i:s",$value['create_time']);
			$list[$key]['create_time'] = $date;
		}
		if($list){
			$this->result(['list' => $list,'rows'=>$rows],1,'获取信息成功');
		} else {
			$this->result('',0,'暂无数据');
		}
	}
	/**
	 * 首页乘用车文章列表
	 * @return [type] [description]
	 */
	public function passengerArticleList()
	{
		$page = input('get.page')? : 1;
		$this->articleList($page,1);
	}
}