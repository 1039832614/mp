<?php 
namespace app\cb\controller;
use app\base\controller\Bby;
use think\Db;
use Msg\Msg;
use Config;
/**
* 个人中心
*/
class Center extends Bby
{

    // //获取引荐成功人数
    public function followCount()
    {
        $uid = input('post.uid');
        if(empty($uid)) $this->result('',0,'参数错误');

        $count = Db::table('u_user')->where('pid',$uid)->count();
        if($count > 0){
        	$this->result($count,1,'获取成功');
        }else{
        	$this->result('',0,'暂无分享关注者！');
        }
        
    }

    /**
     * 引荐成功的人数详情
     * @return [type] [description]
     */
    public function followList()
    {
    	// 获取当前页   获取用户id
    	$page = input('post.page')? :1;
    	$uid = input('post.uid');
    	$pageSize = 10;
    	$count = Db::table('u_user')->where('pid',$uid)->count();
    	$rows = ceil($count / $pageSize);
    	$list = Db::table('u_user')->where('pid',$uid)->field('nick_name,head_pic,create_time')->page($page,$pageSize)->order('id desc')->select();
    	if($count > 0){
    		$this->result($list,1,'获取列表成功');
    	}else{
    		$this->result('',0,'暂无数据');
    	}
    }


    //获取买卡人数
     public function upperCount()
     {

        $uid = input('post.uid');
         
        if(empty($uid)) $this->result('',0,'参数错误');

        $count = DB::table('u_card')->where(['share_uid'=>$uid,'pay_status'=>1])->count();
        if($count > 0){
        	$this->result($count,1,'获取成功');
        }else{
        	$this->result('',0,'暂无分享成功购卡者！');
        }

     }


     /**
     * 引荐成功购卡的人数详情
     * @return [type] [description]
     */
    public function upperList()
    {
    	// 获取当前页   获取用户id
    	$page = input('post.page')? :1;
    	$uid = input('post.uid');
    	$pageSize = 10;
    	$count = Db::table('u_card')->where('share_uid',$uid)->count();
    	$rows = ceil($count / $pageSize);
    	$list = Db::table('u_card uc')
    			->join('u_user uu','uu.id = uc.uid')
    			->where(['share_uid'=>$uid,'pay_status'=>1])
    			->page($page,$pageSize)
    			->field('nick_name,head_pic,sale_time')
    			->select();
    	if($count > 0){
    		$this->result($list,1,'获取列表成功');
    	}else{
    		$this->result('',0,'暂无数据');
    	}
    }




	/**
	 * 邦保养记录列表
	 */
	public function bangLogs()
	{
		$page = input('get.page') ? : 1;
		$uid = input('get.uid');
		// 获取每页条数
		$pageSize = Config::get('page_size');
		$count = Db::table('cs_income')->where('uid',$uid)->count();
		$rows = ceil($count / $pageSize);
		// 获取数据
		$list = Db::table('cs_income')
				->alias('i')
				->join(['cs_shop'=>'s'],'i.sid = s.id')
				->join(['u_card'=>'c'],'i.cid = c.id')
				->field('company,plate,i.create_time,i.oil,i.id as bid,i.sid,if_comment,if_complain')
				->where('i.uid',$uid)
				->order('i.id desc')
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
	 * 发票列表
	 */
	public function receiptLogs()
	{
		$page = input('get.page') ? : 1;
		$uid = input('get.uid');
		// 获取每页条数
		$pageSize = Config::get('page_size');
		$count = Db::table('u_tax')->where('uid',$uid)->count();
		$rows = ceil($count / $pageSize);
		// 获取数据
		$list = Db::table('u_tax')
				->alias('t')
				->join(['u_card'=>'c'],'t.cid = c.id')
				->field('total,plate,cate_name,t.create_time,t.status,card_number')
				->where('t.uid',$uid)
				->order('t.id desc')
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
	 * 我的卡包
	 */
	public function cardList()
	{
		$page = input('get.page') ? : 1;
		$uid = input('get.uid');
		// 获取每页条数
		$pageSize = Config::get('page_size');
		$count = Db::table('u_card')->where('uid',$uid)->where('pay_status',1)->count();
		$rows = ceil($count / $pageSize);
		// 获取数据
		$list = Db::table('u_card')
				->alias('c')
				->join(['co_bang_data' => 'd'],'c.car_cate_id = d.cid')
				->field('card_price,plate,oil_name,card_number,litre,sale_time,remain_times')
				->where('uid',$uid)
				->where('pay_status',1)
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
	 * 赠品列表
	 */
	public function giftList()
	{
		$page = input('get.page') ? : 1;
		$uid = input('get.uid');
		// 获取每页条数
		$pageSize = Config::get('page_size');
		$count = Db::table('cs_gift')->where('uid',$uid)->count();
		$rows = ceil($count / $pageSize);
		// 获取数据
		$list = Db::table('cs_gift')
				->field('gift_name,excode,create_time,status')
				->where('uid',$uid)
				->order('id desc')
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
	 * 联保记录
	 */
	public function careLogs()
	{
		$this->result('',0,'暂无数据');
	}

	/**
	 * 消息列表
	 */
	public function msgList()
	{
		$msg = new Msg();
		$page = input('post.page') ? : 1;
		$uid = input('get.uid');
		$list = $msg->msgList('u_msg',$uid,$page);
		if(count($list['list']) > 0){
			$this->result($list,1,'获取消息列表成功');
		}else{
			$this->result('',0,'暂无数据');
		}
	}


	/**
	 * 消息详情
	 */
	public function msgDetail()
	{
		$mid = input('get.mid');
		$uid = input('get.uid');
		$msg = new Msg();
		$datil = $msg->msgDetail('u_msg',$mid,$uid,1);
		if($datil){
			$this->result($datil,1,'获取消息详情成功');
		}else{
			$this->result('',0,'获取消息详情失败');
		}
	}


	/**
	 * 分享收入
	 */
	public function incomeList()
	{
		$page = input('post.page') ? : 1;
		$uid = input('get.uid');
		// 获取每页条数
		$pageSize = Config::get('page_size');
		$count = Db::table('u_share_income')->where('uid',$uid)->count();
		$rows = ceil($count / $pageSize);
		// 获取数据
		$list = Db::table('u_share_income')
				->field('reward,create_time')
				->where('uid',$uid)
				->order('id desc')
				->page($page, $pageSize)
				->select();
		// 获取总金额
		$total = Db::table('u_share_income')->where('uid',$uid)->sum('reward');
		// 返回给前端
		if($count > 0){
			$this->result(['list'=>$list,'rows'=>$rows,'total'=>$total],1,'获取成功');
		}else{
			$this->result('',0,'暂无数据');
		}
	}

	/**
	 * 获取个人信息
	 */
	public function getInfo()
	{
		$uid = input('get.uid');
		$data = Db::table('u_user')->field('name,phone')->where('id',$uid)->find();
		if(!empty($data['name']) && !empty($data['phone'])){
			$this->result($data,1,'获取成功');
		}else{
			$this->result($data,0,'暂无信息');
		}
	}


	/**
	 * 完善个人信息
	 */
	public function perfect()
	{
		$data = input('get.');
		// 实例化验证
		$validate = validate('Perfect');
		if($validate->check($data)){
			$res = Db::table('u_user')->where('id',$data['uid'])->update(['name'=>$data['name'],'phone'=>$data['phone'],'status'=>1]);
			if($res !== false){
				$this->result('',1,'保存成功');
			}else{
				$this->result('',0,'提交异常');
			}
		}else{
			$this->result('',0,$validate->getError());
		}
	}

	/**
	 * 进行换店服务
	 */
	public function exshop()
	{
		// 输入换店信息
		$data = input('post.');
		$validate = validate('Exshop');
		if($validate->check($data)){	
			if($data['extype'] == 1){
				if(empty($data['reason'])){
					$this->result('',0,'换店理由不能为空');
				}
			}
			$data['aid'] = 	Db::table('u_card')
					->alias('c')
					->join(['cs_shop'=>'s'],'c.sid = s.id')
					->join(['ca_agent'=>'a'],'s.aid = a.aid')
					->where('card_number',$data['card_number'])
					->value('a.aid');
			$add = Db::table('u_ex_shop')->insert($data);
			// 向运营商发短信进行投诉->此处需填补
			// 修改用户卡的绑定
			$ex = Db::table('u_card')->where('card_number',$data['card_number'])->setField('sid',$data['new_sid']);
			if($ex !== false){
				$this->result('',1,'换店成功');
			}else{
				$this->result('',0,'提交异常');
			}
		}else{
			$this->result('',0,$validate->getError());
		}
	}

	/**
	 * 获取邦保养卡列表
	 */
	public function getCardList()
	{
		$uid = input('get.uid');
		$list = Db::table('u_card')->where('uid',$uid)->where('pay_status',1)->field('id as cid,card_number')->order('id desc')->select();
		if(count($list) > 0){
			$this->result($list,1,'获取成功');
		}else{
			$this->result('',0,'暂无数据');
		}
	}

	/**
	 * 获取邦保养卡所在店铺
	 */
	public function getcardShop()
	{
		$cid = input('get.cid');
		$shop = Db::table('u_card')
				->alias('c')
				->join(['cs_shop'=>'s'],'c.sid=s.id')
				->field('s.id as sid,company')
				->where('c.id',$cid)
				->find();
		if($shop){
			$this->result($shop,1,'获取成功');
		}else{
			$this->result('',0,'获取数据异常');
		}
	}
	
}