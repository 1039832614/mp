<?php
namespace app\travel\controller;
use app\base\controller\Travel;
use think\Db;
use Config;

class Index extends Travel
{ 

    /**
     * 获取轮播图列表
     */
    public function getBannerList()
    {
        $this->bannerList(1);//正式上线时改为2
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
        $this->updateUserCoord($data,'yue_user');
    }

    /**
     * 用户登录操作
     */
    public function login()
    {
        $data = input('get.');
        $openid = $this->getOpenId($data['code'])['openid'];
        $api_key = md5($openid.time());
        $us = Db::table('yue_user')->field('id')->where('open_id',$openid)->find();
        // 获取用户信息
        $arr['nick_name'] = $data['nick_name'];
        $arr['head_pic'] = $data['head_pic'];
        $arr['sex'] = $data['sex'];
        // 如果存在，则更新此用户相关数据
        if($us){
            Db::table('yue_user')->where('open_id',$openid)->update($arr);
            $uid = $us['id'];
        }else{
            // 如果不存在则添加此用户
            $arr['open_id'] = $openid;
            $arr['user_sn'] = build_only_sn();
            $uid = Db::table('yue_user')->insert($arr);
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
