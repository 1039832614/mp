<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件

/**
 * 单图片上传
 * @param  图片字段
 * @param  要保存的路径
 * @return 图片保存后的路径
 */
 function upload($image,$path,$host){
    // 本地测试地址，上线后更改
    $host = $host ? $host : $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'];
    // 获取表单上传文件
    $file = request()->file($image);
    // 进行验证并进行上传
    $info = $file->validate(['size'=>3145728,'ext'=>'jpg,png,jpeg'])->move( './uploads/'.$path);
    // 上传成功后输出信息
    if($info){
      return  $host.'/uploads/'.$path.'/'.$info->getSaveName();
    }else{
      // 上传失败获取错误信息
      return  $file->getError();
    }
}


/**
 * @return 用于JWTtoken 的key值
 */
function create_key(){
  return $key="Jx3T4w5%djLp1t#";
}

/*
 * 密码加密方式
 * @param string $ps 要加密的字符串
 * @return string 加密后的字符串
 * @author zhaizhaohui
 */
function get_encrypt($ps){
    return sha1(sha1('zm'.$ps));
}


/**
 * 密码比对
 * @param string $ps 要比较的密码
 * @param string $db_ps 数据库保存的已经加密过的密码
 * @return boolean 密码相同，返回true
 * @author zhaizhaohui
 */
function compare_password($ps,$db_ps){
    return get_encrypt($ps) == $db_ps;
}

/**
 * 数组转换字符串
 * @param  数组
 * @param  选择的字段
 * @return 字符串
 */
function array_str($data,$key){
    $arr=array_column($data,$key);
    $str=implode(',',$arr);
    return $str;
}

/**
 *  无限极分类
 * @param  数组
 * @param  父级id
 * @return 树形数组
 */
  function get_child($data,$pid=0){
      $arr = array();
      foreach ($data as $key =>$v) {
          if ($v['pid']==$pid) {
              $son = get_child($data,$v['id']);
              if ($son){
                  $v["son"] = $son;
              }
             $arr[] = $v; 
          }
         
      }
     return $arr;
  };

  /**
   * 生成唯一编号
   * @return [type] [description]
   */
function build_only_sn()
{
    $arr = explode(' ',microtime());
    $num = $arr[0]*10000000000 + $arr[1] - $arr[0]*1000000;
    $num = str_pad($num,11,mt_rand(0,9));
    $num = str_pad($num,12,mt_rand(0,9));
    return  $num;
}


/**
 * 生成唯一订单编号
 * @return [type] [description]
 */
function build_order_sn()
{
    return str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT) . time();
}






  