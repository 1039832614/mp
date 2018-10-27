<?php 
namespace app\trip\validate;
use think\Validate;
class Car extends Validate
{	
	protected $regex = [ 'zip' => '/^[京津沪渝冀豫云辽黑湘皖鲁新苏浙赣鄂桂甘晋蒙陕吉闽贵粤青藏川宁琼使领A-Z]{1}[A-Z]{1}[A-Z0-9]{4}[A-Z0-9挂学警港澳]{1}$/u'];
	protected $rule = [
		'plate|车牌号'          => 'require|regex:zip',
		'brand|品牌'			=> 'require',
		'type|车型'			    => 'require',
	];
	




}