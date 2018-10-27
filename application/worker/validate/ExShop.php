<?php 
namespace app\worker\validate;
use think\Validate;

class ExShop extends Validate
{
	protected $rule = [
		'reason|换店理由' => 'require',
		'sid|新店铺' => 'require'
	];
	protected $message = [
		'sid.require' => '请选择新店铺',
	];
}