<?php 
namespace app\cf\controller;
// use app\base\controller\Cf;
use think\Db;
use think\Controller;
use WxJ\WxJ;

/**
 * 车服管家用户登录
 */
class Index extends Controller
{
	public function initialize(){
		$this->appid = "wx795d1f9e2284a75e";
		$this->secret = "f2627bc25a37201a549df40b9233d73c";
	}
	public function login()
	{
		$data = input('get.');
		$safe = $this->getOpenId($data['code']);
		$wxj = new Wxj;
		$info = $wxj->decryptData($safe['session_key'],$this->appid,$data['encryptedData'],$data['iv']);
		$us = Db::table('o_user')
				->field('id')
				->where('open_id',$safe['openid'])
				->find();
		//构建入库数据
		$arr = [
			'open_id'   => $safe['openid'],
			'unionId'   => $info['unionId'],
			'head_pic'  => $info['avatarUrl'],
 			'nick_name' => $info['nickName'],
			'sex'       => $info['gender']
		];
		//判断用户是否存在
		if($us) {
			Db::table('o_user')->where('open_id',$safe['openid'])->update($arr);
			$uid = $us['id'];
		} else {
			$uid = Db::table('o_user')->insertGetId($arr);
		}
		$this->result(['uid'=>$uid,'openid'=>$safe['openid'],'unionId'=>$info['unionId']],1,'登录成功');
	}
	/**
	 * 获取用户的openid和sessionkey
	 * @param  [type] $code [微信小程序临时认证code]
	 * @return [type]       [description]
	 */
	function getOpenId($code){
		$appid = $this->appid;
		$secret = $this->secret;
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