<?php 
namespace app\st\validate;

use think\Validate;

class Info extends Validate
{
	protected $rule = [
		'name|姓名' => 'require',
		'phone|手机号' => 'require|mobile',
		'carclass|车系' => 'require',
		'cartype|车型' => 'require',
	];
}