<?php
namespace app\base\controller;
use app\base\controller\Base;
use Msg\Sms;
use think\Db;
use Geo\Geo;
use Config;
class Worker extends Base 
{
	function initialize()
	{
		$this->sms = new Sms();
	}
	
    /**
     * 生成API验证码
     */
    public function apiVerify()
    {
        return mt_rand(1000,9999);
    }

    /**
     * 手机发送验证码
     * @param  [type] $phone   [手机号]
     * @param  [type] $content [短信发送内容]
     * @return [type]          [发送成功或失败]
     */
    public function smsVerify($phone,$content,$code = '')
    {
        return $this->sms->send_code($phone,$content,$code);
    }


     /**
     * 进行邦保养操作时，发送手机验证码
     * @return [type] [发送成功或失败]
     */
    public function forCode($phone,$card_number)
    {
        // 生成四位验证码
        // 您邦保养卡号为【{$card_number}】参与本次保养的验证码为【{$code}】，请勿泄露给其他人。
        $code=$this->apiVerify();
        $content = "您邦保养卡号为：【".$card_number."】参与本次保养的验证码为：【".$code."】，请勿泄露给其他人。";
        // $content="您的短信验证码是：【".$code."】。您正在通过手机号重置登录密码，如非本人操作，请忽略该短信。";
       return  $this->smsVerify($phone,$content,$code);
    }
    /**
     * 获取用户的位置信息
     * @param  $uid 用户id
     * @return $user_location 经纬度
     */
    function getLocation($id,$table){
        $user_location = Db::table("$table")
                         ->where('id',$id)
                         ->field('lat,lng')
                         ->find();
        return $user_location;
    }

    /**
     * 获取推荐店铺
     * 传入数据lat,lng,page,prefix
     */
    public function shopList($data,$prefix)
    {
        $geo = new Geo();
        $hash = $geo->encode_hash($data['lat'],$data['lng']);
        $hv = substr($hash,0,1);
        // 分页获取数据
        $page = $data['page'] ? : 1;
        $list = Db::table($prefix.'_shop')
                ->alias('sp')
                ->join([$prefix.'_shop_set'=>'st'],'sp.id = st.sid')
                ->field('sid,company,photo,about,serphone,lng,lat')
                ->whereLike('hash_val',$hv.'%')
                ->where('audit_status',2)
                ->page($page,Config::get('page_size'))
                ->select();
        foreach ($list as $k => $v) {
            $photo = str_replace(['\\'], ["/"], $v['photo']);
            $list[$k]['photo'] = json_decode($photo,true);
        }
        if($list){
            $res = $geo->sortByDistance($list,$data['lat'],$data['lng'],200);
            $this->result($res,1,'获取数据成功');
        }else{
            $this->result('',0,'您所在地区暂无修理厂');
        }
        
    }
    /**
     * 更新用户经纬度
     */
    public function updateUserCoord($data,$table)
    {
        $geo = new Geohash();
        // $data['hash_val'] = $geo->encode_hash($data['lat'],$data['lng']);
        $res = Db::table($table)->where('uid',$data['uid'])->update($data);
        if($res !== false){
            $this->result('',1,'更新成功');
        }else{
            $this->result('',0,'更新失败');
        }
    }
}