<?php 
namespace app\st\validate;

use think\Validate;

class Eva extends Validate
{
	protected $rule = [
		'class|评论星级' => 'require',
		'content|评论内容' => 'require',
	];
}