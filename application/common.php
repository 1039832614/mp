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
 * 单图片上传
 * @param  图片字段
 * @param  要保存的路径
 * @return 图片保存后的路径
 */
 function ubi_upload($image,$path,$host){
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

/**
   * 获取省
   *@return $msg 成功或者失败
   */
function province(){
    $china = Db::table('co_china_data')->where('pid',1)->field('name,id')->select();
    return $china;
  }
  /**
   * 获取市
   *@return $msg 成功或者失败
   */
function city(){
    $id = input("get.id");
    $city = Db::table('co_china_data')->where('pid',$id)->field('id,name')->select();
    return $city;
  }
  /**
   * 获取县
   *@return $msg 成功或者失败
   */
function county(){
    $id = input("get.id");
    $county = Db::table('co_china_data')->where('pid',$id)->field('id,name')->select();
    return $county;
  }

  

   // post
    function geturl($arrParams,$url,$method='post')
    { 
   
      $header = array("Content-type: application/json");// 注意header头，格式k:v

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $arrParams);
      curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 2);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
      // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);  
        // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); 
      // curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);// curl函数执行的超时时间（包括连接到返回结束） 秒单位
      // curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);// 连接上的时长 秒单位
      curl_setopt($ch, CURLOPT_URL, $url);
      $ret = curl_exec($ch);
      
      $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);// 对方服务器返回http code
      curl_close($ch);

      
      return $ret;
    }
     
    // get
    function getcurl($url)
    { 
        //初始化
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, false);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        //执行命令
        $data = curl_exec($curl);   
        //关闭URL请求
        curl_close($curl);

        //显示获得的数据
        return $data;
    }

    /**
     * 日期格式转换
     * 
     */
    
    function changeTimes($data,$field,$type)
    {   

        foreach ($data as $key => $value) {
            if (!isset($value[$field])) {
              exit($field.'字段不存在');
            }
            // 是否为空 
            if (!$value[$field]) {
                $data[$key][$field] = '';
                
            } 
            // 非时间戳转换为时间戳
            elseif (strlen(intval($value[$field])) !==10) {
                $time = strtotime($value[$field]);
            } 
            // 非时间格式不做改变
            else{
                $time = $value[$field];
                continue;
            } 

            $data[$key][$field] = Date($type,$time)?:'数据格式不支持转换';
        }

        return $data;
    }