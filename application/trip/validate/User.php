<?php 
namespace app\trip\validate;
use think\Validate;
class User extends Validate
{
	protected $rule = [
		'name|用户姓名'        => 'require',
		'phone|手机号'		=> 'require|mobile',
	];
}