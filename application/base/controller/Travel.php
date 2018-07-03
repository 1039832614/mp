<?php 
namespace app\base\controller;
use app\base\controller\Base;
use think\Db;
use Geo\Geo;
use Config;
/**
 * 约驾小程序
 */
class Travel extends Base
{
	//初始化
	function initialize()
	{

	}
	 /**
     * 获取用户的位置信息
     * @param  $uid 用户id
     * @return $user_location 经纬度
     */
	public function getUserLocation($uid)
	{
    	$user_location = Db::table('yue_user')
    	                 ->where('id',$uid)
    	                 ->field('lat,lng')
    	                 ->find();
    	return $user_location;
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

    /**
     * 获取城市的经纬度
     * @param  地点名
     * @return $address
     */
    public function map($address)
    {
        $ak = 'p4n0i3mPmFxWtUowbdtEBhBcbXvs5Pw0';
        $url = 'http://api.map.baidu.com/geocoder/v2/?address='.$address.'&output=json&ak='.$ak;
        $weixin = file_get_contents($url);
        $jsondecode = json_decode($weixin);//对json格式的字符串进行编码
        $arr = get_object_vars($jsondecode);//转换成数组
        $arr2 = get_object_vars($arr['result']);
        $arr3 = get_object_vars($arr2['location']);
        return $arr3;  
    }
    /**
     * 上传约驾 活动照片
     * @param   $uid  约驾活动发起者的id
     * @param   $aid  约驾活动id
     * @param   $files 上传的照片 
     */
    public function up($aid,$uid,$files)
    { 
        foreach($files as $file){
            // 移动到框架应用根目录/uploads/ 目录下
            $info = $file->validate(['ext'=>'jpg,png,gif'])->move( '../uploads');
            if($info){
                //照片上传成功
                $pic = 'http://localhost/mp/uploads/travel'.$info->getSaveName();
                $data = [
                    'aid' => $aid,
                    'creator_id' => $uid,
                    'pic' => $pic
                ];
                $res = Db::table('yue_activity_picture')
                       ->insert($data);
                $msg = ['status' => 1,'msg' => '上传照片成功'];
            } else {
                // 上传失败获取错误信息
                $message = $file->getError();
                $msg = ['status' => 0,'msg' => $message];
            }    
        }
        return $msg;
    }

    /**
     * 根据用户id获取用户在yue_user中的信息
     * @param  $uid 约驾小程序中的用户id
     * @return $yue_user 约驾小程序用户信息
     */
    public function selYueUser($uid){
        $yue_user = Db::table('yue_user')
                     ->where('id',$uid)
                     ->find();
        return $yue_user;
    }
    /**
     * 获取系统发送的关于发送对象最后一条信息的id
     */
    public function getLastMid()
    {
        return Db::table('yue_message')->max('id');
    }

    /**
     * 获取当前用户信息库的最后一条信息的id
     */
    public function getMaxMid($uid)
    {
        $u_mid = Db::table('yue_message_user')->where('uid',$uid)->max('mid');
        return $u_mid ? $u_mid : 0;
    }

    /**
     * 获取当前信息列表
     */
    public function msgList($uid)
    {   
        return  Db::table('yue_message_user')
                ->alias('um')
                ->join(['yue_message'=>'m'],'um.mid = m.id')
                ->field('mid,status,title,create_time')
                ->where('uid',$uid)
                ->order('um.mid desc')
                ->paginate(10);
    }


    /**
     * 获取当前信息已读未读列表
     */
    public function msgLists($uid,$status)
    {   
        $where=[['uid','=',$uid],['status','=',$status]];
        return  Db::table('yue_message_user')
                ->alias('um')
                ->join(['yue_message'=>'am'],'um.mid = am.id')
                ->field('mid,status,title,content,create_time')
                ->where($where)
                ->order('um.mid desc')
                ->paginate(10);
    }


    /**
     * 获取未读取的信息数据
     */
    public function getUrMsg($uid)
    {
        // 获取系统消息最后一条
        $s_mid = $this->getLastMid();
        // 获取消息库里最后一条
        $u_mid = $this->getMaxMid($uid);
        // 如果消息库里的id大于等于系统消息的id
        if($u_mid >= $s_mid){
            // 不做操作
            return false;
        }else{
            // 获取所差的数据条数
            $mids = Db::table('yue_message')->where('id','>',$u_mid)->column('id');
            // 将所差数据插入数据库
            foreach ($mids as $k => $v) {
                $data[$k] = ['uid'=>$uid,'mid'=>$v];
            }
            $res = Db::table('yue_message_user')->insertAll($data);
            return $res;
        }
    }

    /**
     * 获取消息详情
     */
    public function msgDetail($mid,$uid)
    {
        // 更新消息状态
        Db::table('yue_message_user')->where(['mid'=>$mid,'uid'=>$uid])->setField('status',1);
        // 返回消息详情
        return Db::table('yue_message')
            ->where('id','=',$mid)
            ->find();
    }
}