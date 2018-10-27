<?php 
namespace app\st\controller;
use app\base\controller\Store;
use think\Db;
use Config;
use WxJ\WxJ;
/**
 * 轮播图，更新经纬度，用户登录，获取openid
 */
class test extends Store 
{
	/**
	 * 更新用户经纬度
	 */
	public function upCoord()
	{
		$data = input('get.');
		$this->updateUserCoord($data,'st_user');
	}
	public function test()
	{
		$data = input('get.');
		// return $data;die();
		$sessionKey = $this->getOpenId($data['code'])['session_key'];//
		$wxj = new Wxj;
		$appid = $wxj = new WxJ;
		$appid = 'wx5860353a53d0a912';
		$encryptedData = $data['tel_encryptedData'];
		$iv = $data['tel_iv'];
		$info = $wxj->decryptData($sessionKey,$appid,$encryptedData,$iv);//解密后的数据
		return $info;die();
		return $data;die();
	}
	/**
	 * 登录注册
	 */
	public function login()
	{
		$data = input('get.');
		// return $data;die();
		// print_r($data);die();
		// return $data;die();
		$sessionKey = $this->getOpenId($data['code'])['session_key'];//可获得openid和session_key
		// $arr = $this->getOpenId($data['code']);
		// return $arr;die();
		$wxj = new Wxj;
		$appid = 'wx5860353a53d0a912';
		$encryptedData = $data['encryptedData'];
		$iv = $data['iv'];
		$info = $wxj->decryptData($sessionKey,$appid,$encryptedData,$iv);//解密后的数据
		// return $errCode;
		return $info;
		die();
		$api_key = md5($openid.time());
		$us = Db::table('st_user')
		      ->field('id')
		      ->where('open_id',$openid)
		      ->find();
		// 获取用户信息
		$arr['nick_name'] = $data['nick_name'];
		$arr['head_pic']  = $data['head_pic'];
		$arr['sex']       = $data['sex'];
		if($us){
			Db::table('st_user')->where('open_id',$openid)->update($arr);
			$uid = $us['id'];
		} else {
			// 如果不存在则添加此用户
			$arr['open_id'] = $openid;
			$arr['user_sn'] = build_only_sn();
			$uid = Db::table('st_user')->insertGetId($arr);
		}
		// \Cache::get('cu_'.$uid,$api_key,3600);
		$this->result(['uid' => $uid,'openid' => $openid],1,'登录成功');
	}
	/**
	 * 获取用户的openid和sessionkey
	 * @param  [type] $code [微信小程序临时认证code]
	 * @return [type]       [description]
	 */
	function getOpenId($code){
		$appid = 'wx5860353a53d0a912';
		$secret = 'a68efd3d0b55c9df7c454d8767cb3735';
		$url = "https://api.weixin.qq.com/sns/jscode2session?appid=$appid&secret=$secret&js_code=$code&grant_type=authorization_code";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($curl);
        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) == '200') {
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $body = substr($response, $headerSize);
        }
        return json_decode($body,true);
	}
}
