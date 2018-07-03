<?php 
namespace app\travel\validate;

use think\Validate;

class Act extends Validate
{
	protected $rule = [
		'title|标题'          => 'require',
		'origin|出发地'       => 'require',
		'start_time|出发时间' => 'require',
		'stop_time|截止时间'  => 'require',
		'path|出行路线'       => 'require',
		'car_type|车型'       => 'require',
		'details|活动详情'    => 'require',
		'number|人数'         => 'require|number',
	];

	protected $message = [
		'number.require' => '人数必填',
		'number.number'  => '人数必须为数字'
	];
}