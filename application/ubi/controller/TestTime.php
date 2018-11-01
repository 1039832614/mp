<?php
namespace app\ubi\controller;
use app\base\controller\Ubi;
use think\Db;
use Epay\BbyEpay;
/**
* 定时退费及更新行驶里程  油耗
*/
class TestTime extends Ubi
{
	
	/**
	 * 调取里程接口查询每个设备当天所行驶里程
	 * @return [type] [description]
	 */
	public function inter()
	{

		//获取所有已绑定小程序的用户设备号
		$eq_num = $this->eqNum();
		// print_r($eq_num);exit;
		if (!$eq_num) {
			print_r($eq_num);exit;
		}

            
		$url2 = 'https://obd.ctbls.com/api/static_mileageAndoilReport';
		$data2['OBDIDs'] = $eq_num;
		// print_r($data2);exit;
		// $data2['OBDIDs'] = ['164875483570','164875483703'];
		// $data2['beginDate'] = date("Y-m-d",strtotime("-1 day"));
		// $data2['endDate'] = date("Y-m-d");
		$data2['beginDate'] = '2017-7-29';
		$data2['endDate'] = '2017-7-30';
		$data2 = json_encode($data2,JSON_UNESCAPED_UNICODE);
		$array = $this->km($url2,$data2);	
		$array = json_decode($array,true);
		// print_r($array);exit;
		// 
		if (!$array) {
			print_r($array);exit;
		}
		// print_r($array);die;
		$this->distance($array , date("Y-m-d",strtotime("-1 day")));
	}


	/**
	 *录入用户行车信息（凌晨12点01定时）
	 * @param  [type] $array [description]
	 * @return [type]        [description]
	 */
	public function distance($array,$create_time)
	{

		$arr = array();  
		//循环查看每个设备所对应的里程是否大于1公里 1km
		foreach ($array as $k => $v) {
			// print_r($v);
			// 根据设备号查询用户信息
			$userDetail = $this->userDetail($v['OBDID']);
			// print_r($userDetail);
			// 查询保单是否有大于前一天的日期 有的话则改为保单过期
			$date = Db::table('cb_policy_sheet')->where('u_id',$userDetail['u_id'])->where('end_time','<',date("Y-m-d",strtotime("-1 day")))->setField('status',2);
			// 根据用户id查询用户是否绑定保单
			$policy_status = Db::table('cb_policy_sheet')->where('u_id',$userDetail['u_id'])->field('status,total,policy_num,end_time')->find();

			if (!$policy_status) {
				
				// continue;
				// exit($userDetail['name'].'保单是空的');
			}
			// print_r($policy_status);
			// 如果polciy_status为空  赋值为4 录入退费表表示未录入保单
			if(empty($policy_status)){
				$policy_status['status'] = 4;
				$policy_status['total'] = 0;
			}

			// 判断设备号是否一致
			if($userDetail['eq_num'] == $v['OBDID']){
				// echo 1;exit;
				$data = [
					'u_id'	=> $userDetail['u_id'],
					'o_number'=> $userDetail['eq_num'],
					'plate'	=> $userDetail['plate'],
					'km'	=> $v['distance'],
					// 'km' => 0.99,
					'create_time'=>$create_time ,
					'oil_con' => $v['oilCount'],
					'oil_aver' => $v['oilCount'] / $v['distance'],
					'driv_hour'=>$v['oilFee'],
					'name'=>$userDetail['name'],
					'phone'=>$userDetail['phone'],
					// '' => Date('Y-m-d', strtotime('-1 day'))
					'u_status' => $policy_status['status'],
					// 'refund_price' => $policy_status['total']*(1/365),
					'refund_price' => 1,
					'policy_num'=>$policy_status['policy_num'],
					'end_time'=>$policy_status['end_time'],
				];
			}else{
				$data=[];
			}
			$arr[] = $data;
		}
		// exit;
		Db::table('cb_refund')->insertAll($arr);
	}


	/**
	 * 每天早上8点退前一天的费用（每天早上8点定时）
	 * @return [type] [description]
	 */
	public function refundPost()
	{
		$create_time = date("Y-m-d",strtotime("-1 day"));
		// 获取所有大于一公里的信息 且保单未过期已录入
		$list = Db::table('cb_refund br')
				->join('cb_user bu','br.u_id = bu.u_id')
				->where(['refund_status'=>0,'br.create_time'=>$create_time,'br.u_status'=>1])
				->where('br.km','<',1)
				->field('bu.open_id,br.r_id,refund_price,bu.name,br.policy_num,o_number,end_time')
				->select();
       // echo $create_time;die;
		$Epay = new BbyEpay;
		if(empty($list)){
			// 写日志
			$GLOBALS['err'] ='【'.date("Y-m-d",strtotime("-1 day")).'】没有可退费的用户！' ;
			$GLOBALS['r_id'] = 0;
			$GLOBALS['trade_no'] = 0;
			$this->estruct();
			exit;
		}

		foreach ($list as $k => $v) {
			$trade_no = build_only_sn();
			$a = $Epay->car_dibs($trade_no,$v['open_id'],$v['refund_price']*100,'保险退费');
			// $a = $Epay->car_dibs($trade_no,$v['open_id'],1.11*100,'保险退费');
			if($a){
				// 修改退款状态为已退款
				$res = Db::table('cb_refund')->where('r_id',$v['r_id'])->setField('refund_status',1);
				// 在
				if($res !== false){

					// 写日志
					$GLOBALS['err'] = '【'.date("Y-m-d",strtotime("-1 day")).'】定时给姓名为:【'.$v['name'].'】,保单号为：【'.$v['policy_num'].'】,且保单过期时间为【'.$v['end_time'].'】设备号为【'.$v['policy_num'].'】的用户退费【'.$v['refund_price'].'】元！';
					$GLOBALS['r_id'] = $v['r_id'];
					$GLOBALS['trade_no'] = $trade_no;
					$this->estruct();
				}else{
					// 写日志
					$GLOBALS['err'] ='【'.date("Y-m-d",strtotime("-1 day")).'】定时退费执行到【'.$v['name'].'】处,出现异常!';
					$GLOBALS['r_id'] = $v['r_id'];
					$GLOBALS['trade_no'] = $trade_no;
					$this->estruct();
					exit;
				}
			}else{
				// 写日志
				$GLOBALS['err'] ='【'.date("Y-m-d",strtotime("-1 day")).'】定时退费执行到【'.$v['name'].'】处,出现'.$a.'异常!';
				$GLOBALS['r_id'] = $v['r_id'];
				$GLOBALS['trade_no'] = $trade_no;
				$this->estruct();
				exit;
			}
		}


	}


	public function aa()
	{
		$Epay = new BbyEpay;
		$a = $Epay->car_log('599111426927');
		print_r($a);exit;
	}


	/**
	 * 记录退款日志
	 * @return [type] [description]
	 */
	public function estruct(){
        // echo 1;exit;
        // print_r($GLOBALS);exit;
        if(isset($GLOBALS['err']) && isset($GLOBALS['r_id']) && isset($GLOBALS['trade_no'])){
        	$data = [
        		'content'=>$GLOBALS['err'],
        		'r_id'=>$GLOBALS['r_id'],
        		'odd_number'=>$GLOBALS['trade_no'],

        	];
            Db::table('cb_policy_log')->insert($data);
        }

    } 


	/**
	 * 根据设备号  及 是否有可以使用的保单 获取用户信息
	 * @param  [type] $obd [description]
	 * @return [type]      [description]
	 */
	public function userDetail($obd)
	{
		return Db::table('cb_user')->where(['eq_num'=>$obd])->field('u_id,plate,eq_num,open_id,name,phone,u_status')->find(); 
	}



	/**
	 * 获取所有用户的设备号
	 * @return [type] [description]
	 */
	private function eqNum()
	{
		// 获取所有用户的OBD设备号
		return Db::table('cb_user')->where('status', 1)->where('eq_num','<>',0)->column('eq_num');
	}





	public function km($url,$data='')
	{
		$header = array("Content-type: application/json");// 注意header头，格式k:v
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);// curl函数执行的超时时间（包括连接到返回结束） 秒单位
		// curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);// 连接上的时长 秒单位
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);              // 从证书中检查SSL加密算法是否存在
    	// curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		$ret = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);// 对方服务器返回http code
		curl_close($ch);
		// print_r($ret);die;
		return $ret;
	}



}