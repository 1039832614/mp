<?php 
namespace app\base\controller;
use think\Controller;
use think\Db;
use geo\Geohash;
use Config;

/**
* 共用基类
*/
class Base extends Controller{

    /**
     * 获取轮播图列表
     */
    public function bannerList($gid)
    {
        $data = Db::table('am_banner')->field('id,title,pic')->where('gid',$gid)->order('id desc')->select();
        if($data){
            $this->result($data,1,'获取数据成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    /**
     * 获取轮播图详情
     */
    public function bannerDetail($id)
    {
        $data = Db::table('am_banner')->field('title,pic,content,create_time')->where('id',$id)->find();
        if($data){
            $this->result($data,1,'获取数据成功');
        }else{
            $this->result('',0,'数据异常');
        }
    }

    /**
     * 检测接口密钥
     */
    public function checkApiKey($uid,$api_key)
    {
        $u = $prefix.'_'.$uid;
        if(empty($u) || Cache::get($u) !== $api_key){
            $this->result('',0,'接口密钥不正确');
        }
    }

    /**
     * 更新用户经纬度
     */
    public function upUserCoord($data,$table)
    {
    	$geo = new Geohash();
    	$data['hash_val'] = $geo->encode_hash($data['lat'],$data['lng']);
    	$res = Db::table($table)->where('uid',$data['uid'])->update($data);
    	if($res !== false){
    		$this->result('',1,'更新成功');
    	}else{
    		$this->result('',0,'更新失败');
    	}
    }

}