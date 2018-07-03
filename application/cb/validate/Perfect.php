<?php 
/**
 * 完善个人信息
 */
namespace app\cb\validate;
use think\Validate;

class Perfect extends Validate
{
	protected $rule = [
      'name|姓名'  	=> 'require',
      'phone|手机号'	=>	'require|mobile'
    ];
}