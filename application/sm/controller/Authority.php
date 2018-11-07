<?php 
namespace app\sm\controller;
use app\base\controller\Sm;
use think\Db;
use WxJ\WxJ;
use Pay\Wx;
use app\sm\controller\Login;

class Authority extends Sm
{

    public function initialize(){
//        parent::initialize();
        $this->wx = new Wx();
        $this->uid = input('post.uid');
    }
    /**
     * 运营总监的区域列表
     * @return [type] [description]
     */
    public function headerAreaList()
    {
        // return $this->uid;
        $list = Db::table('sm_area')
                ->alias('a')
                ->join('co_china_data d','a.area = d.id')
                ->where([
                    'sm_id' => $this->uid,
                    'sm_type' => 2,
                    'is_exits' => 1
                ])
                ->field('a.id,d.name as province,audit_status,sm_profit,sm_status')
                ->select();
        if($list) {
            $this->result($list,1,'获取数据成功');
        } else {
            $this->result('',0,'暂无数据');
        }
    }
    // 服务经理的 服务区域列表
    public function serviceAreaList()
    {
        $list =Db::table('sm_area')
            ->where('sm_id',$this->uid)
            ->where('sm_type',1)
            ->where('is_exits',1)
            ->where('sm_mold','<>',2)
            ->field('id,area,sm_mold,sm_profit,sm_status,audit_status')
            ->select();
        if (!empty($list)){
            foreach ($list as $key=>$value){
                $list[$key]['area'] = $this->provinces($list[$key]['area']);
            }
            $this->result($list,1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    // 服务经理信息
    public function managerInfo()
    {    
        // echo $this->uid;die;
        $info = Db::table('sm_user')
            ->alias('a')
            ->join('co_bank_code b','a.bank_code = b.code','LEFT')
            ->where('a.id',$this->uid)
            // ->where([
            //     'person_rank' => [1,4]
            // ])
            ->field('a.id,a.name,a.phone,a.head_pic,a.sex,a.bank_name,b.name as bank_names,a.bank_branch,person_rank,a.account')
            ->find();
        $this->result($info,1,'数据返回成功');
    }

    /**
     * 获取所有可以选择的省份
     * @return [type] [description]
     */
    public function getPro()
    {
        //获取所有的省份
        $pro = Db::table('co_china_data')
                ->where('pid',1)
                ->select();
        //获取所有被选择的省份
        $area = Db::table('sm_area')
                ->where([
                    'audit_status' => [0,1]
                ])
                ->where('sm_mold','<>',2)
                ->field('id,area')
                ->select();
        //当审核中以及通过审核时，该地区不可被其他人选择
        foreach ($area as $key=>$value){
            foreach ($pro as $k=>$v){
                if ($area[$key]['area'] == $pro[$k]['id']){
                    unset($pro[$k]);
                }
            }
        }
      
        $this->result($pro,1,'获取成功');
    }
    /**
     * 服务经理获取所有的省
     * @return [type] [description]
     */
    public function getAllPro()
    {
        //获取前端提交过来的share_id
        $data = input('get.');
        if($data['share_id'] !== '0' && $data['share_id'] !=='') {
            //获取该运营总监的省份
            $pro = Db::table('co_china_data')
                    ->alias('d')
                    ->join('sm_area a','a.area = d.id')
                    ->where([
                        'a.sm_id' => $data['share_id'],
                        'a.audit_status' => [0,1]
                    ])
                    ->where('a.sm_mold','<>',2)
                    ->field('d.id,d.code,d.name,d.pid,d.sort')
                    ->select();
        } else {
            //获取所有的省份
            $pro = Db::table('co_china_data')
                    ->where('pid',1)
                    ->select(); 
        }
        
        $this->result($pro,1,'获取成功');
    }
    /**
     * 获取某个省份下可以选择的所有城市
     * @return [type] [description]
     */
    public function getCity()
    {
        $id = input('get.id');
        $city = $this->excPro($id);
        // 如果该省已无可选择市区  返回0 ，前端判断向用户进行提醒   2018-10-9 cjx
        if(!empty($city)){
            $this->result($city,1,'获取成功');
        }else{
             $this->result('',0,'该省份无可选区域');
        }
        
    }

    // 新增区域申请 操作
    public function addArea()
    {
        $data = input('post.');

        // 查找是否有已付费但被驳回
        $list = Db::table('sm_area')
            ->where('sm_id',$data['sm_id'])
            ->where('audit_status',2)
            ->where('is_exits',1)
            ->limit(1)
            ->order('create_time DESC')
            ->find();
        // 查看该用户是否有未审核的地区 2018-10-9 cjx
        $area = Db::table('sm_area')
                ->where('sm_id',$data['sm_id'])
                ->where('audit_status',0)
                ->where('is_exits',1)
                ->limit(1)
                ->order('create_time DESC')
                ->find();
        $person_rank = Db::table('sm_user')
                        ->where('id',$this->uid)
                        ->value('person_rank');
        if($person_rank == 1 || $person_rank == 4){
            $divide = Db::table('am_sm_set')->where('status',1)->value('maid');
        } else {
            $divide = Db::table('am_sm_set')->where('status',2)->value('maid');
        }
        
        if (empty($list) && empty($area)){
            $model = new Login();
            $data['trade_no'] = $this->wx->createOrder();
            $arr = [
                'area' => $data['area'],
                'money' => $data['money'],
                'pay_status' => 0,
                'trade_no' => $data['trade_no'],
                'create_time' => date('Y-m-d H:i:s',time()),
                'sm_id' => $data['sm_id'],
                'sm_profit' => $divide,
            ];
            $lastId = Db::table('sm_area')->insertGetId($arr);
            $openid = $data['openid'];
            if($lastId){
                Db::commit();
                // $result = $model->weixinapp($lastId,$openid);
                // $result['trade_no'] = $data['trade_no'];
                // $result['cid'] = $lastId;
                $this->result('',1,'新增区域成功,等待后台审核');
            } else {
                Db::rollback();
                $this->result('',0,'发起支付异常');
            }
        }else if(empty($area)){
           $arr = [
               'area' => $data['area'],
               'sm_id' => $data['sm_id'],
               'money' => $data['money'],
               'pay_status' => $list['pay_status'],
               'trade_no' => $list['trade_no'],
               'transaction_id'=> $list['transaction_id'],
               'pay_time'=> $list['pay_time'],
               'create_time'=> date('Y-m-d H:i:s',time()),
               'audit_status'=> 0,
               'sm_status'=> $list['sm_status'],
               'sm_profit'=> $divide,
               'sm_mold'=> $list['sm_mold'],
               'sm_type' => $list['sm_type'],
               'if_read'=> $list['if_read'],
           ];
           // 如果有被驳回的订单则把订单修改成为重新向总后台进行审核  2018-10-9 cjx
           $ret = Db::table('sm_area')->where('id',$data['id'])->strict(false)->update($arr);
           if ($ret){
               $this->result('',2,'新增区域成功,等待后台审核');
           }else{
               $this->result('',3,'新增区域失败');
           }
        }
        $this->result('',3,'您有待审核的区域,请先联系总后台进行审核');
    }

    // 新增区域  审核状态返回
    public function auditStatus()
    {
        // 先判断是否 已经存在一条
        $list = Db::table('sm_area')
            ->where('sm_id',$this->uid)
            ->where('if_read',0)
            ->where('sm_mold','<>',2)
            ->where('audit_status','<>',0)
            ->field('id,audit_status')
            ->limit(2,1)
            ->order('create_time ASC')
            ->find();
        if ($list){
            $this->result($list,1,'返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    // 新增区域  点击确定  修改状态值
    public function editStatus()
    {
        $id = input('post.id');
        $ret = Db::table('sm_area')
            ->where('id',$id)
            ->update(['if_read'=>1]);
        if ($ret){
            $this->result('',1,'修改成功');
        }else{
            $this->result('',0,'修改失败');
        }
    }
    // 新增区域 查看驳回理由
    public function rejectionDetail()
    {
        $id = input('post.id'); // 主键ID
        $reason = Db::table('sm_area')
            ->where('id',$id)
            ->field('reason,audit_person,FROM_UNIXTIME(audit_time) as time')
            ->find();
        // 修改  状态  已读
        Db::table('sm_area')->where('id',$id)->update(['if_read'=>1]);
        if ($reason){
            $this->result($reason,1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    // 取消区域申请 操作
    public function cancelArea()
    {
        $data = input('post.');  // 提交的数据
	if (trim($data['cancel_reason']) == ''){
            $this->result('',0,'请输入取消理由');
        }
        $arr = [
            'sid' => $data['sid'],
            'cancel_reason' => $data['cancel_reason'],
            'sm_id' => $data['sm_id'],
            'create_time'=>date('Y-m-d H:i:s',time()),
            'status' => 0,
        ];
        Db::startTrans();
        $ret = Db::table('sm_apply_cancel')->insert($arr);
        $ree = Db::table('sm_area')->where('id',$data['sid'])->update(['sm_mold'=>3]);
        if ($ret && $ree){
            DB::commit();
            $this->result('',1,'取消区域提交成功');
        }else{
            Db::rollback();
            $this->result('',0,'取消区域提交失败');
        }
    }

    // 取消区域 审核状态返回
    public function auditState()
    {
        $list = Db::table('sm_apply_cancel')
            ->where('sm_id',$this->uid)
            ->where('read_status',0)
            ->where('status','<>',0)
            ->field('id,status')
            ->limit(1)
            ->order('audit_time ASC')
            ->find();
        if ($list){
            $this->result($list,1,'返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    //取消区域  点击确定  修改状态值
    public function editAudit()
    {
        $id = input('post.id');
        $ret = Db::table('sm_apply_cancel')
            ->where('id',$id)
            ->update(['read_status'=>1]);
        if ($ret){
            $this->result('',1,'修改成功');
        }else{
            $this->result('',0,'修改失败');
        }
    }

    //取消区域  查看驳回理由详情
    public function refusalDetail()
    {
        $id = input('post.id'); // 主键ID
        $reason = Db::table('sm_apply_cancel')
            ->where('id',$id)
            ->field('reason,audit_person,FROM_UNIXTIME(audit_time) as time')
            ->find();
        // 修改  状态  已读
        Db::table('sm_apply_cancel')->where('id',$id)->update(['read_status'=>1]);
        if ($reason){
            $this->result($reason,1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    /**
     * 排除已选择的省市
     * @return [type] [description]
     * $pid 省id
     */
    public function excPro($pid)
    {
        $city = Db::table('co_china_data')
                    ->where('pid',$pid)
                    ->select();
        //获取所有被选择的城市
        $area = Db::table('sm_area')
            ->where('audit_status','<>',2)
            ->where('audit_status','<>',4)
            ->where('sm_mold','<>',2)
            ->field('id,area')
            ->select();
        //当区域状态是待审核以及审核通过时，不可被其他人选择
        foreach ($area as $key=>$value){
            foreach ($city as $k=>$v){
                if ($area[$key]['area'] == $city[$k]['id']){
                    unset($city[$k]);
                }
            }
        }
        return $city;
    }
    /**
     * 获取总后台直接取消区域的
     * @return [type] [description]
     */
    public function getSmMold()
    {
        $list = Db::table('sm_area')
                ->alias('a')
                ->join('co_china_data d','d.id = a.area')
                ->where([
                    'a.sm_id' => $this->uid,
                    'a.if_read' => 0,
                    'a.sm_mold' => 2
                ])
                ->field('a.id,d.name as city')
                ->order('id')
                ->limit(1)
                ->find();
        $count = Db::table('sm_apply_cancel')
                    ->where('sid',$list['id'])
                    ->find();
        if($list && $count < 0) {
            $this->result($list,1,'您的'.$list['city'].'区域已被总后台取消');
        } else {
            $this->result('',0,'暂无数据');
        }
    }
    /**
     * 更改已读状态
     * @return [type] [description]
     */
    public function readSmMold()
    {
        $id = input('post.id');
        $info = Db::table('sm_area')
                ->where([
                    'id' => $id
                ])
                ->field('from_unixtime(audit_time) as time,audit_person,reason')
                ->find();
        $up = Db::table('sm_area')
                ->where([
                    'id' => $id
                ])
                ->update(['if_read'=>1]);
        if($info) {
            $this->result($info,1,'获取成功');
        } else {
            $this->result('',0,'暂无数据');
        }
    }
}


