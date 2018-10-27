<?php 
namespace app\base\controller;
use app\base\controller\Base;
use think\Db;

class Sm extends Base
{
	public function initialize()
	{
		parent::initialize();
	}
	/**
	 * 身份判断 判断是不是运营总监
	 * @return [type] [description]
	 */
	public function judgePerson($uid)
	{
		$re = Db::table('sm_user')
					->where([
						'id' => $uid
					])
					->value('person_rank');
		if($re > 1){
			return 2;//是运营总监
		} else {
			return 1;//是服务经理
		}
	}
	/**
	 * 判断是否服务经理
	 * @return [type] [description]
	 */
	public function judgeSm($uid)
	{
		$re = Db::table('sm_area')
				->where([
					'sm_id'        => $uid,
					'pay_status'   => 1,
					'audit_status' => 1
				])
				->count();
		if($re > 0){
			return 1;//是服务经理
		} else {
			return 0;//游客
		}
	}
	/**
     * 上传图片
     * @param  [type] $image [description]
     * @param  [type] $path  [description]
     * @param  [type] $host  [description]
     * @return [type]        [description]
     */
    public function uploadImage($image,$path,$host)
    {
        // 获取表单上传文件
        $file = request()->file($image);
        // 进行验证并进行上传
        $info = $file->validate(['size'=>3145728,'ext'=>'jpg,png,jpeg'])->move( './uploads/worker/'.$path.'/');
        // 上传成功后输出信息
        if($info){
          $res = $host.'/uploads/worker/'.$path.'/'.$info->getSaveName();
          $res = stripcslashes($res);//替换反斜杠
          $this->result(['url'=>$res],1,'上传成功');
        }else{
          $this->result('',0,$file->getError());
        }
    }
    /**
     * 获取服务经理信息
     * @return [type] [description]
     */
   	public function getSmInfo($uid)
   	{
   		$info = Db::table('sm_user')
   				->where('id',$uid)
   				->select();
   		return $info;
   	}

    // 所有未选区域
    public function area()
    {
        //  所有已选并审核通过的区域
        $area = Db::table('sm_area')
            ->where('audit_status',1)
            ->where('sm_type',1)
            ->field('id,area')
            ->select();
        $allArea = Db::table('co_china_data')->select();
        foreach ($area as $key=>$value){
            foreach ($allArea as $k=>$v){
                if ($area[$key]['area'] == $allArea[$k]['id']){
                    unset($allArea[$k]);
                }
            }
        }
        $allArea = array_values($allArea);
        $this->result($allArea,1,'数据返回成功');

    }

    public function province($areaid)
    {
        // 查找县区级名称和市级ID
        $area = Db::table('co_china_data')->where('id',$areaid)->field('pid,name')->find();
        // 市级名称和省级ID
        $city = Db::table('co_china_data')->where('id',$area['pid'])->field('pid,name')->find();
        // 省级名称
        $province = Db::table('co_china_data')->where('id',$city['pid'])->value('name');
        // 拼接
        $name = $province.$city['name'].$area['name'];
        return $name;
    }

    public function provinces($areaid)
    {
        // 查找县区级名称和市级ID
        $area = Db::table('co_china_data')->where('id',$areaid)->field('pid,name')->find();
        // 市级名称和省级ID
        $city = Db::table('co_china_data')->where('id',$area['pid'])->value('name');
        // 拼接
        $name = $city.$area['name'];
        return $name;
    }

    // 省 名称
    public function provincenes($areaid)
    {
        // 查找省名称
        $name = Db::table('co_china_data')->where('id',$areaid)->value('name');
        return $name;
    }
}
