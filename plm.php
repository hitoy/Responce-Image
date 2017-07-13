<?php
/*
 * Web图片加载助手
 * Version:1.0
 * 作者:杨海涛 2017年7月10日 https://www.hitoy.org/
 * 说明:
 * 在移动设备的访问量越来越多，占据的网站流量比重也不断增加
 * 为了让网站适用于移动设备浏览，现在一般采用响应式或者和独
 * 立网站分开的方式重新制作一个网站，对于一般网站来说，逻辑
 * 并不复杂，所以响应式的方式更适合为普通网站的移动解决方案。
 * 但存在一个问题，为了保证浏览效果，PC网站上的图片质量一般
 * 比较高，这就意味这其图片尺寸较大，如果放到手机上，可能由
 * 于网络速度较差而影响页面加载，一种办法是img标签中使用
 * srcset，对不同的分辨率使用不同图片；另外一种是根据不同设
 * 备的分辨率对初试图片进行处理，通常使用COOKIE;
 *
 * 本工具能使用srcset的方式动态生成需要的图片
 //srcset URL方式获取需要的图片分辨率URL格式为
 //剪裁:example.com/example.jpg/(crop:[x[,y,]]width[,height])
 //缩放:example.com/example.jpg/(reduce:[x[,y,]]width[,height])
 //括号里面为动作，可以连续多次使用:
 //example.com/example.jpg/(crop:[x[,y,]]width[,height])/(reduce:[x,[y,]]width[,height])为先进行剪裁，然后压缩处理
 //[]中的为可选值，x,y不填默认为0,height不填默认为图片高度（剪裁）和宽度缩小后图片高度（缩放）
 * 2, 服务器重写至此文件
 *
 * 最基础的配置:
 */
//404页面
define("Page404","a");
//缓存目录
define("CacheDir","./caches");
//图片HTTP缓存时间
define("expires","+1 days");
//是否打开排错功能
define("Debug",true);
//图片参数错误时，显示原始图片
define("DisplayRaw",false);
//是否对图片的进行外链保护
define("ImageProtect",false);
//图片保护白名单
$whitelist=array("ip"=>array('127.0.0.1','::1'),"useragent"=>array('Bingbot','Googlebot','BaiduSpider','YandexBot','360Spider'),'host'=>array('localhost'));

//不要更改下面的代码
if(defined('Debug') && Debug == true){
    error_reporting(E_ALL);
}
if(defined('ImageProtect') && ImageProtect == true && is_forbid()){
   header("HTTP/1.1 403 Forbid"); 
   die;
}
require_once("./responseimage.class.php");
$img = new ResponseImage("./caches");
$img->display();

function is_forbid(){
    global $whitelist;
    $useragent = @$_SERVER['HTTP_USER_AGENT'];
    $remoteip  = @$_SERVER['REMOTE_ADDR'];
    $referer = @$_SERVER['HTTP_REFERER'];
     foreach($whitelist['ip'] as $ip){
        if($ip==$remoteip){
            return false;
        }
    }
    foreach($whitelist['useragent'] as $ua){
        if(strpos($useragent,$ua)!==false){
            return false;
        }
    }
    foreach($whitelist['host'] as $host){
       preg_match("/https?:\/\/([^\/]*)/i",$referer,$m);
       $refererhost=isset($m[1])?$m[1]:"";
       $host = str_replace(".","\.",$host);
       if($host && $refererhost && preg_match("/$host/",$refererhost)){
            return false;
       }
    }
    return true;
}
