<?php 
/**
 * 申请换店
 */
namespace app\cb\validate;
use think\Validate;

class Exshop extends Validate
{
	protected $rule = [
      'card_number|卡号'  	=> 'require',
      'uid|用户id'	=>	'require',
      'old_shop|旧店名称'	=> 'require',
      'new_shop|新店名称'	=> 'require',
      'extype|换店类型' => 'require'
    ];
}