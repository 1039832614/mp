<?php 
namespace app\sm\validate;
use think\Validate;

class Phone extends Validate
{
	//负责人照片，负责人姓名，电话，性别，个人简介，团队使命，团队愿景，团队口号
	protected $rule = [
		'phone|手机号' => 'require|mobile',
	];
}