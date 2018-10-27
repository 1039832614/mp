<?php 
namespace app\st\validate;

use think\Validate;

class User extends Validate
{
	protected $rule = [
		'name|姓名' => 'require',
		'phone|手机号' => 'require|mobile',
	];
}