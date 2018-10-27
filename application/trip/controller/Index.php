<?php 
namespace app\trip\controller;
use app\trip\controller\Index;
use think\Db;
use Config;
use MAP\Map;
use think\Cache;
use think\Controller;
/**
 * 约驾登录
 */
class Index extends Controller
{
	/**
 	* 限制地区内登录 lat lng
 	*/
 	public function astrict(){
 		//获取用户地理位置
 		$data = input('get.');
 		$res = Db::table('yue_user')
 				   ->where('u_id',$data['u_id'])
 				   ->update(['lat' => $data['lat'],'lng' => $data['lng']]);
	 	//    $location = new Map;
		//    $map = $location->location($data['lat'],$data['lng']);
	 	//    $gid = 2;
		// $list =  Db::table('am_astrict')
		//    			->where('gid',$gid)
		//    			->where('urban',$map['city'])
		//    			->select();
		if($res){
		   	$this->result('',1,"获取当前位置成功");
		}else{
		   	$this->result('',1,"获取当前位置失败");
		}
 	}
	public function login(){
		$data = input('get.');
		$openid = $this->getOpenId($data['code'])['openid'];
		$api_key = md5($openid.time());
		$us = Db::table('yue_user')
				  ->field('u_id')
				  ->where('open_id',$openid)
				  ->find();
		//获取用户信息
		if($data['nikename'] == null){
			$arr['nikename'] = $this->getNonceStr();
		}else{
			$arr['nikename'] = $data['nikename'];
		}
		$arr['head_image'] = $data['head_image'];
		$arr['sex'] = $data['sex'];
		if($us){
			//存在用户，更新
			Db::table('yue_user')->where('open_id',$openid)->update($arr);
			$uid = $us['u_id'];
		}else{
			//不存在用户添加
			$arr['open_id'] = $openid;
			//用户编号
			$arr['user_sn'] = build_only_sn();
			$uid = Db::table('yue_user')->insertGetId($arr);
		}
		\Cache::set('cu_'.$uid,$api_key,3600);
		$this->result(['u_id' => $uid,'openid'=>$openid],1,'登录成功');
	}
/**
 * 获取用户的openId
 */	
	function getOpenId($code){
		$appid = Config::get('appid');
		$secret = Config::get('secret');
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
/**
	 * 
	 * 产生随机字符串，不长于32位
	 * @param int $length
	 * @return 产生的随机字符串
	 */
	public function getNonceStr($length = 4) {
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";  
		$str ="";
		for ( $i = 0; $i < $length; $i++ )  {  
			$str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);  
		} 
		return "用户".$str;
	}

}