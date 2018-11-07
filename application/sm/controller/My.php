<?php 
namespace app\sm\controller;
use app\base\controller\Sm;
use think\Db;
use Epay\BbyEpay;

class My extends Sm
{

    function initialize(){
        parent::initialize();
        $this->uid = input('post.uid');
        $this->buyEpay = new BbyEpay();
//        $this->uid = 1;
    }

    // 服务经理 我的页面

   // 我的 投诉 条数
    public function complaintCount()
    {
        $status = Db::table('sm_user')->where('id',$this->uid)->value('person_rank');
        if ($status == 1 && $status == 2){
            if ($status == 1){
                $s = $this->uid;
            }else{
                // 搜索 该 运营总监的运营区域
                $area = Db::table('sm_area')
                    ->where('sm_id',$this->uid)
                    ->value('area');
                // 查询 所有服务经理所属的省级ID
                $sm_area = Db::table('sm_area')
                    ->alias('sa')
                    ->join('co_china_data cc','sa.area = cc.id')
                    ->where('sa.sm_type',1)
                    ->field('cc.pid,sa.sm_id')
                    ->select();
                if (!empty($sm_area)){
                    foreach ($sm_area as $key=>$value){
                        if ($area == $sm_area[$key]['pid']){
                            $s[] = $sm_area[$key]['sm_id'];
                        }
                    }
                }else{
                    $s = array();
                }
            }
            $count = Db::table('sm_complaint')
                ->where('sm_id','in',$s)
                ->where('status',1)
                ->where('sm_status',0)
                ->count();
            $this->result($count,1,'返回成功');
        }else{
            $this->result('0',1,'返回成功');
        }
    }

    // 服务经理 我的状态
    public function myStatus()
    {
        $status = Db::table('sm_user')
                    ->where('id',$this->uid)
                    ->value('person_rank');
        if($status == 5) {
            //是待审核的运营总监
            //获取运营总监的被驳回的区域
            $area = Db::table('sm_area')
                    ->alias('a')
                    ->join('co_china_data d','d.id = a.area')
                    ->where([
                        'sm_id' => $this->uid
                    ])
                    ->order('a.id desc')
                    ->limit(1)
                    ->field('d.name,a.sm_profit,a.id,a.audit_status')
                    ->find();
            if($area) {
                $this->result($area,2,'获取成功');
            } else {
                $this->result('',0,'无权限');
            }
        }
        if ($status == 1  || $status == 2){
            if ($status == 1){
                $sm_type = 1;
            }elseif ($status == 2){
                $sm_type = 2;
            }
            $pageSize = 8;     // 每页显示条数
            $page = input('post.page')? : 1;  // 页码
            $count = Db::table('sm_area')
                ->where('sm_id',$this->uid)
                ->where('audit_status',1)
                ->where('sm_type',$sm_type)
                ->where('is_exits',1)
                ->where('sm_mold','<>',2)
                ->where('sm_mold','<>',3)
                ->count();
            $rows = ceil($count/$pageSize);
            $list = Db::table('sm_area')
                ->where('sm_id',$this->uid)
                ->where('audit_status',1)
                ->where('sm_type',$sm_type)
                ->where('is_exits',1)
                ->where('sm_mold','<>',2)
                ->where('sm_mold','<>',3)
                ->field('id,area,sm_status,sm_mold,UNIX_TIMESTAMP(create_time) as time,sm_profit')
                ->page($page,$pageSize)
                ->select();
            if (!empty($list)){
                foreach ($list as $key=>$value){
                    if ($status == 1){
                        $list[$key]['area'] = $this->provinces($list[$key]['area']);
                    }elseif ($status === 2){
                        $list[$key]['area'] = $this->provincenes($list[$key]['area']);
                    }
                    if ($list[$key]['sm_mold'] == 0){
                        if ($status == 1){
                            $list[$key]['times'] = $list[$key]['time'] + 90 * 86400 - time();
                            unset($list[$key]['time']);
                        }elseif ($status == 2){
                            $list[$key]['times'] = $list[$key]['time'] + 180 * 86400 - time();
                            unset($list[$key]['time']);
                        }
                    }else{
                        $list[$key]['times'] = 0;
                        unset($list[$key]['time']);
                    }
                }
            }
            if ($count > 0){
                $this->result(['list'=>$list,'rows'=>$rows],1,'数据返回成功');
            }else{
                $this->result('',0,'暂无数据');
            }
        }else{
            $this->result('',0,'无权限');
        }
    }


    //服务经理  我的运营商
    public function agentList()
    {
        // 查询该 服务经理 的 服务区域
        $marea = Db::table('sm_area')
            ->where('sm_id',$this->uid)
            ->where('audit_status',1)
            ->where('is_exits',1)
            ->where('sm_mold','<>',2)
            ->where('sm_type',1)
            ->field('id,area')
            ->select();
        if (!empty($marea)){
            // 将 服务经理的区域生成数组
            foreach ($marea as $k=>$v){
                $arr[] = $marea[$k]['area'];
            }
            // 查询所有运营商的区域市级ID
            $list = Db::table('ca_area')->select();
            foreach ($list as $key=>$value){
                $area[$key]['cid'] = Db::table('co_china_data')
                    ->where('id',$list[$key]['area'])
                    ->value('pid');
                if (in_array($area[$key]['cid'],$arr)){
                    // 查询运营商名称
                    $list[$key]['company'] = Db::table('ca_agent')
                        ->where('aid',$list[$key]['aid'])
                        ->value('company');
                    // 查询该运营商 的运营区域
                    $list[$key]['operating'] = $this->province($value['area']);
                    // 查询运营商下的维修厂数量
                    $list[$key]['count'] = Db::table('cs_shop')
                        ->where('aid',$list[$key]['aid'])
                        ->where([
                            'audit_status' => [2,6]
                        ])
                        ->count();
//                    unset($list[$key]['area']);
                }
                else{
                    unset($list[$key]['area']);
                    unset($list[$key]['aid']);
                }
            }
            $list = array_values(array_filter($list));
            if ($list){
                $this->result(['list'=>$list],1,'数据返回成功');
            }else{
                $this->result('',0,'暂无数据');
            }
        }else{
            $this->result('',0,'暂无数据');
        }

    }
   /**
     * 维修厂列表
     * @return [type] [description]
     */
    public function getShopList(){
        //获取提交过来的运营商id以及区域id
        $data = input('post.');
        $list = Db::table('cs_shop_set')
                ->alias('ss')
                ->join('cs_shop s','s.id = ss.sid')
                ->leftJoin('u_card c','c.sid = s.id')
                ->where([
                    'ss.county_id' => $data['area'],
                    's.audit_status' => [2,6]
                ])
                ->field("s.company,s.id,s.leader,s.phone")
                ->group('s.company')
                ->select();
        foreach ($list as $key => $value) {
            $list[$key]['number'] = Db::table('u_card')
                                    ->where([
                                        'pay_status' => 1,
                                        'sid' => $list[$key]['id']
                                    ])
                                    ->count();
            $list[$key]['detail'] = '已售 '.$list[$key]['number'].' 张';
        }
        if($list) {
            $this->result($list,1,'获取成功');
        } else {
            $this->result('',0,'暂无数据');
        }
    }
    // 我的代理商
    public function brokerList()
    {
        $pageSize = 8;  // 每页显示条数
        $page = input('post.page')? : 1;  //  页码
        $count = Db::table('cg_supply')
            ->alias('a')
            ->join('cg_supply_set b','a.gid = b.gid','LEFT')
            ->join('sm_area c','c.sm_id ='.$this->uid,'LEFT')
            ->where('b.service_id',$this->uid)
            ->count();
        $rows = ceil($count / $pageSize);
        $list = Db::table('cg_supply')
            ->alias('a')
            ->join('cg_supply_set b','a.gid = b.gid','LEFT')
            ->join('sm_area c','c.sm_id ='.$this->uid,'LEFT')
            ->where('b.service_id',$this->uid)
            ->field('a.gid,a.company,supply_profit')
            ->page($page,$pageSize)
            ->select();
        foreach ($list as $key=>$value){
            $list[$key]['area'] = Db::table('cg_area')
                ->where('gid',$list[$key]['gid'])
                ->field('area')
                ->select();
            foreach ($list[$key]['area'] as $k=>$v){
                $list[$key]['supplyArea'][] = $this->provinces($v['area']);
                unset($list[$key]['area']);
            }
        }
        if ($count > 0){
            $this->result(['list'=>$list,'rows'=>$rows],1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    // 我的 往期收入  时间选择列表
    public function timeChoice()
    {
        $pageSize = 8;                         // 每页显示条数
        $page = input('post.page')? : 1;  //  页码
        $month = input('post.month')? : date('Y-m',time()); // 当前月份
        $count = Db::table('sm_income')
            ->where('sm_id',$this->uid)
            ->where("DATE_FORMAT(create_time,'%Y-%m') = '$month'")
            ->group('company')
            ->count();
        $rows = ceil($count / $pageSize);
        $list['data'] = Db::table('sm_income')
            ->where('sm_id',$this->uid)
            ->where('person_rank',1)
            ->where("DATE_FORMAT(create_time,'%Y-%m') = '$month'")
            ->field('id,company,sum(money) as money,create_time,address,type,read_status')
            ->group('company')
            ->page($page,$pageSize)
            ->select();
        $list['total'] = 0;
        foreach ($list['data'] as $key=>$value){
            $list['total'] += $list['data'][$key]['money'];
        }
        if ($count > 0){
            $this->result(['list'=>$list,'rows'=>$rows],1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    // 我的 往期收入  类型选择列表
    public function typeChoice()
    {
        $pageSize = 8;                         // 每页显示条数
        $page = input('post.page')? : 1;  //  页码
        $type = input('post.type')? : 1; // 类型
        $person_rank = 1; // 类型  服务经理
        $count = Db::table('sm_income')
            ->where('sm_id',$this->uid)
            ->where('type',$type)
            ->where('person_rank',$person_rank)
            ->group('company')
            ->count();
        $rows = ceil($count / $pageSize);
        $list = Db::table('sm_income')
            ->where('sm_id',$this->uid)
            ->where('type',$type)
            ->where('person_rank',$person_rank)
            ->field('id,company,sum(money) as money,create_time,address,type,read_status')
            ->group('company')
            ->page($page,$pageSize)
            ->select();
        $list['total'] = 0;
        foreach ($list as $key=>$value){
            $list['total'] += $list[$key]['money'];
        }
        if ($count > 0){
            $this->result(['list'=>$list,'rows'=>$rows],1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    // 我的 投诉 列表
    public function complaintList()
    {
        // 判断是 服务经理 还是 运营总监
        $status = Db::table('sm_user')->where('id',$this->uid)->value('person_rank');
        if ($status == 1 || $status == 2){
            $pageSize = 8;                         // 每页显示条数
            $page = input('post.page')? : 1;
            if ($status == 1){
                $count = Db::table('sm_complaint')
                    ->where('sm_id',$this->uid)
                    ->count();
                $rows = ceil($count/$pageSize);
                $list = Db::table('sm_complaint')
                    ->field('id,content,create_time,type')
                    ->where('sm_id',$this->uid)
                    ->page($page,$pageSize)
                    ->select();
	        if(empty($list)){
                    $list = array();
                }
            }elseif ($status == 2){
                // 搜索 该 运营总监的运营区域
               $area = Db::table('sm_area')
                   ->where('sm_id',$this->uid)
                   ->value('area');
               // 查询 所有服务经理所属的省级ID
                $data = Db::table('sm_area')
                    ->alias('sa')
                    ->join('co_china_data cc','sa.area = cc.id')
                    ->where('sa.sm_type',1)
                    ->field('cc.pid,sa.sm_id')
                    ->select();
                if (!empty($data)){
                    foreach ($data as $key=>$value){
                        if ($area == $data[$key]['pid']){
                            $arr = Db::table('sm_complaint')
                                ->alias('a')
                                ->join('sm_user b','a.sm_id = b.id','LEFT')
                                ->where('a.sm_id',$data[$key]['sm_id'])
                                ->field('a.id,a.content,a.create_time,a.type,b.name,b.head_pic')
                                ->order('create_time DESC')
                                ->select();
                            $lists[] = $arr;
                        }
                    }
                    if (!empty($lists)){
                        foreach ($lists as $key=>$value){
                            foreach ($lists[$key] as $k=>$v){
                                $list[] = $lists[$key][$k];
                            }
                        }
                    }
                    $start = ($page-1)*$pageSize; // 每次分页开始的位置
                   if (!empty($list)){
                       $total = count($list);
                       $rows = ceil($total/$pageSize);
                       $list = array_slice($list,$start,$pageSize);
                   }else{
                       $list = array();
                   }
                }
            }
            if (!empty($list)){
                $this->result(['list'=>$list,'rows'=>$rows],1,'数据返回成功');
            }else{
                $this->result('',0,'暂无数据');
            }
        }else{
            $this->result('',0,'无权限');
        }
    }

    // 我的  投诉 详情
    public function complaintDetail()
    {
        $id = input('post.id'); // 投诉列表 ID
        $type = Db::table('sm_complaint')->where('id',$id)->value('type');  
        //投诉者类型 1运营商 2市级代理  3维修厂
        if ($type == 1){
            $table = 'ca_agent';
            $tid = 'aid';
        }elseif ($type == 2){
            $table = 'cg_supply';
            $tid = 'gid';
        }else{
            $table = 'cs_shop';
            $tid = 'id';
        }
        $list = Db::table('sm_complaint')
            ->alias('a')
            ->join('sm_user b','a.sm_id = b.id','LEFT')
            ->join(''.$table.' c','a.uid= c.'.$tid.'','LEFT')
            ->field('a.city_id,a.city_id,a.company,a.phone,a.content,a.create_time,b.name,b.phone as telphone,b.head_pic,a.status,a.sm_status,FROM_UNIXTIME(handle_time) as times')
            ->where('a.id',$id)
            ->find();
       if (!empty($list)){
           $list['area'] = $this->provinces($list['city_id']);
           // 服务经理区域
           $city_id = Db::table('co_china_data')
               ->where('id',$list['city_id'])
               ->field('pid,name')
               ->find();
           $province = Db::table('co_china_data')
               ->where('id',$city_id['pid'])
               ->value('name');
           $list['areas'] = $province.$city_id['name'];
	if ($list['times'] == null){
               $list['times'] = '暂没有撤回';
           }
           unset($list['county_id']);
           if ($list){
               $this->result($list,1,'数据返回成功');
           }else{
               $this->result('',0,'暂无数据');
           }
       }else{
           $this->result('',0,'暂无数据');
       }
    }

    // 问题反馈
    public function problemFeedback()
    {
        $data = input('post.');
        // 判断该用户是服务经理还是运营总监
        $status = Db::table('sm_user')->where('id',$this->uid)->value('person_rank');
        if ($status == 1 || $status == 2){
            if ($status == 1){
                // 查询该服务经理区域是否有运营总监
                $list = Db::table('sm_area')
                    ->alias('sa')
                    ->join('co_china_data cc','sa.area = cc.id')
                    ->where('sm_id',$this->uid)
                    ->field('cc.pid')
                    ->select();
                if (!empty($list)){
                    foreach ($list as $key=>$value){
                        $m_area[] = $list[$key]['pid'];
                    }
                  // 所有运营总监的区域
                    $sm_area = Db::table('sm_area')
                        ->where('sm_type',2)
                        ->where('area','in',$m_area)
                        ->column('sm_id');
                    if (!empty($sm_area)){
                        $header = implode(',',$sm_area);
                    }else{
                        $header = 0;
                    }
                }else{
                    $this->result('',0,'无权限');
                }
            }elseif ($status == 2){
                $header = 0;
            }
            $arr = [
                'sm_id' => $this->uid,
                'sm_header_id' => $header,
                'title' => $data['title'],
                'content' => $data['content'],
                'create_time' => date('Y-m-d H:i:s',time()),
            ];
            $ret = Db::table('sm_feedback')->insert($arr);
            if ($ret){
                $this->result('',1,'问题反馈成功');
            }else{
                $this->result('',0,'问题反馈失败');
            }
        }else{
            $this->result('',0,'无权限');
        }
    }

    // 服务经理  提现 记录列表
    public function withdrawList()
    {
        $pageSize = 8;  // 每页显示条数
        $page = input('post.page')? : 1; // 页码
        $month = input('post.month')? : date('Y-m',time()); // 当前年-月份
        $count = Db::table('sm_apply_cash')
            ->where('sm_id',$this->uid)
            ->where('audit_status',1)
            ->where("DATE_FORMAT(create_time,'%Y-%m') = '$month'")
            ->count();
        $rows = ceil($count/$pageSize);
        $list = Db::table('sm_apply_cash')
            ->where('sm_id',$this->uid)
            ->where("DATE_FORMAT(create_time,'%Y-%m') = '$month'")
            ->where('audit_status',1)
            ->field('id,money,create_time,audit_status')
            ->page($page,$pageSize)
            ->select();
        if ($count > 0){
            $this->result(['list'=>$list,'rows'=>$rows],1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    // 服务经理  提现  详情
    public function withdrawDetail()
    {
        // 提现 ID
        $id = input('post.id');
        $data = Db::table('sm_apply_cash')
            ->alias('a')
            ->join('co_bank_code c','a.bank_code = c.code','LEFT')
            ->where('a.id',$id)
            ->field('a.money,a.create_time,a.odd_number,FROM_UNIXTIME(a.audit_time) as audit_times,a.trade_no,a.account_name,c.name')
            ->find();
        $info = $this->buyEpay->banklog($data['odd_number']);
        if ($info['result_code'] == 'SUCCESS'){
            $data['arrive_time'] = $info['pay_succ_time'];
        }else{
            $data['arrive_time'] = '';
        }
        unset($data['trade_no']);
        if ($data){
            $this->result($data,1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }


    //往期收入  开发奖励
    public function developmentList()
    {
        // 判断是服务经理还是运营总监
        $status = Db::table('sm_user')->where('id',$this->uid)->value('person_rank');
        if ($status == 1 || $status == 2){
            if ($status == 1){
                // 服务经理  判断是 业务合作 还是  加盟
                $sm_status = Db::table('sm_area')
                    ->where('sm_id',$this->uid)
                    ->where('sm_mold','<>',2)
                    ->where('sm_mold','<>',3)
                    ->where('audit_status',1)
                    ->where('sm_type',1)
                    ->value('sm_status');
                if (!empty($sm_status)){
                    if ($sm_status == 2){
                        //  加盟 状态
                        $page = input('post.page')? : 1; // 页码
                        $month = input('month')? : date('m',time()); // 默认当前月份
                        $person_rank = 1;
                        $if_finish = '0,1';
                        $this->developmentPremium($this->uid,$page,$month,$person_rank,$if_finish);
                    }else{
                        // 业务合作 状态
                        $page = input('post.page')? : 1; // 页码
                        $month = input('month')? : date('m',time()); // 默认当前月份
                        $person_rank = 1;
                        $if_finish = '1';
                        $this->developmentPremium($this->uid,$page,$month,$person_rank,$if_finish);
                    }
                }
            }else{
                // 身份 为  运营总监
                $page = input('post.page')? : 1; // 页码
                $month = input('month')? : date('m',time()); // 默认当前月份
                $person_rank = 2;
                $if_finish = '0,1';
                $this->developmentPremium($this->uid,$page,$month,$person_rank,$if_finish);
            }
        }else{
            $this->result('',0,'无权限');
        }
    }

    //服务经理 开发奖励  接口
    public function developmentPremium($uid,$page,$month,$person_rank,$if_finish)
    {
        $pageSize = 8;                         // 每页显示条数
        $count = Db::table('sm_income')
            ->alias('a')
            ->join('cs_shop b','a.sid = b.id','LEFT')
            ->join('ca_agent c','b.aid = c.aid','LEFT')
            ->where('a.sm_id',$uid)
            ->where('a.type',2)
            ->where('a.person_rank',$person_rank)
            ->where('a.if_finish','in',$if_finish)
            ->where("DATE_FORMAT(a.create_time,'%Y-%m') = '$month'")
            ->count();
        $rows = ceil($count / $pageSize);
        $list['data'] = Db::table('sm_income')
            ->alias('a')
            ->join('cs_shop b','a.sid = b.id','LEFT')
            ->join('ca_agent c','b.aid = c.aid','LEFT')
            ->where('a.sm_id',$uid)
            ->where('a.type',2)
            ->where('a.person_rank',$person_rank)
            ->where('a.if_finish','in',$if_finish)
            ->where("DATE_FORMAT(a.create_time,'%Y-%m') = '$month'")
            ->field('a.id,a.money,b.company,c.company as agent_company,a.create_time')
            ->order('a.create_time DESC')
            ->page($page,$pageSize)
            ->select();
        $list['total'] = 0;
        foreach ($list['data'] as $key=>$value){
            $list['total'] += $list['data'][$key]['money'];
        }
        if ($count > 0){
            $this->result(['list'=>$list,'rows'=>$rows],1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    //往期收入  管理奖励
    public function managementList()
    {
        // 判断是服务经理还是运营总监
        $status = Db::table('sm_user')->where('id',$this->uid)->value('person_rank');
        if ($status == 1 || $status == 2){
            if ($status == 1){
                $page = input('post.page')? : 1; // 页码
                $month = input('month')? : date('m',time()); // 默认当前月份
                $this->managementPremium($this->uid,$page,$month);
            }else{
                // 身份 为  运营总监
                $page = input('post.page')? : 1; // 页码
                $month = input('month')? : date('m',time()); // 默认当前月份
                $this->managementAward($this->uid,$page,$month);
            }
        }else{
            $this->result('',0,'无权限');
        }
    }

    //服务经理  管理奖励  接口
    public function managementPremium($uid,$page,$month)
    {
        $pageSize = 8;                         // 每页显示条数
        $count = Db::table('sm_income')
            ->where('sm_id',$uid)
            ->where('type',3)
            ->where('person_rank',1)
            ->where("DATE_FORMAT(create_time,'%Y-%m') = '$month'")
            ->count();
        $rows = ceil($count/$pageSize);
        $list['data'] = Db::table('sm_income')
            ->where('sm_id',$uid)
            ->where('type',3)
            ->where('person_rank',1)
            ->where("DATE_FORMAT(create_time,'%Y-%m') = '$month'")
            ->field('company,address,money')
            ->order('create_time DESC')
            ->page($page,$pageSize)
            ->select();
        $list['total'] = 0;
        foreach ($list['data'] as $key=>$value){
            $list['total'] += $list['data'][$key]['money'];
        }
        if ($count > 0){
            $this->result(['list'=>$list,'rows'=>$rows],1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    // 运营总监 管理奖励 接口
    public function managementAward($uid,$page,$month)
    {
        $pageSize = 8;                         // 每页显示条数
        $count = Db::table('sm_income')
            ->alias('a')
            ->join('sm_user b','a.uuid = b.id','LEFT')
            ->where('a.sm_id',$uid)
            ->where('a.type',3)
            ->where('a.person_rank',2)
            ->where("DATE_FORMAT(a.create_time,'%Y-%m') = '$month'")
            ->count();
        $rows = ceil($count/$pageSize);
        $list['data'] = Db::table('sm_income')
            ->alias('a')
            ->join('sm_user b','a.uuid = b.id','LEFT')
            ->where('a.sm_id',$uid)
            ->where('a.type',3)
            ->where('a.person_rank',2)
            ->where("DATE_FORMAT(a.create_time,'%Y-%m') = '$month'")
            ->field('a.id,a.uuid,sum(a.money) as money,a.address')
            ->order('a.create_time DESC')
            ->page($page,$pageSize)
            ->group('uuid')
            ->select();
        $list['total'] = 0;
        foreach ($list['data'] as $key=>$value){
            $list['data'][$key]['name'] = Db::table('sm_user')
                ->where('id',$list['data'][$key]['uuid'])
                ->value('name');
            $list['data'][$key]['head_pic'] = Db::table('sm_user')
                ->where('id',$list['data'][$key]['uuid'])
                ->value('head_pic');
            $list['total'] += $list['data'][$key]['money'];
            unset($list['data'][$key]['uuid']);
        }
        if ($count > 0){
            $this->result(['list'=>$list,'rows'=>$rows],1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    //往期收入   团队奖励
    public function teameList()
    {
        // 判断是服务经理还是运营总监
        $status = Db::table('sm_user')->where('id',$this->uid)->value('person_rank');
        if ($status == 1 || $status == 2){
            if ($status == 1){
                $page = input('post.page')? : 1; // 页码
                $month = input('month')? : date('m',time()); // 默认当前月份
                $person_rank = 1;
                $this->teamPremium($this->uid,$page,$month,$person_rank);
            }else{
                // 身份 为  运营总监
                $page = input('post.page')? : 1; // 页码
                $month = input('month')? : date('m',time()); // 默认当前月份
                $person_rank = 2;
                $this->teamAward($this->uid,$page,$month,$person_rank);
            }
        }else{
            $this->result('',0,'无权限');
        }
    }

    //服务经理 团队奖励  接口
    public function teamPremium($uid,$page,$month,$person_rank)
    {
        $pageSize = 8;                         // 每页显示条数
        $count = Db::table('sm_income')
            ->where('sm_id',$uid)
            ->where('type',1)
            ->where('person_rank',$person_rank)
            ->where("DATE_FORMAT(create_time,'%Y-%m') = '$month'")
            ->count();
        $rows = ceil($count/$pageSize);
        $list['data'] = Db::table('sm_income')
            ->where('sm_id',$uid)
            ->where('type',1)
            ->where('person_rank',$person_rank)
            ->where("DATE_FORMAT(create_time,'%Y-%m') = '$month'")
            ->field('id,company,address,sum(money) as money,create_time')
            ->order('create_time DESC')
            ->page($page,$pageSize)
            ->select();
        $list['total'] = 0;
        foreach ($list['data'] as $key=>$value){
            $list['total'] += $list['data'][$key]['money'];
        }
        if ($count > 0){
            $this->result(['list'=>$list,'rows'=>$rows],1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    //运营总监 团队奖励  接口
    public function teamAward($uid,$page,$month,$person_rank)
    {
        $pageSize = 8;                         // 每页显示条数
        $count = Db::table('sm_income')
            ->alias('a')
            ->join('sm_user b','a.uuid = b.id')
            ->where('a.sm_id',$uid)
            ->where('a.type',1)
            ->where('a.person_rank',$person_rank)
            ->where("DATE_FORMAT(a.create_time,'%Y-%m') = '$month'")
            ->group('uuid')
            ->count();
        $rows = ceil($count/$pageSize);
        $list['data'] = Db::table('sm_income')
            ->alias('a')
            ->join('sm_user b','a.uuid = b.id')
            ->where('a.sm_id',$uid)
            ->where('a.type',1)
            ->where('a.person_rank',$person_rank)
            ->where("DATE_FORMAT(a.create_time,'%Y-%m') = '$month'")
            ->field('a.id,a.address,sum(a.money) as money,a.create_time,b.name,b.head_pic')
            ->order('create_time DESC')
            ->group('uuid')
            ->page($page,$pageSize)
            ->select();
        $list['total'] = 0;
        foreach ($list['data'] as $key=>$value){
            $list['total'] += $list['data'][$key]['money'];
        }
        if ($count > 0){
            $this->result(['list'=>$list,'rows'=>$rows],1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    /*******************************************************************************************************/
  // 协议  接口
    public function protocol()
    {
        $status = input('post.sm_status');
        if ($status == 4){
            $con = Db::table('am_protocol')
                ->where('type',3)
                ->value('content');
            $con = str_replace('img src=&quot;/data/imgs/','img src="https://doc.ctbls.com/data/imgs/',$con);
            $content = htmlspecialchars_decode($con);
        }else{
            $con = Db::table('am_protocol')
                ->where('type',4)
                ->value('content');
            $con = str_replace('img src=&quot;/data/imgs/','img src="https://doc.ctbls.com/data/imgs/',$con);
            $content = htmlspecialchars_decode($con);
        }
        return $content;
    }
    /**
     * 期权奖励
     * @return [type] [description]
     */
    public function protocol_qy()
    {

        $con = Db::table('am_protocol')
            ->where('type',6)
            ->value('content');
        $con = str_replace('img src=&quot;/data/imgs/','img src="https://doc.ctbls.com/data/imgs/',$con);
        $content = htmlspecialchars_decode($con);
        return $content;
    }
    // 投诉列表
    public function complaint($sm_id)
    {
        // 投诉列表
        $list = Db::table('sm_complaint')
            ->where('sm_id','in',$sm_id)
            ->field('sm_id,id,name,create_time,uid,city_id,type')
            ->select();
//        var_dump($list);exit;
        if (!empty($list)){
            foreach ($list as $key=>$value){
                $list[$key]['area'] = Db::table('co_china_data')
                    ->where('id',$list[$key]['city_id'])
                    ->value('name');
                unset($list[$key]['city_id']);
                unset($list[$key]['type']);
                unset($list[$key]['uid']);
            }
            return $list;
//            $this->result($list,1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }
    // 数据
    public function dataLists()
    {
        $status = Db::table('sm_user')->where('id',$this->uid)->value('person_rank');
        if ($status == 1){
            $list = $this->complaint($this->uid);
            $this->result($list,1,'数据返回成功');
        }elseif ($status == 2){
            // 搜索 该 运营总监的运营区域
            $area = Db::table('sm_area')
                ->where('sm_id',$this->uid)
                ->value('area');
            // 查询 所有服务经理所属的省级ID
            $data = Db::table('sm_area')
                ->alias('sa')
                ->join('co_china_data cc','sa.area = cc.id')
                ->where('sa.sm_type',1)
                ->field('cc.pid,sa.sm_id')
                ->select();
            if (!empty($data)){
                foreach ($data as $key=>$value){
                    if ($area == $data[$key]['pid']){
                        $sm_id[] = $data[$key]['sm_id'];
                    }
                }
            }else{
                $sm_id = array();
            }
            $list = $this->complaint($sm_id);
            $this->result($list,1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    // ID
    public function pidData()
    {
        $status = Db::table('sm_user')->where('id',$this->uid)->value('person_rank');
        if ($status == 1){
            // 获取投诉的列表
            $list = $this->complaint($this->uid);

            // 获取违规处理的id 如果没有则直接查询投诉列表的第一条
            $id = input('post.id')? : $list[0]['id'];
            $sm_ids = input('post.sm_id')? : $this->uid;
            // return $list;
            $this->violationList($id,$sm_ids);
        }elseif ($status == 2){
            // 搜索 该 运营总监的运营区域
            $area = Db::table('sm_area')
                ->where('sm_id',$this->uid)
                ->value('area');
            // 查询 所有服务经理所属的省级ID
            $data = Db::table('sm_area')
                ->alias('sa')
                ->join('co_china_data cc','sa.area = cc.id')
                ->where('sa.sm_type',1)
                ->field('cc.pid,sa.sm_id')
                ->select();
            if (!empty($data)){
                foreach ($data as $key=>$value){
                    if ($area == $data[$key]['pid']){
                        $sm_id[] = $data[$key]['sm_id'];
                    }
                }
            }else{
                $sm_id = array();
            }
            $list = $this->complaint($sm_id);
            $id = input('post.id')? : $list[0]['id'];
            $sm_ids = input('post.sm_id')? : $list[0]['sm_id'];
            $this->violationList($id,$sm_ids);
        }else{
            $this->result('',0,'暂无数据');
        }
    }

//    // 违规处理
//    public function personnel($id)
//    {
//        $status = Db::table('sm_user')->where('id',$this->uid)->value('person_rank');
//        if ($status == 1){
//            $this->violationList($id,$this->uid);
//        }elseif ($status == 2){
//            $smid = Db::table('sm_team')->where('sm_header_id',$this->uid)->value('sm_member_id');
//            $sm_id = explode(',',$smid);
//            $this->violationList($id,$sm_id);
//        }else{
//            $this->result('',0,'暂无数据');
//        }
//    }

    //违规处理  接口
    public function violationList($id,$sm_id)
    {

        // 查询  服务经理的投诉记录
        $list = Db::table('sm_complaint')
            ->where('sm_id','in',$sm_id)
            ->where('id',$id)
            ->field('sm_id,id,create_time,FROM_UNIXTIME(handle_time) as times,type,uid')
            ->select();
        if (!empty($list)){
            foreach ($list as $key=>$value){
                if ($list[$key]['type'] == 1){   // 运营商
                    // 查询 该 运营商 下的维修厂
                    $shop = Db::table('cs_shop')->where('aid',$list[$key]['uid'])->column('id');
                    // 查询 该 运营商 下 的维修厂 的售卡数据 在 服务经理 被投诉后 撤回之前
                    if (!empty($shop)){
                        // 投诉身份 为 运营商
                        $this->dataList($list[$key]['uid'],$shop,$list[$key]['create_time'],$list[$key]['times'],1,$sm_id);
                    }else{
                        $this->result('',0,'暂无数据');
                    }
                }elseif ($list[$key]['type'] == 3){   // 维修厂
                    // 假如投诉身份为  维修厂
                    $this->dataList($list[$key]['uid'],$list[$key]['uid'],$list[$key]['create_time'],$list[$key]['times'],3,$sm_id);
                }
            }
        }else{
            $this->result('',0,'暂无数据');
        }
    }

    public function dataList($cid,$sid,$c_time,$h_time,$type,$sm_id)
    {
        $info = Db::table('u_card')
            ->alias('a')
            ->join('co_car_cate b','a.car_cate_id = b.id','LEFT')
            ->where('sid','in',$sid)
            ->whereBetweenTime('sale_time',$c_time,$h_time)
            ->field('a.plate,b.type,b.series,a.card_price,a.sale_time')
            ->select();
        if ($type == 1){
            // 查找 该运营商的 市级ID
            $countyId = Db::table('ca_area')
                ->alias('a')
                ->join('co_china_data b','a.area = b.id')
                ->where('a.aid',$cid)
                ->value('pid');
            $cityId = Db::table('co_china_data')->where('id',$countyId)->value('id');
        }elseif ($type == 3){
            // 查找 维修厂 的运营商 和 市级ID
            $aid = Db::table('cs_shop')->where('id',$sid)->value('aid');
            // 查找 该运营商的 市级ID
            $cityId = Db::table('ca_area')
                ->alias('a')
                ->join('co_china_data b','a.area = b.id')
                ->where('a.aid',$aid)
                ->value('pid');
        }
        // 查询 该 服务经理 在本区域(市级) 的 分佣
        $divide = Db::table('sm_area')
            ->where('sm_id',$sm_id)
            ->where('area',$cityId)
            ->value('sm_profit');
        foreach ($info as $key=>$value){
            $info[$key]['divide'] = $info[$key]['card_price'] * ($divide / 100);
            unset($info[$key]['card_price']);
            $info[$key]['violation'] = '0.00';
        }
        if ($info){
            $this->result($info,1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }
}