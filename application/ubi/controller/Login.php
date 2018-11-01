<?php 
namespace app\ubi\controller;

use app\base\controller\Ubi;
use think\Db;
use WxJ\WxJ;

/*
 *  登录    
 *
 */

CLass Login extends Ubi
{
    
    /**
     * 更新坐标
     * 
     */
    
    public function upCoord()
    {
        $data = input('get.');

        $result = DB::Table('cb_user')->where('u_id',$data['uid'])->update(
        [
            'lat'=>$data['lat'],
            'lng'=>$data['lng'],
        ]
        );

        if(!$result){
            $this->result('',0,'失败');
        }
        $this->result('',1,'成功');
    }

    /**
     * 登录
     * 
     */
    
    public function Login(WxJ $wxj)
    {
    	$data = input('get.');

    	// 获取用户的openid和sessionkey
    	$safe = $this->getOpenId($data['code']);
    	// 获取用户信息
        $info = $wxj->decryptData($safe['session_key'],Config('appid'),$data['encryptedData'],$data['iv']);
  
        // 构造入库信息
        $arr = [
            'open_id'   => $safe['openid'],
            'unionId'   => $info['unionId'],
            'head_pic'  => $info['avatarUrl'],
            'nick_name' => $this->filterEmoji($info['nickName']),
            'sex'       => $info['gender']
        ];
     
        // 获取用户信息
        $us = Db::table('cb_user')
            ->field('u_id')
            ->where('open_id',$safe['openid'])
            ->find();
        //判断用户是否存在
        if($us) {
            if ($arr['unionId']) {
               Db::table('cb_user')->where('open_id',$safe['openid'])->update($arr); 
            }
            
            $uid = $us['u_id'];
            $loding = 1;
        } else {
            $uid = Db::table('cb_user')->insertGetId($arr);
            $loding = 1;
        }
        
        $this->result(['uid'=>$uid,'openid'=>$safe['openid'],'unionId'=>$info['unionId'],'loding'=>$loding],1,'登录成功');
    }


    /**
     * 获取用户的openid和sessionkey
     * @param  [type] $code [微信小程序临时认证code]
     * @return [type]       [description]
     * 
     */
    
    public function getOpenId($code){

        $appid = Config('appid');
        $secret = Config('secret');
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
        // print_R($response);die;
        return json_decode($body,true);
    }  
    
    
    /**
     * 获取头像？
     * 
     */
    
    public function filterEmoji($str)
    {
        $str = preg_replace_callback( '/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '' : $match[0];
            },
            $str);

        return $str;
    }    


}