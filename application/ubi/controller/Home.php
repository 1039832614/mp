<?php 
namespace app\ubi\controller;
use app\base\controller\Ubi;
use think\Db;

class Home extends Ubi
{

    function initialize(){
        parent::initialize();
        $this->uid = input('post.uid');
    }

    /******************************获取汽车的相关数据********************************/

    /**
     * 获取汽车品牌
     */
    public function getBrand()
    {
        $data = Db::table('co_car_menu')->field('id as brand_id,name,abbr')->select();
        if($data){
            $this->result( $data,1,'获取成功');
        }else{
            $this->result('',0,'获取数据异常');
        }
    }

    /**
     * 获取汽车类型
     */
    public function getAudi()
    {
        $bid = input('get.brand_id');
        $res = Db::table('co_car_cate')->field('type')->where('brand',$bid)->select();
        $data = array_unique(array_column($res,'type'));
        if($data){
            $this->result($data,1,'获取成功');
        }else{
            $this->result('',0,'获取数据异常');
        }
    }

    /**
     * 获取汽车排量
     */
    public function getDpm()
    {
        $bid = input('get.brand_id');
        $type = input('get.type');
        $res = Db::table('co_car_cate')->where('brand',$bid)->where('type',$type)->field('id,series')->select();
        $data = array_unique(array_column($res,'series'));
        if($res){
            $this->result($res,1,'获取成功');
        }else{
            $this->result('',0,'获取数据异常');
        }
    }

    /***************************************我的页面************************************/

    /**
     *  我的  页面  信息
     */
    public function info()
    {
        // 头像  姓名  车牌号
        $info = Db::table('cb_user')
            ->where('u_id',$this->uid)
            ->field('head_pic,name,plate')
            ->find();
        if (strlen($info['plate']) == 0){
            $info['plate'] = '';
        }
        //  查看该用户的 未读 的消息 的条数
        $info['unread'] = $this->item($this->uid);
        // 邦保养剩余次数
        if (!empty($info['plate'])){
            $info['count'] = Db::table('u_card')
                ->where('plate',$info['plate'])
                ->sum('remain_times');
        }else{
            $info['count'] = 0;
        }
        // 订单数量   未支付  1    已确认(未支付)  2
        $info['order'] = Db::table('cb_privil_ser')
            ->where('pay_status','in','0,2')
            ->where('uid',$this->uid)
            ->count();
        if ($info){
            $this->result($info,1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    /**
     * 未读消息数
     */
    public function item($uid)
    {
        // 获取系统消息最后一条
        $s_mid = $this->getLastMid('13');
        // 获取消息库里最后一条
        $u_mid = $this->getMaxMid($uid);
        // 如果消息库里的id大于等于系统消息的id
        if($u_mid >= $s_mid){
            $counts = Db::table('cb_msg')
                ->where('uid',$uid)
                ->where('status',0)
                ->count();
            return $counts;
        }else{
            // 获取所差的数据条数
            if ($u_mid == 0){
                $counts = Db::table('am_msg')
                    ->where('sendto','like','%13%')
                    ->count();
                return $counts;
            }elseif ($u_mid != 0){
                $counts = $s_mid - $u_mid;
                return $counts;
            }
        }
    }


    /**
     * 个人信息  头像
     */
    public function personInfo()
    {
        $info['head_pic'] = Db::table('cb_user')->where('u_id',$this->uid)->value('head_pic');
        if ($info){
            $this->result($info,1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }
    

    /**
     * 个人信息 车辆信息
     */
    public function carInfo()
    {
        // 查找用户的unionId
        $unionid = Db::table('cb_user')->where('u_id',$this->uid)->value('unionId');
        if (strlen($unionid) != 0){
            $uid = Db::table('u_user')->where('unionId',$unionid)->value('id');
            //车牌号  品牌  车型  排量
            $carInfo = Db::table('u_card')
                ->alias('uc')
                ->join('co_car_cate cc','uc.car_cate_id = cc.id','LEFT')
                ->join('co_car_menu cm','cc.brand = cm.id','LEFT')
                ->where('uc.uid',$uid)
                ->field('uc.id,uc.car_cate_id,uc.province,uc.city,uc.plate,cc.type,cc.series,cm.name')
                ->group('uc.plate')
                ->select();
            if ($carInfo){
                $this->result($carInfo,1,'数据返回成功');
            }else{
                $this->result('',0,'暂无数据');
            }
        }else{
            $this->result('',0,'暂无数据');
        }
    }


    /**
     *点击 已有车辆  自动完善信息
     */
    public function wellInfo()
    {
        // 前台传回的信息
        $data = input('post.');
        // 根据用户ID查 u_user 表 相同的 用户的信息
        $unionid = Db::table('cb_user')->where('u_id',$this->uid)->value('unionId');
        if (strlen($unionid) != 0){
            $info = Db::table('u_user')
                ->where('unionId',$unionid)
                ->field('name,phone')
                ->find();
        }else{
            $info['name'] = '';
            $info['phone'] = '';
        }
        // 构建数组 更新 cb_user  该用户的信息
        $arr = [
            'name' => $info['name'],
            'phone' => $info['phone'],
            'status' => 1,  // 已完善信息
            'car_cate_id' => $data['car_cate_id'],
            'plate' => $data['plate'],
            'province' => $data['province'],
            'city' => $data['city']
        ];
        $ret = Db::table('cb_user')
            ->where('u_id',$data['uid'])
            ->update($arr);
        if ($ret){
            $this->result('',1,'完善成功');
        }else{
            $this->result('',0,'该车牌号已绑定个人信息');
        }
    }


    /**
     * 修改 信息
     */
    public function updateInfo()
    {
        // 前台返回的数据
        $data = input('post.');
       // 车牌字母大写
        $plate = input('post.plate','','strtoupper');
        // 判断此车牌是否和邦保养的车牌一样 一样再判断是否有此车型
        $car_cate_id = Db::table('u_card')->where(['plate'=>$data['plate']])->field('car_cate_id')->find();
        if(empty($car_cate_id)){
            if($car_cate_id !== $data['car_cate_id']) $this->result('',0,'该车牌已绑定其他车型');
        }
        $arr = [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'plate' => $plate,
            'province' => $data['province'],
            'city' => $data['city'],
            'car_cate_id' => $data['car_cate_id'],
            'status' => 1,
        ];
        $ret = Db::table('cb_user')
            ->where('u_id',$this->uid)
            ->update($arr);
        if ($ret){
            $this->result('',1,'完善成功');
        }else{
            $this->result('',0,'完善失败');
        }
    }


    /**
     * 返回个人信息
     */
    public function userInfo()
    {
        $data = Db::table('cb_user bu')
            ->join('co_car_cate cc','bu.car_cate_id = cc.id')
            ->join('co_car_menu bd','cc.brand = bd.id')
            ->where('bu.u_id',$this->uid)
            ->field('bu.name,bu.phone,bu.province,bu.city,bu.plate,bd.name as car_name,cc.type,cc.series')
            ->select();
        if (!empty($data)){
            $this->result($data,1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }

    }


   /*************************************系统消息***************************************************/

    // 消息列表
    public function msgList($uid,$page,$status)
    {
       if ($status == 0){
           // 系统消息 消息推送
           $this->getUrMsg($uid);
       }
        $pageSize = 8;
        //总条数
        $counts = Db::table('cb_msg')       //  VIP消息表
            ->alias('um')
            ->join(['am_msg'=>'m'],'um.mid = m.id')          //  总后台消息表
            ->where('uid',$uid)
            ->where('sendto','like','%'.'13'.'%')
            ->where('um.status',$status)
            ->count();
        $rows = ceil($counts/$pageSize);
        // 消息ID    消息状态    标题   时间    内容
        $list= Db::table('cb_msg')
            ->alias('um')
            ->join(['am_msg'=>'m'],'um.mid = m.id')
            ->field('mid,status,title,create_time as time,content')
            ->where('uid',$uid)
            ->where('sendto','like','%'.'13'.'%')
            ->where('um.status',$status)
            ->order('um.mid DESC')
            ->page($page,$pageSize)
            ->select();
        foreach ($list as $key=>$value){
            $list[$key]['content'] = strip_tags($list[$key]['content']);
        }
        if($list){
            $this->result(['list'=>$list,'rows'=>$rows],1,'获取消息列表成功');
        } else {
            $this->result('',0,'暂无数据');
        }
    }

    // 消息  未读列表
    public function unread()
    {
        $uid = input('post.uid');  // 用户ID
        $page = input('post.page') ? : 1;
        $status = 0;
        $this->msgList($uid,$page,$status);
    }

    // 消息  已读列表
    public function read()
    {
        $uid = input('post.uid');  // 用户ID
        $page = input('post.page') ? : 1;
        $status = 1;
        $this->msgList($uid,$page,$status);
    }

    /**
     * 获取系统消息详情
     */
    public function msgDetail()
    {
        $mid = input('post.mid');           // 消息ID
        $uid = input('post.uid');           // 用户ID
        $static = Db::table('cb_msg')
            ->where('mid',$mid)
            ->where('uid',$uid)
            ->setField('status',1);       // 将 状态值 改为 1   已读
        $detail =Db::table('am_msg')         // 总后台消息表 获取 详情
            ->where('id',$mid)
            ->field('title,content,create_time as time')
            ->find();
        $detail['content'] = strip_tags($detail['content']);
        if($detail){
            $this->result($detail,1,'获取消息详情成功');
        } else {
            $this->result('',0,'获取消息详情失败');
        }
    }


    public function getLastMid($rid)
    {
        return Db::table('am_msg')->where('sendto','like','%'.$rid.'%')->max('id');
    }

    /**
     * 获取当前用户信息库的最后一条信息的id
     */
    public function getMaxMid($uid)
    {
        $u_mid = Db::table('cb_msg')->where('uid',$uid)->max('mid');
        return $u_mid ? $u_mid : 0;
    }

    /**
     * 获取未读取的信息数据
     */
    public function getUrMsg($uid)
    {
        // 获取系统消息最后一条
        $s_mid = $this->getLastMid('13');
        // 获取消息库里最后一条
        $u_mid = $this->getMaxMid($uid);
        // 如果消息库里的id大于等于系统消息的id
        if($u_mid >= $s_mid){
            // 不做操作
            return false;
        }else{
            // 获取所差的数据条数
            $mids = Db::table('am_msg')
                ->where('id','>',$u_mid)
                ->where('sendto','like','%'.'13'.'%')
                ->column('id');
            // 将所差数据插入数据库
            foreach ($mids as $k => $v) {
                $data[$k] = ['uid'=>$uid,'mid'=>$v];
            }
            if (!empty($data)){
                $res = Db::table('cb_msg')->insertAll($data);
                return $res;
            }
        }
    }
}