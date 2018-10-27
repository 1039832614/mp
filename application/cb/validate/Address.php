<?php 
/**
 * 转盘抽奖
 */
namespace app\cb\validate;
use think\Validate;

class Address extends Validate
{
	protected $rule = [
	  'man|收货人'	        => 'require',
	  'phone|收货人电话'	=> 'require|mobile',
      'address|收货地址'  	=> 'require',
      'details|详细地址'	=>	'require',
    ];
}