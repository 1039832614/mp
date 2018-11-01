<?php 
namespace app\ubi\validate;

use think\Validate;

class Policy extends Validate
{
	protected $rule = [
		'company|公司名称'          => 'require',
		'policy_num|保单号'       => 'require',
		'start_time|投保开始时间'       => 'require',
		'end_time|投保结束时间'       => 'require',
		'img|保单图片'    => 'require',
	];
}