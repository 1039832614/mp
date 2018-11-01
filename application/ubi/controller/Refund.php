<?php
namespace app\ubi\controller;
use app\base\controller\Ubi;
use think\Db;
/**
* UBI退费(后修改为上传保单，退费进行定时)
*/
class Refund extends Ubi
{
	
	/**
	 * 上传及更新保单
	 * @return [type] [description]
	 */
	public function policyUpload()
	{
		//获取 保险公司名称company  选择险种type 保单金额polic_price、保单开始时间 start_time 保单保单结束时间 end_time 保单照片 img(数组)  用户id u_id  更改的保单id  pid
		$data = input('post.');
		$data['name_price'] = json_encode($data['name_price'],JSON_UNESCAPED_UNICODE);
		$data['pc_img'] = json_encode($data['pc_img']);
		unset($data['CarownerData']);
		$validate = validate('Policy');

		if($validate->check($data)){
			if($data['end_time'] < date('Y-m-d')) $this->result('',0,'不能上传过期保单');

			if(empty($data['pid'])){
				// print_r($data);exit;
				// 判断用户是否已录入过保单
				$count = Db::table('cb_policy_sheet')->where('u_id',$data['u_id'])->where('status = 0 or status = 1')->count();
				if($count > 0) $this->result('',0,'您已录入过表单');
				$res = Db::table('cb_policy_sheet')->json(['img'])->insert($data);
				if($res) $this->result('',1,'上传保单成功');
				$this->result('',0,'系统错误,上传失败');

			}else{
				$data['status'] = 0;
				$res = Db::table('cb_policy_sheet')->where('pid',$data['pid'])->update($data);
				if($res !== false) $this->result('',1,'修改成功');
				$this->result('',0,'系统错误,请重新修改');

			}

		}else{
			$this->result('',0,$validate->getError());
		}
		
	}


	/**
	 * UBI图片上传
	 * @return [type] [description]
	 */
	public function cbImg()
	{
		return upload('pc_img','ubi','https://cc.ctbls.com');
	}


	/**
	 * 保险公司列表
	 * @return [type] [description]
	 */
	public function policy()
	{
		$data = Db::table('am_policy')->select();
		if($data){
			$this->result($data,1,'获取保险公司列表成功');
		}else{
			$this->result('',0,'暂无数据');
		}
	}



	/**
	 * 险种列表
	 * @return [type] [description]
	 */
	public function policyType()
	{
		$list = Db::table('am_insure_type')->select();
		if($list){
			$this->result($list,1,'获取列表成功');
		}else{
			$this->result('',0,'暂无数据');
		}
	}


	/**
	 * 车主信息（完善信息后获取到）
	 * @return [type] [description]
	 */
	public function plateDetail()
	{
		$uid = input('get.uid');
		if(empty($uid)) $this->result('',0,'缺少uid参数');
		$list = Db::table('cb_user bu')
				->join('co_car_cate cc','bu.car_cate_id = cc.id')
				->join('co_car_menu bd','cc.brand = bd.id')
				->where('u_id',$uid)
				->field('u_id,bu.name,phone,bd.name as car_name,cc.type,cc.series,bu.plate')
				->find();
		if($list){
			$this->result($list,1,'获取列表成功');
		}else{
			$this->result('',0,'您还未完善信息');
		}
	}


	/**
	 * 已上传保单信息
	 * @return [type] [description]
	 */
	public function policyList()
	{
		//获取用户id
		$uid = input('post.uid');
		if(empty($uid)) $this->result('',0,'缺少uid参数');
		//查询用户是否是会员
		$member = Db::table('u_member_table')->where(['uid'=>$uid,'pay_status'=>1])->where('end_time','>',date('Y-m-d H:i:s'))->count();

		// 根据用户id查询该用户是否上传保单
		$list = Db::table('cb_policy_sheet')->where(['u_id'=>$uid])->order('pid desc')->find();
		
		// 获取用户的obdid
		$obdid = Db::table('cb_user')->where(['u_id'=>$uid])->value('eq_num');
		// print_r($obdid);exit;

		if($list){
			if($member < 0){
				$list['mem_status'] = 0;
			}else{
				$list['mem_status'] = 1;
			}

			if(!$obdid){
				//没有绑定设备 
				$list['obd_status'] = 0;
			}else{
				$o_status = $this->go($obdid);
				if($o_status == 1){
					// 没有运行
					$list['obd_status'] = 2;
				}else{
					//运行中
					$list['obd_status'] = 1;
				}

			} 
			// 退费总额
			$refund_total = Db::table('cb_user')->where('u_id',$uid)->value('refund_total');
			// 获取退费详情
			$refund = Db::table('cb_refund')->where(['u_id'=>$uid,'refund_status'=>1])->field('create_time,km,refund_price')->select();
			$list['refund'] = $refund;
			$list['refund_total'] = $refund_total;
			// $list['start_time'] = Date('Y-m-d' , strtotime($list['start_time']) ) ;

			// $list['end_time'] = Date('Y-m-d' , strtotime($list['end_time']) ) ;
			
			$this->result($list,1,'获取保单信息成功');
		}else{
			$this->result('',0,'暂未上传保单');
		}

	}


	public function go($obdid)
	{
		// 判断设备是否激活或运行
		$url2 = 'https://obd.ctbls.com/api/BlackList?json=true&OBDID='.$obdid;
		return getcurl($url2);
		

	}


}
