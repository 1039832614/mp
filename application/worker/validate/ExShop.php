<?php 
namespace app\worker\validate;
use think\Validate;

class ExShop extends Validate
{
	protected $rule = [
		'reason|换店理由' => 'require',
		'new_shop|新店铺' => 'require'
	];
	protected $message = [
		'new_shop.require' => '尚未选择新店铺',
	];
}