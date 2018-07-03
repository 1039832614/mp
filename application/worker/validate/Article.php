<?php
namespace app\worker\validate;

use think\Validate;

class Article extends Validate
{
	protected $rule = [
		'title|标题'   => 'require|max:70',
		'thumb|配图'   => 'require',
		'content|内容' => 'require',
		'mold|类型'    => 'require'
	];
}