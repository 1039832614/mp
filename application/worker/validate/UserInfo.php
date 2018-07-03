<?php 
namespace app\worker\validate;
use think\Validate;

class UserInfo extends Validate
{
	protected $rule = [
		'wx_head|头像'  => 'require',
		'repair|车型'   => 'require',
		'name|姓名'	    => 'require|max:18',
		'phone|电话'    => 'require|length:11',
		'server|从业时间' => 'require',
		'sid|所属汽修厂' => 'require',
		'skill|技能介绍' => 'max:200'
	];
}