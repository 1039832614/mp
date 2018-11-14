<?php 
namespace app\cb\controller;
use app\base\controller\Bby;
use think\Db;
use think\Cache;
use Msg\Msg;
use Config;
use Epay\BbyEpay;
use WxJ\WxJ;
use MAP\Map;
/**
* 首页内容
*/
class Index extends Bby
{

    /**
     * 判断用户是否是会员
     * @return [type] [description]
     */
    public function ifMember()
    {
        $uid = input('post.uid');
        $list = Db::table('u_member_table')->where(['uid'=>$uid,'pay_status'=>1])->where('end_time','>',date('Y-m-d H:i:s'))->count();
        if($list > 0){
            $expire = Db::table('u_member_table')->where(['uid'=>$uid,'pay_status'=>1])->where('end_time','>',date('Y-m-d H:i:s'))->count();
            if($expire > 0){
                 $this->result('',1,'该用户为会员');
            }else{
                $this->result('',2,'用户会员过期');
            }  
        }else{
            $this->result('',0,'该用户不是会员');
        }
    }



	/**
	 * 获取轮播图列表
	 */
	public function getBannerList()
	{
		$this->bannerList(1);
	}

	/**
	 * 获取轮播图详情
	 */
	public function getBannerDetail()
	{
		$id = input('get.id');
		$this->bannerDetail($id);
	}

	/**
	 * 更新用户坐标
	 */
	public function upCoord()
	{
		$lat = input('get.lat');
		$lng = input('get.lng');
		$uid = input('get.uid');
		$data = [
			'lat'=>$lat,
			'lng'=>$lng,
		];
		$this->upUserCoord($data,$uid,'u_user');
	}

	/**
	 * 获取统计数字
	 */
	public function statistics()
	{
		// 获取服务次数     注：查询邦保养服务次数
		$ser_num = Db::table('cs_income')->count();
		// 获取店铺数       注：查询售卡数量
		$shop_num = Db::table('u_card')->count();
		// 获取本年度邦保养    注：查询用户关注度
		$year_num = Db::table('u_user')->whereTime('create_time','year')->count();
		// 返回数据
		$this->result(['ser_num' => $ser_num+334573, 'shop_num' => $shop_num, 'year_num' => $year_num],1,'获取成功');
	}

    /**
     * 获取消息列表
     */
    public function getMsgs()
    {
        $uid = input('get.uid');
        // 初始化信息扩展
        $Msg = new Msg();
        // 从数据拉取信息
        $mids = $Msg->getUrMsg(1,'u_msg',$uid);
        // 获取未读消息
        $data = Db::table('u_msg')
                ->alias('um')
                ->join(['am_msg'=>'am'],'um.mid = am.id')
                ->field('um.mid,am.title')
                ->where('uid',$uid)
                ->where('status',0)
                ->select();
        // 返回给前端数据
        if($data){
            $this->result($data,1,'获取信息成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }
    

    /**
     * 用户登录操作
     */
    public function login()
    {
        $data = input('get.');
        // print_r($data);exit;
        $openid = $this->getOpenId($data['code']);
        $api_key = md5($openid['openid'].time());
        $wxj = new WxJ;
        $us = Db::table('u_user')->field('id')->where('open_id',$openid['openid'])->find();
        $unionId = $wxj->decryptData($openid['session_key'],$openid['openid'],$data['encryptedData'],$data['iv']);
        // 获取用户信息
        $arr['nick_name'] = $data['nick_name'];
        $arr['head_pic'] = $data['head_pic'];
        $arr['sex'] = $data['sex'];
        $arr['pid'] = $data['pid'];
        $arr['unionId'] = $unionId['unionId'];
        $arr['lat'] = $data['lat'];
        $arr['lng'] = $data['lng'];
        // 如果存在，则更新此用户相关数据
        if($us){
             // 查看用户是否是会员
            // $count = Db::table('u_member_table')->where('uid',$us['id'])->where('end_time','>',date('Y-m-d H:i:s'))->count();
            // xjm 2018.10.27 14:33
            $count = Db::table('u_member_table')
                    ->where([
                        'uid'        => $us['id'],
                        'pay_status' => 1
                    ])
                    ->where('end_time','>',date('Y-m-d H:i:s'))
                    ->count();
            if($count > 0){
                $status = 1;//1为会员
            }else{
                $status = 0;
            }
        	unset($arr['pid']);
            Db::table('u_user')->where('open_id',$openid['openid'])->update($arr);
            $uid = $us['id'];
             // 老用户
            $code = 2;

        }else{
            // 如果不存在则添加此用户
            $arr['open_id'] = $openid['openid'];
            $arr['user_sn'] = build_only_sn();
            $uid = Db::table('u_user')->insertGetId($arr);
            if($arr['pid'] == 0){
                // 不是分享进来的新用户
                $code = 3;
            }else{
                // 通过分享进来的新用户
                $code = 1;
            }
             $status = 0;
        }
        \Cache::set('cu_'.$uid,$api_key,3600);
        $this->result(['uid' => $uid,'openid'=>$openid['openid'],'code2'=>$code,'status'=>$status],1,'登录成功');
    }

    // /**
    //  * 获取用户是否有领取礼品
    //  * @return [type] [description]
    //  */
    public function gift()
    {
    	//获取用户id
    	$uid = input('get.uid');
    	// print_r($uid);exit;
    	// 查看用户是否有支付成功且卡类型为4 的邦保养卡
    	$card = Db::table('u_card')->where(['uid'=>$uid,'pay_status'=>1,'card_type'=>4])->count();
    	if($card > 0){
    		//查看用户购买的邦保养卡号
    		$card_number = Db::table('u_card')->where(['uid'=>$uid,'pay_status'=>1,'card_type'=>4])->field('card_number,sid,id')->order('id desc')->find();
    		//查看邦保养卡号是否在礼品表有没有此卡号
    		$gift = Db::table('cs_gift')->where('card_number',$card_number['card_number'])->order('id desc')->count(); 
    		if($gift > 0){
    			$this->result('',1,'已领取礼品');
    		}else{
    			$this->result(['sid'=>$card_number['sid'],'cid'=>$card_number['id']],0,'您有未领取的礼品！');
    		}
    	}else{
    		$this->result('',2,'暂无购卡');
    	}
    }
	
	 /**
     * 获取用户的openId
     */
    function getOpenId($code){
        $appid = Config::get('appid');
        $secret = Config::get('key');
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid=$appid&secret=$secret&js_code=$code&grant_type=authorization_code";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);//这个是重点。
        $response = curl_exec($curl);
        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) == '200') {
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $body = substr($response, $headerSize);
        }
        return json_decode($body,true);
    }
    /**
     * 是否有抽奖机会
     */
    public function chance(){
        $uid = input('get.uid');
        $list = Db::table('u_user')->where('id',$uid)->field('lottery,status')->find();
        $cid= Db::table('u_card')
                ->where('uid',$uid)
                ->order('id asc')
                ->limit('1')
                ->column('id');
        $total = Db::table('u_card')->where('id','in',$cid)->value('card_price');
        // $this->result('',1,'没有抽奖机会');
        if($list['lottery'] <= 0 ){
                $this->result('',1,'没有抽奖机会');
            }else{
                $this->result(['cid'=>$cid,'total'=>$total,'status'=>$list['status']],0,'有抽奖机会');
            }
    }

     /**
     * 显示是分享球还是能量球
     * @return [type] [description]
     */
    public function ball()
    {
    	$uid = input('post.uid');
    	$old = Db::table('u_user')->where('id',$uid)->value('old');
    	if($old == 3){
    		Db::table('u_user')->where('id',$uid)->setField('old',1);
    	}
    	// print_r($uid);exit;
    	 // 获取用户所在区县id
        $county_id = $this->city($uid);
        $free_times = Db::table('ca_area ca')
        			->join('ca_agent as','ca.aid = as.aid')
        			->where('area',$county_id)
        			->value('free_times');
        // print_r($free_times);exit;
        
    	//查询用户表用户是否是首次关注
        $old = Db::table('u_user')->where('id',$uid)->value('old');
         // 查询购卡表是否有一次性卡,免费体验入表入为一次性卡，如有一次性卡，则返回能量值
        $count = Db::table('u_card')->where(['uid'=>$uid,'card_type'=>1,'remain_times'=>1,'transaction_id'=>0])->field('trade_no,id')->find();
        // if($old == 0){
        // 	if($free_times <= 0 || empty($county_id)){
        // 		$this->result($free_times,6,'该地区已无免费次数,不显示球');
        // 	}else{
        // 		Db::table('u_user')->where(['id'=>$uid])->setField('old',3);
        // 		$this->result('',1,'分享球');
        // 	}
        // }else if($count > 0){
        // 	$this->result('',0,'能量球');
        // }
        if($old == 0){
            if($free_times <= 0 || empty($county_id)){
                $this->result(['free_times'=>$free_times],6,'该地区已无免费次数,不显示球');
            }else{
                Db::table('u_user')->where(['id'=>$uid])->setField('old',3);
                $this->result(['free_times'=>$free_times],1,'分享球');
            }
        }else if($count > 0){
            $this->result('',0,'能量球');
        }else if($old == 1){
            $this->result('',2,'没有球');
        }
    }

   

    /**
     * 判断用户是否是第一次关注
     * @return [type] [description]
     */
    public function ifFirst()
    {
        // 获取用户id
        $uid = input('post.uid');

        // 获取用户所在区县id
        $county_id = $this->city($uid);
        if(empty($county_id)) $this->result('',7,'该地区无运营商，则没有免费次数');

        $free_times = Db::table('ca_area ca')
        			->join('ca_agent as','ca.aid = as.aid')
        			->where('area',$county_id)
        			->value('free_times');
        // print_r($free_times);exit;
        if($free_times <= 0){
        	$this->result(['free_times'=>$free_times],6,'该地区已无免费次数');
        }
        //查询用户表用户是否是首次关注
        $old = Db::table('u_user')->where('id',$uid)->value('old');
        // 查询购卡表是否有一次性卡,免费体验入表入为一次性卡，如有一次性卡，则返回能量值
        $count = Db::table('u_card')->where(['uid'=>$uid,'card_type'=>1,'remain_times'=>1,'transaction_id'=>0])->field('trade_no,id,uid,sid')->find();
        
        // 判断此用户是否是分享关注
        $pid = Db::table('u_user')->where(['old'=>0,'id'=>$uid])->value('pid');
        // 获取用户已获得能量值
        $num = Db::table('u_user')->where(['pid'=>$uid,'status'=>1])->count();
        // $energy = floor($num * 3);
        // old等于0则为新关注
        if($old == 0 && $count <= 0){

            // 修改用户为已关注
            $this->result(['free_times'=>$free_times],1,'此用户为新关注,显示体验球');
        }else if($old == 3 && $count <= 0){
        	// 修改用户为已关注
            $this->result(['free_times'=>$free_times],9,'此用户正在查看小程序,体验球');
        }else if($pid > 0){


            $this->result(['free_times'=>$free_times],5,'此用户通过分享链接进入，进完善信息页面');

        }else if($count){
            
            $this->result(['free_times'=>$free_times,'uid'=>$uid],2,'此用户已点击体验，显示能量球');

        }else{
            $this->result(['free_times'=>$free_times],4,'此用户为老用户');
        }
    }
    
    // 
    // /**
    //  * 判断用户是否是第一次关注
    //  * @return [type] [description]
    //  */
    // public function ifFirst()
    // {
    //     // 获取用户id
    //     $uid = input('post.uid');
    //     // 获取用户所在区县id
    //     $county_id = $this->city($uid);
    //     if(empty($county_id)) $this->result('',7,'该地区无运营商，则没有免费次数');

    //     $free_times = Db::table('ca_area ca')
    //                 ->join('ca_agent as','ca.aid = as.aid')
    //                 ->where('area',$county_id)
    //                 ->value('free_times');
    //     // print_r($free_times);exit;
    //     if($free_times <= 0){
    //         $this->result($free_times,6,'该地区已无免费次数');
    //     }
    //     //查询用户表用户是否是首次关注
    //     $old = Db::table('u_user')->where('id',$uid)->value('old');
    //     // 查询购卡表是否有一次性卡,免费体验入表入为一次性卡，如有一次性卡，则返回能量值
    //     $count = Db::table('u_card')->where(['uid'=>$uid,'card_type'=>1,'remain_times'=>1,'transaction_id'=>0])->field('trade_no,id')->find();
        
    //     // 判断此用户是否是分享关注
    //     $pid = Db::table('u_user')->where(['old'=>0,'id'=>$uid])->value('pid');
    //     // 获取用户已获得能量值
    //     $num = Db::table('u_user')->where(['pid'=>$uid,'status'=>1])->count();
    //     // $energy = floor($num * 3);
    //      if($count){
            
    //         $this->result(['free_times'=>$free_times,'uid'=>$uid],2,'此用户已点击体验，显示能量球');

    //     }else if($energy == $count['trade_no'] && $count['trade_no'] > 0){
    //         // 能量值满则改为已支付
    //         $res = Db::table('u_card')->where(['id'=>$count['id'],'uid'=>$uid])->setField('pay_status',1);
    //         // 用户能量值
    //         $this->result(['free_times'=>$free_times,'count'=>$count],3,'此用户能量值已满，提醒到店体验');
    //     }
    // }


    /**
     * 能量页面
     * @return [type] [description]
     */
    public function enerList()
    {
        $uid = input('post.uid');
        // 查询购卡表是否有一次性卡,免费体验入表入为一次性卡，如有一次性卡，则返回能量值
        $count = Db::table('u_card')->where(['uid'=>$uid,'card_type'=>1,'remain_times'=>1,'transaction_id'=>0])->order('id desc')->limit(1)->field('trade_no,sid,id')->find();
        // 获取用户已获得能量值
        $num = Db::table('u_user')->where(['pid'=>$uid,'status'=>1])->count();
        // $energy = floor($num * 3);
        //帮助集能量列表
        $follow = Db::table('u_user')->where('pid',$uid)->field('nick_name,create_time,head_pic')->order('id desc')->select();
        if($count){
        	if($num >= $count['trade_no'] && $count['trade_no'] > 0 ){
            // 能量值满则改为已支付
	            $res = Db::table('u_card')->where(['id'=>$count['id'],'uid'=>$uid])->setField('pay_status',1);
	            // 用户能量值
	            $this->result(['count'=>$count['trade_no'],'energy'=>$num,'follow'=>$follow,'sid'=>$count['sid']],1,'此用户能量值已满，提醒到店体验');
            }else{
            	$this->result(['count'=>$count['trade_no'],'energy'=>$num,'follow'=>$follow,'sid'=>$count['sid']],1,'能量页成功');
            }
            
        }else{
            $this->result('',0,'能量页失败');
        }   

    }



    /**
     * 能量集成功
     * @return [type] [description]
     */
    public function freeSucc()
    {
        $list = Db::table('u_card uc')
            ->join('u_user uu','uc.uid = uu.id')
            ->where(['pay_status'=>1,'transaction_id'=>0])
            ->field('uid,plate,lat,lng,cate_name')
            ->select();
        if(empty($list)) $this->result('',0,'暂无数据');
        foreach ($list as $k => $v) {
            $map = new Map();
            $list[$k]['address'] = $map->location($v['lat'],$v['lng']);
            $a = $list[$k]['address']['province'].$list[$k]['address']['city'].$list[$k]['address']['district'].$v['plate'].'车 集满能量'.','.'获得邦保养免费体验。';
            $arr[] = $a;
        }
        if($arr){
            $this->result($arr,1,'获取中奖列表成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    /**
     *  获取用户所在县区id
     * @param  [type] $uid [description]
     * @return [type]      [description]
     */
    public function city($uid)
    {
        // 根据uid获取用户的经纬度
        $data = Db::table('u_user')->where('id',$uid)->field('lat,lng')->find();
        // print_r($data);exit;
        if(empty($data)){ $this->result('',8,'未获取到用户经纬度');};
       $map = new Map();
       $city = $map->location($data['lat'],$data['lng']);
//        print_r($city);exit;
       // 根据县区获取县区id
       $county_id = Db::table('co_china_data')->where('name','like',$city['district'].'%')->value('id');
       // echo Db::table('co_china_data')->getLastSql();exit;
       // print_r($county_id);exit;
       return $county_id;
    }
}