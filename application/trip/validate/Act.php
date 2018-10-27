<?php 
namespace app\trip\validate;
use think\Validate;
class Act extends Validate
{
	protected $rule = [
		'title|活动标题'          => 'require|max:20',
		'path|路线'				  => 'require',
		'thronheim|始发地'		  => 'require',
		'deadline|截至时间'		  => 'require',
		'number|总人数'	  => 'require|number',
		'start_time|开始时间'     => 'require',
		'end_time|结束时间'		  => 'require',
		'content|活动描述'        => 'require'
	];
	protected $message = [
		'number.require' => '人数必填',
		'number.number'  => '人数必须为数字',
	];
}

