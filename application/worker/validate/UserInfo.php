<?php 
namespace app\worker\validate;
use think\Validate;

class UserInfo extends Validate
{
	protected $rule = [
		'name|姓名'	    => 'require|max:18',
		'phone|电话'    => 'require|length:11|mobile',
		'repair|车型'   => 'require',
		'server|从业时间' => 'require',
		'sid|所属汽修厂' => 'require',
		'head|头像'  => 'require',
		'skill|技能介绍' => 'max:200'
	];
	protected $message = [
		'phone.length' => '手机号长度不符合要求'
	];
}