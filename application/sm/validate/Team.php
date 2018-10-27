<?php 
namespace app\sm\validate;
use think\Validate;

class Team extends Validate
{
	//负责人照片，负责人姓名，电话，性别，个人简介，团队使命，团队愿景，团队口号
	protected $rule = [
		'leader_img|负责人正面照' => 'require', 
		'leader|负责人姓名'       => 'require',
		'leader_resume|负责人简介' => 'require',
		'team_slogan|团队口号'    => 'require',
		'team_mission|团队使命'   => 'require',
		'team_vision|团队愿景'    => 'require',
		
	];
}