<?php 
namespace app\cf\controller;
// use app\base\controller\Cf;
use think\Db;
use think\Controller;
use WxJ\WxJ;

/**
 * 车服管家用户登录
 */
class A extends Controller
{
	public function a()
	{	
		$data = input('post.');
		if(time() - session('time'.$data['uid']) < 20){
			// return 1;
			return '33'.session('time'.$data['uid']);
		}else{
			session('time'.$data['uid'],time());
			// return 2;
			return '44'.session('time'.$data['uid']);
		}
	}
}