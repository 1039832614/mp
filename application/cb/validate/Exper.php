<?php 
/**
 * 免费体验
 */
namespace app\cb\validate;
use think\Validate;

class Exper extends Validate
{
	protected $rule = [
      'province|车牌省'  	=> 'require',
      'city|车牌市'	=>	'require',
      'plate|车牌号'	=>	'require',
      'car_cate_id|车牌id'	=>	'require',
      'cate_name|车牌姓名'	=>	'require',
      'oil_cate|油类型'	=>	'require',
      'sid|维修厂id'	=>	'require',
      'trade_no|所需能量'	=>	'require',
      'oil_name|油名称'	=>	'require',
      'oil|油'	=>	'require',
    ];
}