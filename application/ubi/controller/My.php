<?php 
namespace app\ubi\controller;
use app\base\controller\Ubi;
use think\Db;

class My extends Ubi
{

    function initialize()
    {
        parent::initialize();
        $this->uid = input('post.uid');
    }

    /***************************************我的订单***************************************************/
    /**
     * 我的订单  订单列表
     */
    public function orderList($page,$uid,$status)
    {
        $pageSize = 8;                         // 每页显示条数
        $count = Db::table('cb_privil_ser')
            ->alias('a')
            ->join('cs_shop b','a.sid = b.id','LEFT')             // 维修厂表
            ->where('a.uid',$uid)
            ->where('pay_status','in',$status)
            ->count();
        $rows = ceil($count / $pageSize);
        //  订单编号   订单时间   订单金额   产品名称  规格 数量   提供单位(维修厂名)  产品图片
        $list = Db::table('cb_privil_ser')
            ->alias('a')
            ->join('cs_shop b','a.sid = b.id','LEFT')
            ->where('a.uid',$uid)
            ->where('pay_status','in',$status)
            ->field('a.id,a.odd_num,a.pro_pic,a.create_time,a.pro_ame,a.spec,a.number,a.price,a.pay_status,b.company')
            ->order('create_time DESC')
            ->page($page,$pageSize)
            ->select();
        if ($count > 0){
            $this->result(['list'=>$list,'rows'=>$rows],1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }


    //  我的订单  待支付
    public function unpaid()
    {
        $uid = input('post.uid');   //  用户ID
        $page = input('post.page')? : 1;  //  页码
        $status = '0,2';
        return $this->orderList($page,$uid,$status);
    }

    // 我的订单   已支付
    public function paid()
    {
        $uid = input('post.uid');   //  用户ID
        $page = input('post.page')? : 1;  //  页码
        $status = 1;
        return $this->orderList($page,$uid,$status);
    }

    /***************************************我的预约***************************************************/

    /**
     *我的预约  预约列表
     */
    public function reservationList($page,$uid,$status)
    {
        $pageSize = 8;                         // 每页显示条数
        $count = Db::table('cb_privil_ser')                 //  订单表
            ->alias('a')
            ->join('cs_shop cs','a.sid = cs.id','LEFT')
            ->where('a.uid',$uid)
            ->where('pay_status',$status)
            ->where('type',1)
            ->count();
        $rows = ceil($count / $pageSize);
        //  维修厂名称    产品名称     预约时间    产品规格  产品图片
        $list = Db::table('cb_privil_ser')                 //  订单表
            ->alias('a')
            ->join('cs_shop cs','a.sid = cs.id','LEFT')
            ->where('a.uid',$uid)
            ->where('pay_status',$status)
            ->where('type',1)
            ->field('a.id,a.pro_ame,a.pro_pic,cs.company,a.spec,a.make,a.number,a.pay_status')
            ->order('a.create_time DESC')
            ->page($page,$pageSize)
            ->select();
        if ($count > 0){
            $this->result(['list'=>$list,'rows'=>$rows],1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }

    }

    //我的预约   已预约
    public function reserved()
    {
        $uid = input('post.uid');   // 用户ID
        $page = input('post.page')? : 1;  //  页码
        $status = 0;                       //  已预约
        return $this->reservationList($page,$uid,$status);
    }

    // 我的预约   已确认
    public function confirmed()
    {
        $uid = input('post.uid');   // 用户ID
        $page = input('post.page')? : 1;  //  页码
        $status = 2;                       //  已确认
        return $this->reservationList($page,$uid,$status);
    }

    // 我的预约  已超时
    public function timeout()
    {
        $uid = input('post.uid');   // 用户ID
        $page = input('post.page')? : 1;  //  页码
        $status = 3;                       //  已超时
        return $this->reservationList($page,$uid,$status);
    }

    /**
     *  超时订单超过三天后  转为 异常订单
     */
    public function exception()
    {
        // 用户ID
        $uid = input('post.uid');
        // 获取 预约订单 已经超时的列表
        $order = Db::table('cb_privil_ser')
            ->where('uid',$uid)
            ->where('pay_status',3)
            ->where('type',1)
            ->select();
        if (!empty($order)){
            foreach ($order as $key=>$value){
                if (time() > (strtotime($order[$key]['make']) + 86400 * 3)){
                    Db::table('cb_privil_ser')->where('id',$order[$key]['id'])->setField('pay_status',4);
                }else{
                    $this->result('',1,'您有超时订单,请及时处理');
                }
            }
        }else{
            $this->result('',0,'暂无数据');
        }
    }


    /****************************************我的服务**************************************************/

    /**
     * 我的服务
     */
    public function myService()
    {
        // 产品名称  产品图片
        $uid = input('post.uid');
        $pageSize = 8;                         // 每页显示条数
        $page = input('post.page')? : 1;  //  页码
        $count = Db::table('cb_privil_ser')
            ->alias('a')
            ->join('am_serve as','a.fid = as.id','LEFT')
            ->where('a.uid',$uid)
            ->where('a.pay_status',1)
            ->where('a.type',0)
            ->group('a.fid')
            ->count();
        $rows = ceil($count / $pageSize);
        $list = Db::table('cb_privil_ser')
            ->alias('a')
            ->join('am_serve s','a.fid = s.id','LEFT')
            ->where('a.uid',$uid)
            ->where('a.pay_status',1)
            ->where('a.type',0)
            ->field('a.id,a.fid,a.pro_ame,a.cycle,a.pro_pic,UNIX_TIMESTAMP(s.time) as times')
            ->group('a.fid')
            ->page($page,$pageSize)
            ->select();
        foreach ($list as $key=>$value){
            // 倒计时时间 转成数组返回给前端
                $number = strlen($list[$key]['cycle']);
                $list[$key]['day'] = $this->number($number,$list[$key]['cycle']);
//            unset($list[$key]['fid']);
            unset($list[$key]['times']);
            }
        if ($count > 0){
            $this->result(['list'=>$list,'rows'=>$rows],1,'数据返回成功');
        }else{
            $this->result('',0,'暂无购买的服务');
        }
    }

    /**
     * 我的服务  详情
     */
    public function myServiceDetail()
    {
        //  产品图片  名称  倒计时  距离  维修厂地址   维修厂名称  产品描述  规格参数  数量
        // 服务ID
        $fid = input('post.fid');
        // 用户ID
        $uid = input('post.uid');
        // 查询用户经纬度
        $uInfo = Db::table('cb_user')->where('u_id',$uid)->field('lat,lng')->find();
        $detail = Db::table('cb_privil_ser')
            ->alias('a')
            ->join('cs_shop cs','a.sid = cs.id','LEFT')
            ->join('cs_shop_set css','a.sid = css.sid','LEFT')
            ->join('am_serve as','a.fid = as.id','LEFT')
            ->where('a.fid',$fid)
            ->field('a.fid,a.pro_ame,a.pro_pic,a.cycle,a.desc,a.spec,a.number,cs.company,css.lat,css.lng,css.province,css.city,css.county,css.address,UNIX_TIMESTAMP(a.pay_time) as times')
            ->order('a.id DESC')
            ->limit(1)
            ->find();
        // 将地址拼接
        $detail['site'] = $detail['province'].$detail['city'].$detail['county'].$detail['address'];
        // 计算 用户与维修厂之间距离
        $detail['distance'] = $this->getDistance($uInfo['lat'],$uInfo['lng'],$detail['lat'],$detail['lng']);
        //  倒计时  转换成数组返回给前端
        $number = strlen($detail['cycle']);
        $detail['day'] = $this->number($number,$detail['cycle']);
            // 判断该服务项是否有预约的订单
            $order = Db::table('cb_privil_ser')
                ->where('uid',$uid)
                ->where('fid',$detail['fid'])
                ->where('type',1)
                ->order('id DESC')
                ->find();
        if (!empty($order)){
            if ($order['pay_status'] == 0 || $order['pay_status'] == 2){
                $detail['makeTime'] = $order['make'];
            }elseif ($order['pay_status'] == 1){
                $detail['makeTime'] = 1;
            }else{
                $detail['makeTime'] = 0;
            }
        }
        // 去除多余的返回值
        unset($uInfo['lat']);
        unset($uInfo['lng']);
        unset($detail['lat']);
        unset($detail['lng']);
        unset($detail['province']);
        unset($detail['city']);
        unset($detail['county']);
        unset($detail['address']);
        unset($detail['times']);
        if ($detail){
            $this->result($detail,1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }


    /****************************************保单信息**************************************************/

    /**
     * 保单信息
     */
    public function policyInfo()
    {
        $uid = input('post.uid');
        // 所属保险公司   保单号   险种  保单金额  保单有效期 保险照片
        $pinfo['data'] = Db::table('cb_policy_sheet')
            ->where('u_id',$uid)
            ->field('pid,company,policy_num,name_price,start_time,end_time,img,status,total')
            ->json(['name_price'])
            ->order('pid DESC')
            ->limit(1)
            ->find();
        // 有效期
        if (!empty($pinfo['data']['start_time'])){
            $times = strtotime($pinfo['data']['end_time']) - strtotime($pinfo['data']['start_time']);
            $pinfo['data']['indate'] = round($times/86400).'天';   // 四舍五入取整
        }
        if (!empty($pinfo['data']['img'])){
            $pinfo['data']['img'] = json_decode($pinfo['data']['img']);
        }
//        var_dump($pinfo);exit;
        if (!empty($pinfo['data'])){
            $this->result($pinfo,1,'数据返回成功');
        }else{
            $this->result('',0,'您暂无保单');
        }
    }

    /**
     * 查看 驳回理由
     */
    public function checkReason()
    {
        // 保单 ID
        $id = input('post.id');
        // 审核人   驳回理由  审核时间
        $reason = Db::table('cb_policy_sheet')
            ->where('pid',$id)
            ->field('audit_person,reason,audit_time')
            ->find();
        if ($reason){
            $this->result($reason,1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

}