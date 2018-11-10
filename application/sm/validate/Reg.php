<?php 
namespace app\sm\validate;
use think\Validate;

class Reg extends Validate
{
	//头像，姓名，性别， 电话，开户名，开户行，分行，卡号，服务区域
	protected $rule = [
		'head_pic|头像' => 'require',
		'name|姓名' => 'require',
		'sex|性别' => 'require',
		'phone|手机号' => 'require|mobile',
		'bank_code|银行编码' => 'require',
		'bank_branch|银行分行' => 'require',
		'bank_name|开户名' => 'require',
		'account|银行卡号' => 'require',
		// 'area|区域' => 'require',
	];
}