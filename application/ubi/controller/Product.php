<?php 
namespace app\ubi\controller;
use app\base\controller\Ubi;
use think\Db;

class Product extends Ubi
{

    function initialize()
    {
        parent::initialize();
        $this->uid = input('post.uid');
    }

    /******************************************产品列表*******************************************/
	/**
	 * 产品列表
	 */
    public function product_list()
    {
        //  产品名称   产品图片   产品描述    更换周期
        $list = Db::table('am_serve')
            ->field('id,name,image,content,period,time')
            ->where('pid','<>',0)
            ->order('period ASC')
            ->select();
        // 查找订单表中该用户已购买的服务项的ID
        $order_uid = Db::table('cb_privil_ser')
            ->where('pay_status',1)
            ->where('type',0)
            ->where('uid',$this->uid)
            ->field('fid')
            ->select();
        if (!empty($order_uid)){
            // 生成一维数组
            foreach ($order_uid as $key=>$value){
                $arr[] = $order_uid[$key]['fid'];
            }
        }else{
            $arr = array();
        }
        foreach ($list as $key=>$value){
            // 产品名称 加上 仲达天下
            $list[$key]['name'] = '(仲达天下)'.$list[$key]['name'];
            // 将富文本内容解析
            $list[$key]['content'] = mb_substr(strip_tags(str_replace("&nbsp;","",htmlspecialchars_decode($list[$key]['content']))),20,30,"utf-8");
            //将周期天数分割按数组给前端
            $list[$key]['times'] = $this->purchased($list[$key]['id']);
            // 产品是否购买标识  当前用户
            if (!in_array($list[$key]['id'],$arr)){
                unset($list[$key]['id']);
                unset($list[$key]['content']);
                unset($list[$key]['name']);
                unset($list[$key]['image']);
                unset($list[$key]['period']);
                unset($list[$key]['time']);
                unset($list[$key]['times']);
            }else{
                $list[$key]['logo'] = 1;
            }
            // 去除不必要字段
            unset($list[$key]['time']);
            unset($list[$key]['photo']);
            unset($list[$key]['day']);
            unset($list[$key]['period']);
        }
        if ($list){
            $list = array_values(array_filter($list));
            $this->result($list,1,'获取列表成功');
        }else{
            $this->result('',0,'暂无购买的产品');
        }
    }


    /**
     *  首页产品 详情
     */
    public function product_detail()
    {
        // 产品ID
        $id = input('post.id');
        // 用户ID
        $uid = input('post.uid');
        // 是否 购买 标识
        $logo = input('post.logo');
        // 查询用户经纬度
        $uInfo = Db::table('cb_user')->where('u_id',$uid)->field('lat,lng')->find();
        $info = Db::table('am_serve')
            ->where('id',$id)
            ->field('name,image,period,content,size,UNIX_TIMESTAMP(time) as times,number')
            ->find();
        $info['name'] = '(仲达天下)'.$info['name'];
        $a = str_replace('img src=&quot;/data/imgs/','img src="https://doc.ctbls.com/data/imgs/',$info['content']);
        $info['content'] = str_replace('.png&quot','.png"',$a);
        // 判断是否 购买 邦保养卡
        // 查找用户的unionId
        $unionid = Db::table('cb_user')->where('u_id',$this->uid)->value('unionId');
        if (strlen($unionid) != 0){
            $uid = Db::table('u_user')->where('unionId',$unionid)->value('id');
            $ubi = Db::table('u_card')
                ->where('uid',$uid)
                ->where('pay_status',1)
                ->find();
        }else{
            $ubi = array();
        }
        // 已经购买
        if (!empty($ubi)){
            // logo  1  已购买该产品
            if ($logo == 1){
                // 判断该产品有没有预约的订单
                $finish = Db::table('cb_privil_ser')
//                    ->where('pay_status',1)
                    ->where('uid',$this->uid)
                    ->where('fid',$id)
                    ->where('type',1)
                    ->order('id DESC')
                    ->find();
                    // 如果 有预约订单
                if (!empty($finish)){
                   // 若有 已预约 但未支付的 和 已确认但未支付 时 显示 预约时间   维修厂名称  维修厂地址  维修厂与用户距离
                    if ($finish['pay_status'] == 0 || $finish['pay_status'] == 2){
                        $info['numbers'] = $finish['number'];
                        // 查询维修厂的名称  地址  经纬度
                        $shop = Db::table('cs_shop')
                            ->alias('cs')
                            ->join('cs_shop_set css','cs.id = css.sid','LEFT')
                            ->field('cs.company,css.province,css.city,css.county,css.address,css.lat,css.lng')
                            ->where('cs.id',$finish['sid'])
                            ->find();
                        // 计算 与 用户之间的距离
                        $info['distance'] = $this->getDistance($uInfo['lat'],$uInfo['lng'],$shop['lat'],$shop['lng']);
                        // 维修厂名称
                        $info['shopname'] = $shop['company'];
                        // 维修厂详细地址
                        $info['site'] = $shop['province'].$shop['city'].$shop['county'].$shop['address'];
                        // 显示 预约时间
                        $info['reservation'] = $finish['make'];
                        $info['time'] = $this->purchased($id);
                        // 已支付  进行倒计时
                    }elseif ($finish['pay_status'] == 1){
                        $info['numbers'] = $finish['number'];
                        $info['reservation'] = 1;
                        // 有完成的预约订单 返回给前端 倒计时时间戳
                        $info['time'] = (strtotime($finish['pay_time']) + $info['period'] * 86400) - time();
                        // 如果 倒计时 小于等于0  则返回更新倒计时从新开始  并 可重新预约
                        if ($info['time'] <= 0){
                            $info['reservation'] = 0;
                            $info['time'] = $this->purchased($id);
                        }
                    }else{
                        $info['reservation'] = 0;
                        // 已购买 预约订单未确认  将更换周期时间转换为分割成数组
                        $info['time'] = $this->purchased($id);
                    }
                }else{
                    $info['numbers'] = $info['number'];
                    $info['reservation'] = 0;
                    // 不进行倒计时  只 将更换周期时间转换为分割成数组
                    $info['time'] = $this->purchased($id);
                }
            }else{
                $info['reservation'] = 0;
                // 不进行倒计时  只 将更换周期时间转换为分割成数组
                $info['time'] = $this->purchased($id);
            }
            $info['ubi'] = 1;
            unset($info['period']);
        }else{
            $info['reservation'] = 0;
            // 不进行倒计时  只 将更换周期时间转换为分割成数组
            $info['time'] = $this->purchased($id);
            $info['ubi'] = 0;
            unset($info['period']);
        }
        unset($info['times']);
        if ($info){
            $this->result($info,1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    /******************************************倒计时相关接口******************************************/

    /**
     * 产品  未购买 或 购买后没有预约 的倒计时 接口
     */
    public function purchased($fid)
    {
        // 查询该产品的更换周期  但不倒计时  将时间转换为数组返回给前端
        $period = Db::table('am_serve')
            ->where('id',$fid)
            ->value('period');
        $number = strlen($period);
        $times = $this->number($number,$period);

        return $times;
    }

    /****************************************预约相关操作************************************************/

    /**
     * 预约前的数据
     */
    public function record()
    {
        $id = input('post.id');
        $data = Db::table('am_serve')
            ->where('id',$id)
            ->field('name,size')
            ->find();
        $data['number'] = Db::table('cb_privil_ser')
                    ->where('fid',$id)
                    ->where('type',0)
                    ->value('number');
        if ($data){
            $this->result($data,1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }


    /**
     * 预约 操作
     */
    public function reservation()
    {
       // 前台提交的数据
        $data = input('post.');
        // 判断 预约时间是否是三天后
        // 获取三天后的时间戳
        $times = strtotime(date('Y-m-d',time())) + 86400 * 3;
        if ($times > strtotime($data['makeTime'])){
            $this->result('',0,'请预约三天后的日期时间');
        }
        // 查找用户的相关信息
        $info = Db::table('cb_user')
            ->where('u_id',$data['uid'])
            ->field('plate,eq_num')
            ->find();
        // 查找 该产品的相关信息
        $pinfo = Db::table('am_serve')
            ->where('id',$data['fid'])
            ->find();
        // 首次的数量  价格
        $first = Db::table('cb_privil_ser')
            ->where('fid',$data['fid'])
            ->where('type',0)
            ->field('number,price,brand_car_displa')
            ->find();
        // 构数组入库数据
        $arr = [
            'odd_num' => $this->getNonceStr(),   // 订单号
            'plate' => $info['plate'],           // 车牌号
            'eq_num' => $info['eq_num'],         // 设备号
            'uid' => $data['uid'],               // 用户ID
            'pro_ame' => $pinfo['name'],         // 产品名称
            'pro_pic' => $pinfo['image'],        // 产品图片
            'cycle' => $pinfo['period'],         // 更换周期
            'desc' => $pinfo['content'],         // 产品描述
            'spec' => $pinfo['size'],            // 产品规格
            'number' => $first['number'],        // 预约数量
            'sid' => $data['sid'],               // 维修厂ID
//            'fid' => $pinfo['id'],               // 该产品的ID
            'fid' => $data['fid'],               // 该产品的ID
            'price' => $first['price'] * ($pinfo['redate'] / 100),      // 预约价格
            'pay_status' => 0,                   // 支付状态
            'create_time' => date('Y-m-d H:i:s',time()),        // 创建时间
            'type' => 1,                        //  订单类型 1  预约订单
            'make' => $data['makeTime'],        // 预约时间
            'brand_car_displa' => $first['brand_car_displa'],    //  首次购买该产品时的 品牌/车型/排量
        ];
        $ret = Db::table('cb_privil_ser')
            ->insert($arr);
        if ($ret){
            $this->result('',1,'预约成功');
        }else{
            $this->result('',0,'预约失败');
        }
    }
}