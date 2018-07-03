<?php 
namespace app\worker\controller;
use app\base\controller\Worker;
use think\Db;
use Config;

class Index extends Worker
{
	/**
	 * 获取轮播图列表
	 */
	public function getBannerList()
	{
		$this->bannerList(1);//正式测试时，改为 3
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
	 * 查看用户是否认证
	 */
	public function identification()
	{
		$uid = input('get.uid');
		$user = Db::table('tn_user')
				->where('id',$uid)
				->field('name,phone,cert')
				->find();
		if($user){
			$this->result(['user'=>$user],1,'获取数据成功');
		} else {
			$this->result('',0,'请完善您的个人信息');
		}
	}
	/**
	 * 更新用户坐标
	 */
	public function upCoord()
	{
		$data = input('get.');
		$this->updateUserCoord($data,'tn_user');
	}

	/**
	 * 登录注册
	 * @return [type] [description]
	 */
	public function login()
	{
		$data = input('get.');
		$openid = $this->getOpenId($data['code'])['openid'];
		$api_key = md5($openid.time());
		$us = Db::table('tn_user')->field('id')->where('open_id',$openid)->find();
		//获取用户信息
		$arr['nick_name'] = $data['nick_name'];
		$arr['head_pic'] = $data['head_pic'];
		$arr['sex'] = $data['sex'];
		//如果存在，则更新此用户相关数据
		if($us){
			Db::table('tn_user')->where('open_id',$openid)->update($arr);
			$uid = $us['id'];
		} else {
			//如果不存在则添加此用户
			$arr['open_id'] = $openid;
			$arr['user_sn'] = build_only_sn();
			$uid = Db::table('tn_user')->insert($arr);
		}
		\Cache::set('tn_'.$uid.$api_key,3600);
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