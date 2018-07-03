<?php 
namespace app\cb\controller;
use app\base\controller\Bby;
use think\Db;
use think\Cache;
use Msg\Msg;
use Config;
/**
* 首页内容
*/
class Index extends Bby
{
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
		$data = input('get.');
		$this->upUserCoord($data,'u_user');
	}

	/**
	 * 获取统计数字
	 */
	public function statistics()
	{
		// 获取服务次数
		$ser_num = Db::table('cs_income')->count();
		// 获取店铺数
		$shop_num = Db::table('cs_shop')->count();
		// 获取本年度邦保养
		$year_num = Db::table('cs_income')->whereTime('create_time','year')->count();
		// 返回数据
		$this->result(['ser_num' => $ser_num+324573, 'shop_num' => $shop_num+2156, 'year_num' => $year_num+17232],1,'获取成功');
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
        $openid = $this->getOpenId($data['code'])['openid'];
        $api_key = md5($openid.time());
        $us = Db::table('u_user')->field('id')->where('open_id',$openid)->find();
        // 获取用户信息
        $arr['nick_name'] = $data['nick_name'];
        $arr['head_pic'] = $data['head_pic'];
        $arr['sex'] = $data['sex'];
        // 如果存在，则更新此用户相关数据
        if($us){
            Db::table('u_user')->where('open_id',$openid)->update($arr);
            $uid = $us['id'];
        }else{
            // 如果不存在则添加此用户
            $arr['open_id'] = $openid;
            $arr['user_sn'] = build_only_sn();
            $uid = Db::table('u_user')->insertGetId($arr);
        }
        \Cache::set('cu_'.$uid,$api_key,3600);
        $this->result(['uid' => $uid,'openid'=>$openid],1,'登录成功');
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
}