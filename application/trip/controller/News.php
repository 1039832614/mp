<?php 
namespace app\trip\controller;
use app\trip\controller\news;
use think\Controller;
use think\Db;
/**
 * 消息通知 
 */
class News extends Controller
{
	/**
	 * 系统消息列表全部
	 * @return [type] [description]
	 */
	public function msgL()
	{	 
		$uid = input('get.uid');
		// 系统消息 消息推送
		$this->getUrMsg($uid); 
		$page = input('get.page') ? : 1;
		$pageSize = 10;
		//总条数
		$counts = Db::table('yue_usermsg')
				->alias('um')
				->join(['am_msg'=>'m'],'um.mid = m.id')
				->where('uid',$uid)
				->where('sendto','like','%'.'9'.'%')
				->count();
		$rows = ceil($counts/$pageSize);
        $list= Db::table('yue_usermsg')
                ->alias('um')
                ->join(['am_msg'=>'m'],'um.mid = m.id')
                ->field('mid,static,title,create_time as time')
                ->where('uid',$uid)
                ->where('sendto','like','%'.'9'.'%')
                ->order('um.mid desc')
                ->page($page,$pageSize)
                ->select();
		if($list){
			$this->result(['list'=>$list,'rows'=>$rows],1,'获取列表成功');		
		} else {
			$this->result('',0,'获取列表失败');
		}
	}
	/**
	 * 获取系统消息详情
	 * @return [type] [description]
	 */
	public function detail()
	{
		$mid = input('get.mid');
		$uid = input('get.uid');
		$static = Db::table('yue_usermsg')
					->where('mid',$mid)
					->where('uid',$uid)
					->setField('static',1);
		$detail =Db::table('am_msg')
		            ->where('id',$mid)
		            ->field('title,content,create_time as time')
		            ->find();
		if($detail){
			$this->result($detail,1,'获取消息详情成功');
		} else {
			$this->result('',0,'获取消息详情失败');
		}
	}

/**
 * 活动通知 
 */
	/**
 	 *活动通知列表
 	 */
	public function actNews(){
		$page = input('get.page') ? : 1;
		$pageSize = 10;
		$uid = input('get.uid');
		//总条数
		$counts = Db::table('yue_actnews')->where('uid',$uid)->count();
		$rows = ceil($counts/$pageSize);
		$list = Db::table('yue_actnews')
					->where('uid',$uid)
					->field('id,title,time,static')
					->order('time desc')
					->page($page,$pageSize)
					->select();
		if($list){
			$this->result(['list'=>$list,'rows'=>$rows],1,'获取消息详情成功');
		}else{
			$this->result('',0,'暂无数据');
		} 
	}
	/**
 	 *活动通知详情
 	 */
	public function actDetail(){
		$id = input('get.id');
		$actnews = Db::table('yue_actnews')
					   ->where('id',$id)
					   ->field('title,time,content')
					   ->find();
		if($actnews){
			$static = Db::table('yue_actnews')
						->where('id',$id)
						->update(['static'=>'1']);
			$this->result($actnews,1,'获取消息详情成功');
		}else{
			$this->result('',0,'获取详情失败');
		}
	}
	/**
	 * 用户删除活动消息
	 * @return [type] [description]
	 */
	public function actDel(){
		$id = input('get.id');
		//总条数
		$list = Db::table('yue_actnews')->where('id',$id)->delete();
		if($list){
			$this->result('',1,'删除成功');
		}else{
			$this->result('',0,'删除失败');
		} 
	}

// 消息推送
    /**
	 * 获取系统发送的关于发送对象最后一条信息的id
	 */
	public function getLastMid($rid)
	{
		return Db::table('am_msg')->where('sendto','like','%'.$rid.'%')->max('id');
	}

    /**
     * 获取当前用户信息库的最后一条信息的id
     */
    public function getMaxMid($uid)
    {
        $u_mid = Db::table('yue_usermsg')->where('uid',$uid)->max('mid');
        return $u_mid ? $u_mid : 0;
    }
    /**
     * 获取未读取的信息数据 
     */
    public function getUrMsg($uid)
    {
        // 获取系统消息最后一条
        $s_mid = $this->getLastMid('9');
        // 获取消息库里最后一条
        $u_mid = $this->getMaxMid($uid);
        // 如果消息库里的id大于等于系统消息的id
        if($u_mid >= $s_mid){
            // 不做操作
            return false;
        }else{
            // 获取所差的数据条数
            $mids = Db::table('am_msg')
            		->where('id','>',$u_mid)
            		->where('sendto','like','%'.'9'.'%')
            		->column('id');
            // 将所差数据插入数据库
            foreach ($mids as $k => $v) {
                $data[$k] = ['uid'=>$uid,'mid'=>$v];
            }
            $res = Db::table('yue_usermsg')->insertAll($data);
            return $res;
        }
    }


    

}