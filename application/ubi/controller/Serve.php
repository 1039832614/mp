<?php 
namespace app\ubi\controller;
use app\base\controller\Ubi;
use think\Db;

class Serve extends Ubi
{

    function initialize(){
        parent::initialize();
        $this->uid = input('post.uid');
    }

    /**
    *就近服务 维修厂
    */
    public function service()
    {
        $uid = input('post.uid');            //  用户的ID
        $info = Db::table('cb_user')->field('lat,lng')->where(array('u_id'=>$uid))->find();          //  搜索用户的 经纬度
        $pageSize = 8;
        $page = input('post.page')? : 1;
        $count = Db::table('cs_shop')
            ->alias('a')
            ->join('cs_shop_set b','a.id = b.sid','LEFT')
            ->where(array('audit_status'=>2))
            ->count();
        $rows = ceil($count / $pageSize);
        //  维修厂ID  维修厂名称   维修厂详情ID  维修厂描述   服务次数     经度  纬度  图片
        $list = Db::table('cs_shop')
            ->alias('a')
            ->join('cs_shop_set b','a.id = b.sid','LEFT')
            ->field('a.id,a.company,b.id as bid,b.photo,b.about,service_num,b.lat,b.lng')
            ->where(array('audit_status'=>2))                 //  已审核
            ->page($page,$pageSize)
            ->select();
        foreach ($list as $key=>$value){
            $list[$key]['photo'] = json_decode(str_replace(['\\'], ["/"], $list[$key]['photo']));
            //  计算维修厂与用户之间的距离  (四舍五入取整)
            $list[$key]['juli'] =  round($this->getDistance($info['lat'],$info['lng'],$list[$key]['lat'],$list[$key]['lng']));
            //维修厂热度(关注度) 暂以在本维修厂购买邦保养卡为计算方式
            $list[$key]['careness'] =  Db::table('u_card')->where('sid',$list[$key]['id'])->count();
            unset($list[$key]['lat']);
            unset($list[$key]['lng']);
        }
        $count = count($list);
        //把距离最小的放到前面
        //双重for循环, 每循环一次都会把一个最大值放最后
        for ($i = 0; $i < $count - 1; $i++)
        {
            //由于每次比较都会把一个最大值放最后, 所以可以每次循环时, 少比较一次
            for ($j = 0; $j < $count - 1 -  $i; $j++)
            {
                if ($list[$j]['juli'] > $list[$j + 1]['juli'])
                {
                    $tmp = $list[$j];
                    $list[$j] = $list[$j + 1];
                    $list[$j + 1] = $tmp;
                }
            }
        }
        if ($count > 0){
            $this->result(['list'=>$list,'rows'=>$rows],1,'获取列表成功');
        }else{
            $this->result('',0,'暂无更多数据');
        }
    }


    /**
     * 查看服务店铺详情
     */
    public function serveDetail()
    {
        $sid = input('post.sid');   //维修厂的ID
        $uid = input('post.uid');   // 用户ID
        $uinfo = Db::table('cb_user')->field('lat,lng')->where('u_id',$uid)->find();
        //  维修厂名称   图片 经度  纬度    店铺电话    店铺描述  省市区   详细地址
        $list = Db::table('cs_shop')
            ->alias('a')
            ->join('cs_shop_set b','a.id = b.sid','LEFT')
            ->where('a.id',$sid)
            ->field('a.id,a.company,a.service_num,photo,about,province,city,county,address,serphone,lat,lng')
            ->find();
        $list['photo'] = json_decode(str_replace(['\\'], ["/"], $list['photo']));
        //  与用户距离(取整)
        $list['distance'] = floor($this->getDistance($uinfo['lat'],$uinfo['lng'],$list['lat'],$list['lng']));
        //维修厂热度(关注度) 暂以在本维修厂购买邦保养卡为计算方式
        $list['careness'] =  Db::table('u_card')->where('sid',$list['id'])->count();
        unset($list['lat']);
        unset($list['lng']);
        //   该维修厂下 所有技师总数
        $list['count'] = Db::table('tn_user')->where('sid',$sid)->where('cert',1)->count();
        //  该维修厂下的所有技师   头像  姓名  从业时间
        $list['engineer'] = Db::table('tn_user')
                    ->field('wx_head,name,server')
                    ->where('sid',$sid)
                    ->where('cert',1)    //  已认证
                    ->select();
        //  技师服务平均分
        $tn_star = Db::table('u_comment')
                    ->where('sid',$sid)
                    ->avg('tn_star');
        $list['tn_star'] = intval($tn_star);
        //  维修厂平均分
        $shop_star = Db::table('u_comment')
                    ->where('sid',$sid)
                    ->avg('shop_star');
        $list['shop_star'] = intval($shop_star);
        // 总体平均分
        $list['comment'] = intval(round(($tn_star+$shop_star)/2,1));
        //   用户手机号   店铺星级评价    评价内容    评价时间
        $list['commentList'] = Db::table('u_comment')
            ->alias('a')
            ->join('u_user b','a.uid = b.id','LEFT')
            ->field('b.head_pic,b.phone,a.shop_star,a.content,a.create_time')
            ->where('sid',$sid)
            ->select();
        foreach ($list['commentList'] as $key=>$value){
            $list['commentList'][$key]['phone'] = substr_replace($list['commentList'][$key]['phone'],'****',3,4);
        }

        if ($list){
            $this->result($list,1,'数据返回成功');
        }else{
            $this->result('',0,'暂无数据');
        }
    }
}