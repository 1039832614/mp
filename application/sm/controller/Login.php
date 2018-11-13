<?php 
namespace app\sm\controller;
use app\base\controller\Sm;
use think\Db;
use Msg\Sms;
use think\Controller;
use WxJ\WxJ;
use Pay\Wx;
use Epay\SmEpay;
/**
 * 服务经理登录注册
 */
class Login extends Sm
{
	public function initialize()
	{
		$this->sms = new Sms();
		$this->wx = new Wx();
		$this->appid = 'wxffaa414216ca4092';
		$this->secret = 'b366e20ca180b3ce59af61348648a9ca';
	}
	public function login()
	{
		$data  = input('get.');
		$safe  = $this->getOpenId($data['code']);
		$wxj   = new Wxj;
		$info  = $wxj->decryptData($safe['session_key'],$this->appid,$data['encryptedData'],$data['iv']);
		$us    = Db::table('sm_user')
				->field('id,person_rank,share_id')
				->where('open_id',$safe['openid'])
				->find();
		//构建入库数据
		$arr = [
			'open_id'   => $safe['openid'],
			'head_pic'  => $info['avatarUrl'],
 			'nick_name' => $info['nickName'],
			'sex'       => $info['gender']
		];
		//判断用户是否存在
		if($us) {
			if($arr['open_id'] !== null && $arr['head_pic'] !== null && $arr['nick_name'] !== null && $arr['sex'] !== null){
					Db::table('sm_user')
						->where('open_id',$safe['openid'])
						->update($arr);
			}
			$uid = $us['id'];
			// 给前端传分享者id
			$sm_status =[
				'person_rank' => $us['person_rank'],
				'share_id'=>$us['share_id'],
			];
		} else {
			$uid = Db::table('sm_user')->insertGetId($arr);
			$sm_status = Db::table('sm_user')
							->where('id',$uid)
							->field('person_rank,share_id')
							->find();
		}
		$this->result(['uid'=>$uid,'openid'=>$safe['openid'],'sm_status'=>$sm_status['person_rank'],'share_id'=>$sm_status['share_id']],1,'登录成功');
	}
	/**
	 * 获取用户的openid和sessionkey
	 * @param  [type] $code [微信小程序临时认证code]
	 * @return [type]       [description]
	 */
	function getOpenId($code){
		$appid  = $this->appid;
		$secret = $this->secret;
		$url    = "https://api.weixin.qq.com/sns/jscode2session?appid=$appid&secret=$secret&js_code=$code&grant_type=authorization_code";
        $curl   = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($curl);
        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) == '200') {
            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $body = substr($response, $headerSize);
        }
        return json_decode($body,true);
	}
	/**
	 * 获取手机验证码
	 * @return [type] [description]
	 */
	public function getCode()
	{
		$phone   = input('post.phone');
		$code    = $this->apiVerify();
		$content = "您的验证码是：【".$code."】。请不要把验证码泄露给其他人。";
		$a       = $this->smsVerify($phone,$content,$code);
		if($a == '提交成功'){
			$this->result('',1,'发送成功');
		} else {
			$this->result('',0,'该手机号今日获取验证码次数已达上限');
		}
	}
	/**
     * 生成API验证码
     */
    public function apiVerify()
    {
        return mt_rand(1000,9999);
    }
    /**
     * 手机发送验证码
     * @param  [type] $phone   [手机号]
     * @param  [type] $content [短信发送内容]
     * @return [type]          [发送成功或失败]
     */
    public function smsVerify($phone,$content,$code = '')
    {
        return $this->sms->send_code($phone,$content,$code);
    }
	/**
	 * 更新手机号
	 * @return [type] [description]
	 */
	public function updatePhone()
	{
		$data = input('post.');
		$validate = validate('Phone');
		if($validate->check($data)){
			// $check = $this->sms->compare($data['phone'],$data['code']);
			// if($check){
				$res = Db::table('sm_user')
						->where('id',$data['uid'])
						->update(['phone' => $data['phone']]);
				if($res !== false) {
					$this->result('',1,'已更新');
				} else {
					$this->result('',0,'更新失败');
				}
			// } else {
			// 	$this->result('',0,'手机验证码无效或已过期');
			// }
		} else {
			$this->result('',0,$validate->getError());
		}
	}
	/**
	 * 上传支付凭证
	 * @return [type] [description]
	 */
	public function uploadVoucher()
	{	
		return $this->uploadImage('image','voucher','https://xmp.ctbls.com');
	}
	/**
	 * 完善信息
	 * @return [type] [description]
	 */
	public function updateInfo()
	{
		//获取前端提交过来的数据
		$data = input('post.');
		//如果当前用户是加盟状态，则必须选择区域
		if($data['joinStatus'] == 1) {
			if(empty($data['area'])){
				$this->result('',0,'请选择区域');
			}
			if(empty($data['voucher'])){
				$this->result('',0,'请上传支付凭证');
			}
		}
		//实例化验证
		$validate = validate('Reg');
		//进行验证
		if($validate->check($data)){
			//验证成功,开启事务操作
			Db::startTrans();
			//构建用户信息
			$arr = [
				'bank_code'   => $data['bank_code'],   //开户行编码
				'bank_branch' => $data['bank_branch'], //开户分行
				'bank_name'   => $data['bank_name'],   //开户名
				'name'        => $data['name'],        //姓名
				'account'     => $data['account'], 	   //银行卡号
				'reg_time'    => time()                //当前时间
			];
			//更新用户信息表中的数据
			$res = Db::table('sm_user')
						->where('id',$data['uid'])
						->update($arr);
			//判断当前用户注册的是服务经理还是运营总监
			if($data['sm_status'] == 4) {
				//给入库数据身份值和分佣比例
				$sm_type = 1;
				$data['sm_profit'] = Db::table('am_sm_set')
										->where('status',1)
										->value('maid');
			} else {
				$sm_type = 2;
				$data['sm_profit'] = Db::table('am_sm_set')
										->where('status',2)
										->value('maid');
			}
			// 生成系统订单号
			$data['trade_no'] = $this->wx->createOrder();
			if($data['joinStatus'] == 1) {
				//构建区域入库信息
				$area = [
					'area'      => $data['area'],        //地区id
					'trade_no'  => $data['trade_no'],    //订单编号
					'sm_id'     => $data['uid'],         //服务经理或运营总监id
					'sm_type'   => $sm_type,             //身份
					'share_id'  => $data['share_id'],    //分享者id 
					'sm_profit' => $data['sm_profit'],   //售卡分佣
					'money'     => $data['money'],       //端口费金额
					'voucher'   => $data['voucher']      //支付凭证
				];
				//向表中插入数据并获取其id
				$lastId = Db::table('sm_area')
							->insertGetId($area);
				$openid = $data['openid']; 
				if($res !== false && $lastId){
					//事务提交
					Db::commit();                                
					$result             = $this->weixinapp($lastId,$openid);
					$result['trade_no'] = $data['trade_no'];
					$result['cid']      = $lastId;
					$this->result($result,1,'注册成功,请支付系统使用费');
				} else {
					//事务回滚 
					Db::rollback();
					$this->result('',0,'发起支付异常');
				}
			}
			//如果是合作状态的
			if($data['joinStatus'] == 0) {
				//去更新表中数据，身份以及合作方式
				$up = Db::table('sm_user')
						->where('id',$data['uid'])
						->update(['person_rank'=>1,'joinStatus'=>0]);
				if($res !== false && $up){
					Db::commit();
					$this->result('',1,'注册成功');
				} else {
					Db::rollback();
					$this->result('',0,'注册失败');
				}
			}
		} else {
			$this->result('',0,$validate->getError());
		}
	}
	/**
     * 微信小程序接口
     */
    public function weixinapp($lastId,$openid) {  
        //统一下单接口  
        $unifiedorder = $this->unifiedorder($lastId,$openid); 
        $parameters = array(  
            'appId'     => 'wxffaa414216ca4092',                      //小程序ID  
            'timeStamp' => ''.time(),                                 //时间戳  
            'nonceStr'  => $this->wx->getNonceStr(),                  //随机串  
            'package'   => 'prepay_id=' . $unifiedorder['prepay_id'], //数据包  
            'signType'  => 'MD5'                                      //签名方式  
        );  
        //签名
        $parameters['paySign'] = $this->wx->getSign($parameters);  
        return $parameters;  
    } 
    //统一下单接口  
    private function unifiedorder($lastId,$openid) {  
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';  
        // 进行数据查询
        $data = Db::table('sm_area')
        		->field('money,trade_no,id')
        		->where('id',$lastId)
        		->find();
        $parameters = array(  
            'appid'            => 'wxffaa414216ca4092',
            'mch_id'           => '1480664422',
            'nonce_str'        => $this->wx->getNonceStr(), 
            'body'             => '端口费',  
            'total_fee'        => $data['money']*100, 
            'openid'           => $openid,
            'out_trade_no'     => $data['trade_no'], 
            'spbill_create_ip' => '127.0.0.1', 
            'notify_url'       => 'https://xmp.ctbls.com/sm/Login/notify', 
            'trade_type'       => 'JSAPI',
            'attach'           => 'cid='.$lastId.'&total='.$data['money']
        );  
        //统一下单签名  
        $parameters['sign'] = $this->wx->getSign($parameters);  
        $xmlData = $this->wx->arrayToXml($parameters);  
        $return = $this->wx->xmlToArray($this->wx->postXmlCurl($xmlData, $url, 60)); 
        return $return;  
    }  
      /**
	 * 微信支付回调
	 */
	public function notify(){
		
		$xml =  file_get_contents("php://input");
		$data = $this->wx->xmlToArray($xml);
		$data_sign = $data['sign'];
		unset($data['sign']);
		$sign = $this->wx->getSign($data);
			// 判断签名是否正确  判断支付状态
			if (($sign === $data_sign) && ($data['return_code']=='SUCCESS') && ($data['result_code']=='SUCCESS')){
					$result = $data;
					//给attach赋值
					$attach = $this->wx->getStrVal($data['attach']);
					// 更新区域支付状态和时间
					Db::table('sm_area')
					->where('id',$attach['cid'])
					->update([
						'transaction_id' => $result['transaction_id'],
						'pay_status'     => 1,
						'pay_time'       => time()
					]);
					//获取用户id和身份类型
					$info = Db::table('sm_area')
							->where('id',$attach['cid'])
							->field('sm_id,sm_type')
							->find();
					//获取用户已付款的区域
					$count = Db::table('sm_area')
							 ->where([
							 	'id' => $info['sm_id'],
							 	'pay_status' => 1
							 ])
							 ->count();
					//如果用户是首次注册付款，则更改用户身份信息
					 if($count <= 1) {
						if($info['sm_type'] == 2){
							Db::table('sm_user')
								->where('id',$info['sm_id'])
								->update(['person_rank'=>5]);
						} else {
							Db::table('sm_user')
								->where('id',$info['sm_id'])
								->update(['person_rank'=>4]);
						}
					}
					// 返回状态给微信服务器
					echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
					return $result;
				}else{
					$result = false;
				}
			// 返回状态给微信服务器
			echo ($result) ? $this->wx->returnWxXml(1) : $this->wx->returnWxXml(0);
			return $result;
	
	}
	/**
	 * 获取用户信息
	 * @return [type] [description]
	 */
	public function getUserInfo()
	{
		$data = input('post.');
		//查询用户信息
		$info = Db::table('sm_user')
					->where([
						'id' => $data['uid']
					])
					->find();
		// 获取用户的开发奖励余额(即可提现余额)总数
		$income = Db::table('sm_income')
					->where([
						'sm_id'=>$data['uid'],
						'type'=>2,
						'if_finish'=>1,
						'cash_status'=>[0,3]
					])
					->sum('money');
		$info['balance'] = $income;
		if($info) {
			$this->result($info,1,'获取成功');
		} else {
			$this->result('',0,'获取失败');
		}
	}
	/**
	 * 获取银行列表
	 * @return [type] [description]
	 */
	public function getBank()
	{
        return Db::table('co_bank_code')->select();
	}
	/**
	 * 查看用户注册选择区域的审核状态
	 * @return [type] [description]
	 */
	public function getAreaMsg()
	{
		$data = input('post.uid');
		$msg = Db::table('sm_area')
				->where([
					'if_read' => 0,
					'sm_id' => $data['uid']
				])
				->where('sm_mold','<>',2)
				->field('id,audit_status')
				->order('id desc')
				->limit(1)
				->find();
		if($msg) {
			$this->result($msg,1,'获取成功');
		} else {
			$this->result('',0,'暂无');
		}
	}
	/**
	 * 获取正在审核的地区名
	 * @return [type] [description]
	 */
	public function getUnAuditArea()
	{
		$uid = input('post.uid');
		$area = Db::table('sm_area')
					->alias('a')
					->join('co_china_data d','d.id = a.area')
					->where([
						'a.audit_status' => 0,
						'a.sm_id' => $uid
					])
					->order('a.id desc')
					->limit(1)
					->value('d.name');
		if($area) {
			$this->result($area,1,'获取成功');
		} else {
			$this->result('',0,'暂无');
		}
	}
	/**
	 * 判断该用户最后一个支付的区域是否被驳回
	 * @return [type] [description]
	 */
	public function getIfPay(){
		$data = input('post.');
		$info = Db::table('sm_area')
				->where([
					'sm_id' => $data['uid'],
					'audit_status' => 2,
					'is_exits' => 1
				])
				->count();
		$person_rank = Db::table('sm_user')
						->where('id',$data['uid'])
						->value('person_rank');
		if($data['sm_status'] == 1 || $data['sm_status'] == 4) {
			// $money = 6800;
			$money   = 0.01;
			$money_s = 30000;
			$detail  = '线上支付'.$money.'元后，线下需交易'.$money_s.'元';
		} else {
			// $money = 6800;
			$money   = 0.01;
			$money_s = 300000;
			$detail  = '线上支付'.$money.'元后，线下需交易'.$money_s.'元';
		}
		if($info > 0) {
			$this->result(['money'=>$money,'detail'=>$detail],1,'已支付：'.$money.'元');
		} else {
			$this->result(['money'=>$money,'detail'=>$detail],0,'端口费:'.$money.'元');
		}
	}
	/**
	 * 判断用户注册是否驳回
	 * @return [type] [description]
	 */
	public function getIfAudit()
	{
		$uid = input('post.uid');
		//查看当前用户地区表中审核状态不为0 的第一条数据
		$list = Db::table('sm_area')
		            ->where('sm_id',$uid)
		            ->where('audit_status','<>',0)
		            ->field('id,audit_status,if_read')
		            ->limit(1,1)
		            ->find();
		//查看当前消息用户是否已读
        if($list['if_read'] == 0) {
        	//查看该区域是否是用户取消合作的区域，如果是用户主动取消合作的,则不在注册弹窗中出现
	        $count = Db::table('sm_apply_cancel')
	        			->where('sid',$list['id'])
	        			->count();
	        if($count > 0) {
	        	$this->result('',0,'暂无数据');
	        } else {
	        	//判断当前用户的身份
	        	$person_rank = Db::table('sm_user')
	        				->where('id',$uid)
	        				->value('person_rank');
		        if($person_rank == 5 || $person_rank ==2) {
		       		//如果是运营总监的，被驳回了
		        	if($list['audit_status'] == 2) {
		        		$msg = '您的注册申请已被驳回，请在“我的->我的区域”中重新修改';
		        	} else {
		        		$msg = '您的区域申请已通过';
		        	}
		        	
		        } else {
		        	//如果是服务经理的，被驳回了
		        	if($list['audit_status'] == 2) {
		        		$msg = '您的申请被驳回了，请重新提交';
		        	} else {
		        		$msg = '您的区域申请已通过';
		        	}
		        }
		        if ($list){
		            $this->result($list,1,$msg);
		        }else{
		            $this->result('',0,'暂无数据');
		        }
	        }
        } else {
        	$this->result('',0,'暂无数据');
        }
      
	}
	/**
	 * 获取用户被取消合作的信息
	 * @return [type] [description]
	 */
	public function getRejectStatus()
	{
		$uid = input('post.uid');
		$person_rank = Db::table('sm_user')
						->where('id',$uid)
						->value('person_rank');
		$info = Db::table('sm_area')
					->where([
						'sm_id' => $uid,
						'sm_mold' => 2 //取消合作的
					])
					->order('id desc')
					->limit(1)
					->field('id,reason,FROM_UNIXTIME(audit_time) as audit_time,audit_person')
					->find();
				// return $info ;die();
		if($person_rank == 0 && $info) {
			$cancel = Db::table('sm_apply_cancel')
						->where('sid',$info['id'])
						->field('cancel_reason')
						->find();
			if($cancel){
				$info['title'] = '您的取消合作已通过';
			} else {
				$info['title'] = '您已被总后台取消合作';
			}
			$info['bottom_bar'] = '点击后重新注册';
			$this->result($info,1,'获取成功');
		} else {
			$this->result('',0,'暂无取消合作');
		}
	}
}