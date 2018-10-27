<?php 
namespace app\sm\controller;
use app\base\controller\Sm;
use think\Db;

/**
 * 运营总监取消合作
 */
class Cancel extends Sm
{
	/**
	 * 初始化方法
	 * @return [type] [description]
	 */
	public function initialize()
	{
		$this->uid = input('post.uid');
	}
	/**
	 * 获取运营总监的区域
	 * @return [type] [description]
	 */
	public function getArea()
	{
		// return 1;die();
		//查看当前运营总监是否提交过取消合作的记录且没有审核的
		$count = Db::table('sm_apply_cancel')
				 ->where('sm_id',$this->uid)
				 ->where('status',0)
				 ->count();
		if($count < 1) {
			$area = Db::table('sm_area')
				->alias('a')
				->join('co_china_data d','a.area = d.id')
				->where([
					'audit_status' => 1,
					'sm_id' => $this->uid
				])
				->where('sm_mold','<>',2)
				->value('name');
			if($area) {
				$detail = '您确定取消合作（'.$area.'）？';
				$this->result(['detail'=>$detail],1,'获取成功');
			} else {
				$this->result('',0,'暂无区域');
			}
		} else {
			$this->result('',0,'您的申请已提交，等待审核');
		}
		
	}
	/**
	 * 
	 * 取消合作
	 * @return [type] [description]
	 */
	public function cancel()
	{
		$data = input('post.');
		$sid = Db::table('sm_area')
				->alias('a')
				->join('co_china_data d','a.area = d.id')
				->where([
					'audit_status' => 1,
					'sm_id' => $this->uid
				])
				->order('a.id desc')
				->limit(1)
				->where('sm_mold','<>',2)
				->value('a.id');
		// return $sid;die();
		if($data['cancel_reason'] == '' || $data['cancel_reason'] == null) {
			$this->result('',0,'请输入取消理由');
		}
		$arr = [
			'sid' => $sid,
			'cancel_reason' => $data['cancel_reason'],
			'sm_id' => $this->uid,
			'create_time'=>date('Y-m-d H:i:s',time())
		];
		Db::startTrans();
        $ret = Db::table('sm_apply_cancel')->insert($arr);
        $ree = Db::table('sm_area')
        		->where('id',$sid)
        		->update(['sm_mold'=>3]);
        if ($ret && $ree !== false){
            DB::commit();
            $this->result('',1,'取消合作提交成功');
        }else{
            Db::rollback();
            $this->result('',0,'取消合作提交失败');
        }
	}
}