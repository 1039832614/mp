<?php 
namespace app\ubi\controller;

use app\base\controller\Ubi;
use think\Db;
use MAP\Map;
use Pay\Wx;
use think\facade\Log;
use think\Response;


/*
 *  UBI 首页    
 *
 */

class Remind extends Ubi
{   
                                                                                
    //初始化操作
    public function __construct(Response $request)
    {   
        parent::__construct();

        $this->uid = input('post.uid') ?:die('缺少UID') ;     
        if (!input('post.uid')) {
         die;
        } 
        define('UBI','http://car.douying.me:8080/');
    }
    
/**************登录提醒模块**********/

    /**
     * 登录提醒
     * @param  
     * @return json
     * @todo  尾气预警第三方接口暂未编写 
     * @todo  爱车有道 暂设置为空
     */
    
    public function getPrompt()
    {   
        
        $uid = $this->uid;
        if(!$uid) {
            die;
        }
        $arr = [
          'isMaintenance' => $this->isMaintenance($uid), // 是否为邦保养用户
          'isPast'   => $this->isPast($uid),     // 保单是否过期
          'isRaise'  => $this->isRaise($uid),    // 养护提醒
          // 'isFault'  => $this->isFault($uid , 2),    // 故障提醒
          'isFault'  => $this->gggg($uid),    // 故障提醒

          'hintDiagn'  => $this->isDiagn($uid),     // 诊断师气泡
          'hintCar'    => $this->isCar(1),       // 爱车有道
          // 'hintFault'  => $this->isFault($uid , 1), // 故障预警气泡 
          'hintFault'  => $this->gggg($uid), // 故障预警气泡 
          'hintTail'   => $this->isTail($uid),       // 尾气气泡


          'aText' => '还没有设备？购买邦保养会员服务>',
          'text' => '享邦保养折扣、半价特权、保险退费服务', 
        ]; 
        

        if ($arr['isMaintenance'] == 0 ) {
            // $arr['isMaintenance'] = '您不是邦保养会员';
        } elseif($arr['isMaintenance'] == 1){
            $arr['isMaintenance'] = 0;
        }
        if ($arr['isPast'] == 0) {
            $arr['isPast'] = '您的保单已过期,请到退费页重新上传表单';
        } elseif($arr['isPast'] == 1) {
            $arr['isPast'] = 0;
        } elseif($arr['isPast'] == 2) {
            $arr['isPast'] = 0;
        }

        if ($arr['isRaise'] > 0) {
            $arr['isRaise'] = '养护提醒';
        }
        if ($arr['isFault'] > 0){
            $arr['isFault'] = '您的车辆存在故障！';
        }

        if ($arr['hintCar']) {
            $arr['hintCar'] = 1;
        }

        $this->result($arr,1,'获取成功');
    }
    

    /**
     * 是否为邦保养用户
     * @param  $[uid] int [< 用户uid >]
     * @return [int] 0 [暂不是邦保养用户]
     * @return [int] 1 [邦保养用户]
     * 
     */
   
    public function isMaintenance($uid)
    {

        $result = Db::table('cb_user a')
                  ->join('u_user b' , 'a.unionId = b.unionId')
                  ->rightjoin('u_card c' , 'b.id = c.uid')
                  ->where('c.pay_status' , 1)
                  ->where('a.u_id',$uid)
                  ->count();

        return !$result ? 0 : 1;
    }


    /**
     * 保单是否过期
     * @param  $[uid] int [< 用户uid >]
     * @return [int] 0 [保单已过期]
     * @return [int] 1 [保单未过期]
     * @return [int] 2 [未完善保单信息]
     * 
     */
   
    public function isPast($uid)
    {
      
          $result = Db::table('cb_policy_sheet')->where('u_id',$uid)->value('end_time');
          // print_r($result)
          if (empty($result)) {
          // 2 未完善保单信息
            return 2;  
          } elseif (time() >= strtotime($result)) {
          // 0 保单已过期
            Db::table('cb_policy_sheet')->where('u_id', $uid)->update(['status'=>2]);
            return 0;  
          } else {
          // 1 保单未过期
          return 1;  
        }

    }


    /**
     * 养护提醒
     * @param  $[uid] int [< 用户uid >] 
     *
     * 
     */
    
    public function isRaise($uid)
    {
        // echo 1;die;

        // 获取设备号
        $OBD = $this->GetEqnum($uid);
        // echo $OBD;die;
        if (!$OBD) {
            return 0;  
        }        
        // 获取故障信息
        $result = $this->getFault($OBD);
        // print_r($result);die;
        if ($result) {
            foreach ($result as $key => $value) {
                
                if ($value['Name'] == '保养' && $value['ExceptionCount'] > 0) {
                    
                    return '该车辆以达到1000公里，请注意邦保养养护';
                    return $value['Exception'];
                }
            }
        }
        // print_r()
        return 0;
    }
   
    /*
     *
     */
    
    public function gggg($uid)
    {
        // die;
        // 获取OBDID
        $OBDID = $this->GetEqnum($uid);
        // $OBDID = 164875483570;
        // 未绑定OBD设备 返回0
        if (!$OBDID) {
            return 0;
        }

        $data = $this->getFaultLog($OBDID, '2013-10-10', Date('Y-m-d'));
        // 
        $mid = DB::table('cb_unusual')
                ->where('eq_num', $OBDID)
                // ->where('status', 0)
                ->max('id');
        if (!$mid) {

            $time = '2020-10-21';
        } else {
            $time = DB::table('cb_unusual')->where('id', $mid)->value('warning_time'); 
        }
       // echo $time;die;
        if (isset($data['Rows'])) {
            $arr = [];
            foreach ($data['Rows'] as $key => $value) {
                // if ($value[''])
                if ($time > $value['_fcdate']){
                    $arr[$key]['eq_num'] = $value['_obdid'];
                    $arr[$key]['warning_time'] = $value['_fcdate'];
                    $arr[$key]['content'] = $value['_title'];
                    $arr[$key]['u_id'] = $uid;    
                }
            }
            DB::table('cb_unusual')->insertAll($arr);
           
            return DB::table('cb_unusual')
                   ->where('eq_num', $OBDID)
                   ->where('status', 0)
                   ->count();
            // return $sum;
        }
        return 0;
                                         
    }


    /**
     * 故障提醒
     * @param  $[uid] int [< 用户uid >]
     * @param  $[type] 1 [<返回故障条数>]
     * @param  $[type] 2 [<返回自定义故障信息>]
     * @return [int] 0   [< 无故障 >]
     * @return [int] > 0 [< 故障提醒 >]
     * 
     * 
     */    
    
    public function isFault($uid , $type = 1)
    { 

        // 获取OBDID
        $OBDID = $this->GetEqnum($uid);

        // 未绑定OBD设备 返回0
        if (!$OBDID) {
            return 0;
        }
        
        // 获取故障信息
        $result = $this->getFault($OBDID);
        
        if (!$result) {
            return 0;
        }
        // 筛掉养护后计算故障条数
        unset($result[13]);
        $count = array_sum(array_column($result,'ExceptionCount'));
        
        // 返回信息条数 或 提示信息
        if ($count > 0 && $type == 1) {

            return $count;
        } 
        elseif ($count > 0 && $type == 2) {

            return '您的车辆存在故障！';
        }
        else {
            return 0;
        }
        
    }


    /**
     * 尾气预警
     * @return  [int] 0 [< 不提醒 >]
     * @return  [int] 1 [< 尾气预警 >]
     *
     * @todo 模拟数据请注意修改
     */
    
    public function isTail($uid) 
    {
        return 0;
    }


    /**
     * 爱车有道
     * @return  [int] 0 [< 不提醒 >]
     * @return  [int] 1 [< 提醒 >]
     *
     * @todo 2数据  1消息
     */
    
    public function isCar($type = 2) 
    {     
        $uid = $this->uid;
        return $this->isCarModel($uid);

        // return 0;
    }
    
    /**
     * 养护提醒
     *  type 消息提示 ， type 2 数据格式
     */
    
    public function isCarModel($uid, $arr=[], $type = 2)
    {   

        $OBD = $this->GetEqnum($uid);
        $result = $this->getFault($OBD);

        $km = Db::table('cb_user')->where('eq_num', $uid)->value('km');
        $km = $km?:0;
        $km = 1000 - $km;

        $time = Db::table('cb_refund')->where('u_id', $uid)->group('u_id')->having('sum(km)>'.$km)->value('create_time');
        
        if (!$time && $type == 2) {
            $this->result('', 0, '暂无数据');
        }

        if ($result) {
        // $arr = '';
        // $result = json_encode($result, true);
            foreach ($result as $key => $value) {
                if ($value['Name'] == '保养') {
                    $arr = $value;
                    break;
                }
            }
        }

        // print_r($arr);die;
         if ($arr && $arr['ExceptionCount'] == 1){
            $arr['time'] = $time; 
            return $arr;
         }
         return 0;
        // print_r($arr);die;
    }

    
    /**
     * 诊断师提醒
     * @return  [int] 0 [< 不提醒 >]
     * @return  [int] >0 [< 诊断师提醒数量 >]
     *
     */
    
    public function isDiagn($uid)
    {   

        // 获取OBDID
        $OBDID = $this->GetEqnum($uid);

        // 未绑定OBD设备 返回 0
        if (!$OBDID) {
            return 0;
        }

        // 获取故障信息
        $result = $this->getFault($OBDID);
        
        if (!$result) {
            return 0;
        }
        // 筛掉养护
        unset($result[13]);
        // 添加故障信息
        $this->AddUnusual($result,$uid);
        // 返回故障条数
        return array_sum(array_column($result,'ExceptionCount'));
    }

/**************设备状态模块**********/
    
    /**
     * 是否绑定UBI设备 绑定返回设备码
     * @return  $result  array  设备码 运行状态
     * @return  $result  0      暂未绑定
     *
     */
    
    public function isBinding()
    {
        $OBDID = $this->GetEqnum($this->uid);
// print_r($OBDID);die;
        if (!$OBDID) {
            $this->result('',0,'暂未绑定');
        } else {
            // 查看运行状态
            $this->isStatus($OBDID);
        }

        $this->result('',0,'暂未绑定');
    }


    /**
     * 绑定设备码
     * 
     */
    
    public function recomBinding()
    {
        $OBDID = input('post.obdid')?:die('缺少obdid');
        // print_r($OBDID);die;

        $this->MBinding($this->uid,$OBDID,'绑定');
    }


    /**
     * 修改设备码
     * 
     */
    
    public function upBinding()
    {
        $OBDID = input('post.obdid')?:die('缺少obdid');
        
        $this->MBinding($this->uid,$OBDID,'修改绑定');
    }
    

    /**
     * [未绑定] 绑定设备码
     * @return json
     */
    
    public function MBinding($uid,$OBDID,$msg)
    {
        
        if (!$OBDID) {
            $this->result('', 0, '设备码错误');
        }
        $km = input('post.km');
        $km = 10;
        if (!$km) {
            $this->Result('',0,'km缺少');
        }
        // $OBDID = input('post.obdid')?:die('缺少obdid');

        // 判断该UBI 是否存在并开启
        // $off  = $this->mIsOBD($OBDID);
        

        // 判断UBI 是否正确
        $this->issetObd($OBDID);


        // 判断 该UBI 是否已被绑定
        $data = DB::table('cb_user')->where('eq_num',$OBDID)->find();
        
        // if(!$off){
        //     $this->result('',0,'UBI未开启');
        // }

        if($data) {
            $this->result('',0,'该UBI已被绑定');
        }
        // 绑定UBI
        $result = Db::table('cb_user')->where('u_id',$uid)->update(['eq_num'=>$OBDID,'km'=>$km]);


        if(!$result) {
            $this->result('',0,$msg.'失败');
        }
        // 删除旧设备信息
        // DB::table('cb_unusual')->where('u_id', $uid)->delete();
        $this->result('',1,$msg.'成功');

    }
    

    /**
     * [已绑定] 运行状态
     * @return Json
     * 
     */
    
    public function isStatus($OBDID)
    {   

        // 查看运行状态
        $result = $this->mIsOBD($OBDID);              
        $this->result(['status'=>$result,'OBDID'=>$OBDID],1,'获取成功');
    }
    

    /**
     * 是否开关OBID设备
     * @param [string]  $OBDID  [< 设备码 >]
     * 
     */
    
    public function mIsOBD($OBDID)
    {   

        // 判断UBI 是否存在
        $find = DB::table('cb_eq_num')->where(['eq_num'=>$OBDID])->find();
        // print_r($OBDID);die;
        if (!$find) {
            $this->result('',0,'设备码不存在');
        }
       
        // $url = UBI.'api/BlackList?obdid='.$OBDID;
        // $url = UBI.'api/LastLoction';
        $json = json_encode([
                        'OBDIDs'=> [$OBDID]
                    ]);
    
        // 请求接口
        $result = geturl($json,UBI.'api/LastLoction');
        $result = json_decode($result,true);
        
        if (!isset($result[0]['inDate'])) {
            return 0;
        }
        // 大于20秒。为不运行状态
        if (strtotime($result[0]['inDate']) + 20 < time()) {

            return 0;
        };  
        return 1;
    }
    
    /**
     * 是否存在
     * 
     */
    
    public function issetObd($OBDID)
    {
        $find = DB::table('cb_eq_num')->where(['eq_num'=>$OBDID])->find();
        if (!$find) {
            $this->result('',0,'设备码已被绑定或不存在');
        }        
    }

/**************行驶里程**************/
    
    /**
     * 行驶里程
     * @return [json]
     * @todo 往日行驶里程 ， 暂定为 查询表内数据 ；
     * 
     */
    
    public function getMileage()
    {
        $uid = $this->uid;
        // 获取行驶总数 or 退费总额
        $getToting = $this->getToting($uid);
        // 获取今日行驶里程
        $getTodayToting = $this->getTodayToting($uid);
        // 获取往日行驶里程 or 退费金额
        $list = $this->getPassageToting($uid);
        // print_r($list);die;
        $result = [
           
            'mileage' => $getToting['mileage'],
            'price'   => $getToting['price'],
            'km'      => $getTodayToting,
            'list'    => $list,
        ];

        $this->result($result,1,'获取成功');
    }


    /**
     * 行驶总数 or 退费总额
     * @param  $uid     用户uid
     * @return mileage  行驶总数
     * @return price    退费总额
     *
     * 
     */
    
    public function getToting($uid , $TotalMileage = 0)
    {   
        $data = DB::table('cb_refund')->where('refund_status',1)->where('u_id',$uid)->field('sum(refund_price) price,sum(km) mileage')->find();

        if (!$data['price']) {
            $data['price'] = 0;
        }
        if (!$data['mileage']) {
            $data['mileage'] = 0;
        }
        return $data;
        // // 获取OBDID
        // $OBD = $this->GetEqnum($uid);
        // // 获取当前用户行驶总数
        // $get = $this->getTotalMileage($OBD , '2019-6-1' , Date('Y-m-d'));

        // if ($get) {
        //     $TotalMileage = $value['distance'];
        // } 

        // // 获取行驶总数
        // $data['mileage'] = $TotalMileage;
        // // 获取退费金额
        // $data['price'] = DB::table('cb_refund')->where('refund_status',1)->where('u_id',$uid)->sum('refund_price');
        
        // return $data;
    }


    /**
     * 今日行驶里程
     * @param  $[uid] int [< 用户id >] 
     *
     */
    
    public function getTodayToting($uid , $mileage = 0)
    {
        
        // 获取OBDID
        $OBD = $this->GetEqnum($uid);

        // 获取今日行驶总数
        $result = $this->getDayMileage($OBD , Date('Y-m-d'));
        
        if (!$result) {
            return 0;
        } else {
            if (isset($result['distance'])) {
               return $result['distance'];
            } else {
                return 0;
            }
            
        }

    }
    

    /**
     * 获取往日行驶里程
     * @param  $[uid] int [< 用户id >] 
     * @return  [array] or [null]
     * 
     */
    
    public function getPassageToting($uid)
    {
        
        // 获取表内 行驶里程(KM) 、退费状态 、退费金额 、日期时间
        $data = Db::table('cb_refund')->where('u_id' , $uid)->field('km , refund_status , refund_price , create_time')->select();
        // print_r($data);die;
        return changeTimes($data,'create_time','Y-m-d');
    }

/**************省油宝***************/
     
    /**
     * 省油宝
     * @return [json]
     *
     * @todo  筛选时间 默认开始时间为 用户注册时间 ！待商议
     * @todo  驾驶信息 行车时长不确定是那个字段 ， 暂定为 fireTime
     * @todo  驾驶行为 急加速+急减速 返回的为 加减速    急加油 未返回 
     */
    
    public function getTreasure()
    {
        $uid = $this->uid;
        // 获取默认时间
        $choice = $this->getChoice($uid);
        // 接收时间
        $getTime = $this->getTime( input('post.type') );
        
        /*
           设置筛选时间，接收到时间时 ， 按接收的时间计算
           未接受到时间时，按默认时间计算 
         */
        if ($getTime) {
            $end_time = $getTime['end_time'];
            $start_time = $getTime['start_time'];
        } else{
            $end_time = $choice['end_time'];
            $start_time = $choice['start_time'];
        }
        
        // 筛选时间
    // print_r($choice)

        // 获取驾驶信息
        $arr = [
            'user' => $this->getUset($uid),     // 获取用户信息
            'choice' => ['end_time'=>$end_time , 'start_time'=>$start_time], // 获取筛选时间
            'list' => $this->getDrive($uid , $start_time , $end_time),    // 获取驾驶信息
            'data' => $this->getBehavior($uid, $start_time, $end_time),   // 获取驾驶行为
        ];
    
        $this->result($arr,1,'获取成功');
    }


    /**
     * 用户信息
     * @param  $[uid] int [< 用户uid >] 
     * @param  $[name] [<description>]
     * 
     */
    
    public function getUset($uid)
    {
        $data = DB::table('cb_user')->where('u_id',$uid)->field('nick_name , name , plate , head_pic')->find();
        
        return $data;
    }


    /**
     * 时间列表
     * @param  $[uid] int [< 用户uid >] 
     */
    
    public function getChoice($uid)
    {
        $start_time = DB::table('cb_user')->where('u_id' , $uid)->value('create_time');
        
        return [
          'start_time' => Date('Y-m-d' , strtotime($start_time)),
          'end_time' => Date('Y-m-d'),
        ];
    }


    /**
     * 驾驶信息
     * @param  $[OBDID] int or array [<用户OBDid>]
     * @param  $[beginDate] string [<开始时间>]
     * @param  $[endDate] string [<结束时间>]
     *
     * @return json or null
     * @todo  行车时长错误 ， 不懂 返回的的接口为那个参数 
     */

    public function getDrive($uid , $beginDate = '' , $endDate = '')
    {   

        $OBDID = $this->GetEqnum($uid);
        if (!$OBDID) {
            return 0;
        }
        // 获取油耗 、 行驶里程
        $result = $this->getOil($OBDID , $beginDate , $endDate);
        // print_r($result);die;
        // 计算总油耗
        $sum_fuel = array_sum($result['oilCountData']);
        // 计算总里程 (公里)
        $sum_mileage = array_sum($result['mileageData']);
        // 计算平均油耗
        if ($sum_mileage == 0) {
            $svg_fuel = 0;
        } else {
            $svg_fuel  = round($sum_fuel/$sum_mileage, 2);  
        }
        
        
        // 计算参考油价
        $price = bcmul($sum_fuel, 8, 2);  

        // 获取行车时长
        $duration = $this->duration($OBDID);
       // print_r($duration);die;
        if ($duration) {
            if (isset($duration['fireTime'])) {
                $duration = $duration['fireTime'];
            } else {
                $duration = 0;
            }
            
        } else {
            $duration = 0;
        }

        // 获取异常记录
        $unusual = $this->getFaultLog($OBDID , $beginDate , $endDate , 0);

        if (!isset($unusual['Rows'])) {
            $unusual = 0;
        } else {
            $unusual = count($unusual);
        }
        $arr = [
                    $sum_fuel,      // 总油耗
                    $sum_mileage,   // 行驶里程
                    $svg_fuel,      // 平均油耗
                    $duration,      // 行车时长
                    // $price,         // 参考油价
                    
                    $unusual,        // 异常项目
                    $behavior['play_size'], // 加减油数
               ];
        // $arr = [

        //    'sum_fuel' => $sum_fuel,
        //    'sum_mileage' => $sum_mileage,
        //    'svg_fuel' => $svg_fuel,
        //    'duration' => $duration,
        //    'unusual'  => $unusual,
        // ];

        return $arr;
                 
    }


    
    /**
     * 驾驶行为
     * @param  $[uid] int [< 用户uid >] 
     * 
     */
    
    public function getBehavior($uid, $start_time, $end_time)
    {   
        
        // 获取OBDID
        $OBD = $this->GetEqnum($uid);

        // 获取加减游次数
        $result = $this->getBehaviour($OBD , $start_time , $end_time);     
        

        // 计算 紧急加减速次数
        $sum_fuel = array_sum(array_column($result , 'updownspeedData'));
        // 计算 加油数
        $sum_mileage = array_sum(array_column($result , 'idlTimeData'));
        // 计算平均油耗
        // $svg_fuel  = $sum_fuel * $sum_mileage;
        // print_r($result);die;
        $arr = [
            'play_size' => $sum_fuel,
            'minus_size' => 0,
            'playOil_size' => $sum_mileage,
        ];
        return $arr;
    }

    /**
     * 接受时间
     * 
     */
    
    public function getTime($type = 1)
    {

        if ($type) {
            if($type == 1) {
                $start_time = strtotime('-3 day');
            } elseif ($type == 2) {

                $start_time = strtotime('-7 day');
            } elseif ($type == 3) {
                $start_time = strtotime('-15 day');
            } 
            // $end_time = time();
        } else {
            $start_time = input('start_time');
            $start_time = strtotime($start_time)?: null;
            if($start_time == null) {
                return null;
            }
            
        }
        // echo Date('Y-m-d',strtotime('-7 day'));die;
        $end_time = input('end_time')?:Date('Y-m-d');
        return ['start_time'=>Date('Y-m-d',$start_time),'end_time'=>$end_time];
    }

/**********诊断师 or 杂项***********/
    
    /**
     * 诊断师页面
     * 
     * @return  [<故障与非故障信息>]
     */
    
    public function getCheck()
    {
        $uid = $this->uid;
        $OBD = $this->GetEqnum($uid);
       
        if(!$OBD) {
            $this->result('',0,'暂未绑定OBD');
        }
        $result = $this->getFault($OBD);
           // print_r($result);die;
        // $result = json_decode($result,true);
        // print_R($result);die;
        // $result['a'] = $result;
        if (!$result) {
            $this->result('' , 0 , '获取失败');
        }
        foreach ($result as $key => $value) {
            if ($value['Name'] == '保养'){
                unset($result[$key]);
            }
        }
        $this->result($result,1,'获取成功');
        //
    }


    /**
     * 点击诊断
     * 出现故障添加故障信息;
     * 
     */
    
    public function moAccord()
    {

        $uid = $this->uid;
        $OBD = $this->GetEqnum($uid);
        if (!$OBD) {
            $this->result('', 0, '暂未绑定设备');
        }
        $result = $this->getFault($OBD);
        // print_r($OBD);die;
        // 入库故障信息
        if($result) {
            $this->AddUnusual($result,$uid);
        }
        

        if (!$result) {
            $this->result('',0,'没有异常');
        }
        

        $this->result($result,1,'获取成功');
    }
    

    /**
     * 入库故障信息
     * @param $result 故障信息
     * @param $[uid] [< 用户id >]
     * 
     */

    public function AddUnusual($result,$uid)
    {   
        // // 已经入库的故障 
        // $data = Db::table('cb_unusual')->where('u_id',$uid)->where('status',0)->select();
        // // 获取设备号
        // $OBDID = $this->GetEqnum($uid);
        // if ($data) {
        //     foreach ($data as $key => $value) {
        //         foreach ($result as $k => $v) {
        //             if ($value['name'] == $v['Name']) {
        //                 unset($result[$k]);
        //             }
        //         }
        //     }
        // }
        // // print_r($result);die;
        // $arr = [];
        // foreach ($result as $key => $value) {
        //     if ($value['ExceptionCount'] > 0) {
        //         $arr[$key]['u_id'] = $uid;
        //         $arr[$key]['name'] = $value['Name'];  //异常项目名称
        //         $arr[$key]['content'] = $value['Exception']; // 异常项目内容
        //         $arr[$key]['value'] = $value['Value']; // 异常项目值
        //         $arr[$key]['size'] = $value['ExceptionCount']; // 异常项目数量
        //         $arr[$key]['eq_num'] = $OBDID;

        //         if (isset($value['refer'])) {
        //             $arr[$key]['refer'] = $value['refer'];
        //         }
        //         // $arr[$key]['refer'] = substr($value['Exception'], strpos($value['Exception'], '<br/>') +5 );  // 异常项目参考值
        //         // $arr[$key]['content'] = substr($value['Exception'], 0, strpos($value['Exception'], '<br/>')); // 异常项目内容
        //     }
        // }

        // if( !empty($arr) ) {
        //     $r = Db::table('cb_unusual')->insertAll($arr);
        // }        
    }


    /**
     * 爱车有道
     * 
     */
    
    public function getYoudao()
    {

    }


    /**
     * 故障预警页面
     * 
     */
    
    public function getWarning()
    {
        // $data = $this->getFaultLog(, '2013-10-10', Date('Y-m-d'),0);
       // db::table('cb_unusual')->where('warning_time','<',1)->delete();
        $OBDID = $this->GetEqnum($this->uid);
        // $OBDID = 164875483570;
        // 
        $data = Db::table('cb_unusual')->where('eq_num', $OBDID)->where('status',0)->field('id,content,warning_time')->select();
    
        if (empty($data)) {
            $this->result('',0,'暂无数据');
        }

        // foreach ($data as $key => $value) {
                                                                                   
        //     $data[$key]['refer'] = substr($value['content'],strpos($value['content'], '<br/>') +5 );
        //     $data[$key]['content'] = substr($value['content'], 0, strpos($value['content'], '<br/>'));
        // }
        $data = changeTimes($data , 'warning_time' ,'Y-m-d H:i');
        $this->result($data , 1 , '获取成功');
    }


    /**
     * 故障预警点击忽略
     * 
     */
    
    public function ClickWarning()
    {
        $id = input('post.id')?:die('缺少参数');
        $res = Db::table('cb_unusual')->where('id',$id)->update(['status'=>1]);
        if ($res === false) {
            $this->result('',0,'操作失败');
        } 
        $this->result('',1,'操作成功');
    }


    /**
     * 尾气预警
     * 
     * @todo 等第三方接口编写
     */
    
    public function getTail()
    {


    }


    /**
     * 限制登录
     * 
     */
    
    public function VerReg(Map $map)
    {
        
        // $data = DB::table('cb_user')->where('u_id',$this->uid)->field('lng,lat')->find();
        $data = input('post.');
        $data['lng'] = $data['longitude'];
        $data['lat'] = $data['latitude'];
        unset($data['longitude']);
        unset($data['latitude']);
        unset($data['uid']);
        if (!$data['lng']) {
            $this->result('', 2, '用户暂未授权地区信息');
        }
// print_r($data);die;
        // 更新地区
        Db::table('cb_user')->where('u_id', $this->uid)->update($data);
// die;
        
        $area = $map->location($data['lat'],$data['lng']);
        // print_R($area);die;
        $result = Db::table('am_astrict')->where(
            [
                'name' => $area['province'],
                'urban' => $area['city'],
            ]
        )->find();

        if(empty($result)){
            $this->result('',0,'该地区暂未开放');
        }
        $this->result('',1,'正常开放');
        
                 
    }

/**** 支付 ****/

    /**
     * UBI 购买
     * 
     */
    
    public function buyUbi(Wx $wx)
    {
        Log::record('UBI购买信息');
        Log::save();
        $data = input('post.');
        $validate = validate('BuyUbi');
        if (!$validate->check($data)) {
            $this->result('' ,0 ,$validate->getError());
        } 
        $uid = $this->uid;
        // 添加支付订单
        $data['trade_no'] = $wx->getNonceStr();
        // 添加用户uid
        $data['u_id'] = $uid;
        
        // 入库订单信息
        $result = Db::table('cb_bay_ubi')->strict(false)->insertGetId($data);
        // 获取用户open_id;
        $open_id = DB::table('cb_user')->where('u_id' , $uid)->value('open_id');
         

        if ($result) {
            // 调用支付接口
            $data = $this->weixinappv($uid , $open_id , $data['trade_no'] , $result);
            $this->result('',1,'支付成功');
            
        } else {
            $this->result('',0,'异常支付');
        }
    }


    /**
     * 微信小程序接口
     */
    public function weixinappv($uid , $openid , $trade_no , $id ) {
        $wx = new Wx;  
        //统一下单接口  
        $unifiedorder = $this->unifiedorder($uid , $openid , $trade_no , $id);
        $parameters = array(  
            'appId' => Config('appid'), //小程序ID  
            'timeStamp' => ''.time(), //时间戳  
            'nonceStr' => $wx->getNonceStr(), //随机串  
            'package' => 'prepay_id=' . $unifiedorder['prepay_id'], //数据包  
            'signType' => 'MD5'//签名方式  
        );  
        //签名
        $parameters['paySign'] = $wx->getSign($parameters);  
        return $parameters;  
    } 


    //统一下单接口  
    private function unifiedorder($uid , $openid , $trade_no , $id ) {  
        $wx = new Wx;
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';  
        // 进行数据查询
        
        $parameters = array(  
            'appid' => Config('appid'),
            'mch_id' => Config('mch_id'),
            'nonce_str' => $wx->getNonceStr(), 
            'body' =>'邦保养会员',  
            'total_fee' => 365*100, 
            'openid' => $openid,
            'out_trade_no'=> $trade_no, 
            'spbill_create_ip' => '127.0.0.1', 
            'notify_url' => 'http://localhost/mp/public/index.php/ubi/notify', 
            'trade_type' => 'JSAPI',
            'attach' => 'u_id='.$uid.'&id='.$id
        );  
        //统一下单签名  
        $parameters['sign'] = $wx->getSign($parameters);  
        $xmlData = $wx->arrayToXml($parameters);  

        $return = $wx->xmlToArray($wx->postXmlCurl($xmlData, $url, 60)); 
        // print_R($return);die;
        return $return;  
    }  


    /**
     * 微信支付回调
     */
    public function notify(Wx $wx){
        
        $xml =  file_get_contents("php://input");
        $data = $wx->xmlToArray($xml);
        $data_sign = $data['sign'];
        unset($data['sign']);
        // Db::startTrans();
        $sign = $wx->getSign($data);
            // 判断签名是否正确  判断支付状态
            if (($sign===$data_sign) && ($data['return_code']=='SUCCESS') && ($data['result_code']=='SUCCESS')){
                    $result = $data;
                    $attach = $wx->getStrVal($data['attach']);
                    $uid = $attach['u_id'];
                    $id = $attach['id'];
                    // 修改支付状态
                    $result = DB::table()->where('u_id' , $uid)->where('id' , $id)->update(['pay_status'=>1]);                                                                                                                  
                    // // 返回状态给微信服务器
                    // echo ($result) ? $wx->returnWxXml(1) : $wx->returnWxXml(0);
                    // // exit;
                    // return $result;
                }else{
            
                    $result = false;
                }
            
            // 返回状态给微信服务器
            echo ($result) ? $wx->returnWxXml(1) : $wx->returnWxXml(0);
            exit;
            // return $result;
    
    }

/*第三方接口*/

    /**
     * 故障信息
     * @param  $[OBDID] int or array [<用户OBDid>]
     * @param  $[beginDate] string [<开始时间>]
     * @param  $[endDate] string [<结束时间>]
     *
     * @return json or null  
     */
    
    public function getFault($OBDID)
    {   
        if (!$OBDID) {
            return 0;
        }

        $status = $this->mIsOBD($OBDID);
        if (!$status) {
            return 0;
        }

        if (!is_array($OBDID)) {
            $OBDID = [
                $OBDID
            ];
        }
        // 构造数据
        $json = json_encode([
                        'OBDIDs'=> $OBDID,
                    ]);

        // 请求接口
        $result = geturl($json,UBI.'api/VehicleCheckup');
          // print_r($result);die;
        $result = json_decode($result,true);
        // print_r($result);die;
        if(isset($result['Message']) && $result['Message'] == '出现错误。') {
            return 0;
        }
 

        foreach ($result as $key => $value) { 
            if (strstr($value['Exception'], '<br/>')) {
                $result[$key]['refer'] = str_replace('<br/>', '', substr($value['Exception'], strpos($value['Exception'], '<br/>') + strlen('<br/>')));  // 异常项目参考值
                $result[$key]['Exception'] = substr($value['Exception'], 0, strpos($value['Exception'], '<br/>')); // 异常项目内容
            }
            // unset($result[$key]['Exception']);
        }

        return $result;              
    }


    /**
     * 故障记录
     * @param  $[OBDID] int or array [<用户OBDid>]
     * @param  $[beginDate] string [<开始时间>]
     * @param  $[endDate] string [<结束时间>]
     *
     * @todo  暂未使用
     * @return json or null 
     */

    public function getFaultLog($OBDID , $beginDate='2015-4-23' , $endDate , $type = -1)
    {   

        if (!$OBDID) {
            return 0;
        }

        $status = $this->mIsOBD($OBDID);
        if (!$status) {
            return 0;
        }

        if (!is_array($OBDID)) {
            $OBDID = [
                $OBDID
            ];
        }
        // print_r($OBDID);die;
        // 构造数据
        $json = json_encode([
                        'OBDIDs'=> $OBDID
                        ,'beginDate'=> $beginDate
                        ,'endDate'  => $endDate
                        ,'pageSize' => 200
                        ,'pageNum' => 1
                        ,'isProcessed' => $type
                    ]);
        
        // 请求接口
        $result = geturl($json,UBI.'api/faultcode');
         
        return json_decode($result,true)?:0;              
    }


    /**
     * 行驶总里程
     * @param  $[OBDID] int or array [<用户OBDid>]
     * @param  $[beginDate] string [<开始时间>]
     * @param  $[endDate] string [<结束时间>]
     *
     * @return json or null 
     */

    public function getTotalMileage($OBDID , $beginDate='2015-4-23' , $endDate)
    {   

        if (!$OBDID) {
            return 0;
        }

        $status = $this->mIsOBD($OBDID);
        if (!$status) {
            return 0;
        }

        if (!is_array($OBDID)) {
            $OBDID = [
                $OBDID
            ];
        }
    
        // 构造数据
        $json = json_encode([
                        'OBDIDs'=> $OBDID
                        ,'beginDate'=> $beginDate
                        ,'endDate'  => $endDate
                    ]);
    
        // 请求接口
        $result = geturl($json,UBI.'api/static_mileageAndoilForWeixin');
        
        return json_decode($result,true)?:0;              
    }


    /**
     * 今日行驶总里程
     * @param  $[OBDID] int or array [<用户OBDid>]
     * @param  $[dt] string [<要查询的时间>]
     * @return json or null 
     */

    public function getDayMileage($OBDID , $dt)
    {   

        if (!$OBDID) {
            return 0;
        }

        $status = $this->mIsOBD($OBDID);
        if (!$status) {
            return 0;
        }

        if (!is_array($OBDID)) {
            $OBDID = [
                $OBDID
            ];
        }
    
        // 构造数据
        $json = json_encode([
                        'OBDIDs'=> $OBDID
                        ,'dt'=> $dt
                    ]);
    
        // 请求接口
        $result = geturl($json,UBI.'api/static_currentMileageOil');

        return json_decode($result,true)?:0;              
    }


    /**
     * 总里程油耗
     * @param  $[OBDID] int or array [<用户OBDid>]
     * @param  $[beginDate] string [<开始时间>]
     * @param  $[endDate] string [<结束时间>]
     *
     * @return json or null 
     */

    public function getOil($OBDID , $beginDate='2015-4-23' , $endDate)
    {   
        
        if (!$OBDID) {
            return ['oilCountData'=>[0,0], 'mileageData'=>[0,0]];
        }
        

        $status = $this->mIsOBD($OBDID);
        if (!$status) {
            return ['oilCountData'=>[0,0], 'mileageData'=>[0,0]];
        }





        if (!is_array($OBDID)) {
            $OBDID = [
                $OBDID
            ];
        }
    
        // 构造数据
        $json = json_encode([
                        'OBDIDs'=> $OBDID
                        ,'beginDate'=> $beginDate
                        ,'endDate'  => $endDate
                        ,'groupBy'  => $OBDID
                    ]);
    
        // 请求接口
        $result = geturl($json,UBI.'api/static_mileageAndoil');
        
        return json_decode($result,true)?:['oilCountData'=>[0,0], 'mileageData'=>[0,0]];              
    }


    /**
     * 行车时长
     * @param  $[OBDID] int or array [<用户OBDid>]
     *
     * @return json or null 
     */

    public function duration($OBDID)
    {   

        if (!$OBDID) {
            return 0;
        }

        $status = $this->mIsOBD($OBDID);
        if (!$status) {
            return 0;
        }

        if (!is_array($OBDID)) {
            $OBDID = [
                $OBDID
            ];
        }
    
        // 构造数据
        $json = json_encode([
                        'OBDIDs'=> $OBDID
                    ]);
    
        // 请求接口
        $result = geturl($json,UBI.'api/LastLoction');
        // print_r($result);die;
        return json_decode($result,true)?:0;              
    }


    /**
     * 急加油次数
     * @param  $[OBDID] int or array [<用户OBDid>]
     * @param  $[beginDate] string [<开始时间>]
     * @param  $[endDate] string [<结束时间>]
     *
     * @return json or null 
     */

    public function getBehaviour($OBDID , $beginDate='2015-4-23' , $endDate)
    {   

        if (!$OBDID) {
            return ['updownspeedData'=>[0,0],'idlTimeData'=>[0,0]];
        }

        $status = $this->mIsOBD($OBDID);
        if (!$status) {
            return ['updownspeedData'=>[0,0],'idlTimeData'=>[0,0]];
        }

        if (!is_array($OBDID)) {
            $OBDID = [
                $OBDID
            ];
        }
    
        // 构造数据
        $json = json_encode([
                        'OBDIDs'=> $OBDID
                        ,'beginDate'=> $beginDate
                        ,'endDate'  => $endDate
                    ]);
    
        // 请求接口
        $result = geturl($json,UBI.'api/static_Driverbehavior');
        
        return json_decode($result,true)?:['updownspeedData'=>[0,0],'idlTimeData'=>[0,0]];              
    }





/***
 *                    _ooOoo_
 *                   o8888888o
 *                   88" . "88
 *                   (| -_- |)
 *                    O\ = /O
 *                ____/`---'\____
 *              .   ' \\| |// `.
 *               / \\||| : |||// \
 *             / _||||| -:- |||||- \
 *               | | \\\ - /// | |
 *             | \_| ''\---/'' | |
 *              \ .-\__ `-` ___/-. /
 *           ___`. .' /--.--\ `. . __
 *        ."" '< `.___\_<|>_/___.' >'"".
 *       | | : `- \`.;`\ _ /`;.`/ - ` : | |
 *         \ \ `-. \_ __\ /__ _/ .-` / /
 * ======`-.____`-.___\_____/___.-`____.-'======
 *                    `=---='
 *
 * .............................................
 *          佛祖保佑             永无BUG
 */

}