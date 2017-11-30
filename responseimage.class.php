<?php
/*
 * 响应式图片类
 * 杨海涛 2017年7月12日
 *
 */

class ResponseImage{
    //原始图片绝对路径
    public $RawImage;
    //原始图片的文件名
    private $RawFileName;
    //原始图片的相关信息
    private $Width;
    private $Height;
    private $MIMETYPE;

    //实际请求图片缓存绝对路径
    private $Image;

    //图片缓存目录
    private $CacheDir;

    //其它相关信息
    //支持的处理动作
    private $supportaction=array("crop","reduce");
    //图片处理动作:二维数组,格式为array(array(action=>"剪裁",x=>"起始X点",y=>"起始Y点",width=>"宽度",height=>"高度"),array(action=>"缩放",x=>"起始X点",y=>"起始Y点",width="宽度",height=>"高度"));
    private $actions=array();

    //环境变量
    private $docroot;

    //是否是直接请求位处理的图片
    private $isDirect = false;


    public function __construct($cachedir){
        $this->docroot = realpath($_SERVER["DOCUMENT_ROOT"]);
        if(!is_dir($cachedir)){mkdir($cachedir);}
        $this->CacheDir=realpath($cachedir)."/";
        preg_match("/(^.*?([^\/]*\.(jpg|jpeg|gif|png)))(?=(\/.*))/i",$_SERVER['REQUEST_URI'],$m);
        if(empty($m)){
            //请求 原始图片
            $this->Image=$this->RawImage=$this->docroot.$_SERVER['REQUEST_URI'];
        }else{
            //请求 处理之后的图片
            $this->RawImage=$this->docroot.$m[1];
            $this->RawFileName=$m[2];
            $this->init($m[4]);
        }
        //原始图片不存在
        if(!file_exists($this->RawImage)){
            display_error(404,Page404);
        }
        //请求处理图片(动作不为空)
        if(!empty($this->actions)){
            //原始图片相关信息
            $RAWInfo = getimagesize($this->RawImage);
            $this->MIMETYPE = $RAWInfo['mime'];
            $this->imageinit();
        }
        $this->display();
    }

    //初始化图片：1，生成处理动作，2，获取缓存文件名$this->Image
    //剪裁:example.com/example.jpg/(crop:[x[,y,]]width[,height])
    //缩放:example.com/example.jpg/(reduce:[x[,y,]]width[,height])
    //括号里面为动作，可以连续多次使用:
    //example.com/example.jpg/(crop:[x[,y,]]width[,height])/(reduce:[x,[y,]]width[,height])为先进行剪裁，然后压缩处理
    //[]中的为可选值，x,y不填默认为0,height不填默认为图片高度（剪裁）和宽度缩小后图片高度（缩放）
    private function init($actionstr){
        $actions = explode("/",trim($actionstr,"/"));
        foreach($actions as $a){
            if($a=="") continue;
            $action = substr($a,0,strpos($a,":"));
            $datas= explode(",",substr($a,strpos($a,":")+1));
            $len = count($datas);
            switch($len){
            case 0:
                break;
            case 1:
                $x = 0;
                $y = 0;
                $width = $datas[0]*1;
                $height = "auto";
                break;
            case 2:
                $x = 0;
                $y = 0;
                $width =$datas[0]*1;
                $height = $datas[1]*1;
                break;
            case 3:
                $x = $datas[0]*1;
                $y = 0;
                $width = $datas[1]*1;
                $height = $datas[2]*1;
                break;
            case 4:
                $x = $datas[0]*1;
                $y = $datas[1]*1;
                $width = $datas[2]*1;
                $height = $datas[3]*1;
            }
            array_push($this->actions,array("action"=>$action,"x"=>$x,"y"=>$y,"width"=>$width,"height"=>$height));
            $this->Image .= "$action-$x-$y-$width-$height-";
        }
        $this->Image=$this->CacheDir.$this->Image.$this->RawFileName;
    }


    //低级功能：缩放图片：第一个参数为图片资源参数，以原始图片
    private function reduceimage($imgres,$x,$y,$w,$h){
        $imgwidth = imagesx($imgres);
        $imgheight = imagesy($imgres);
        if($x>$imgwidth) $x = $x-$imgwidth;
        if($y>$imgheight) $y = $y-$imgheight;
        if($w>$imgwidth-$x) $w=$imgwidth-$x;
        if($h==='auto') $h=$imgheight*$w/$imgwidth;
        if($h>$imgheight-$y) $h=$imgheight-$y;
        $new = imagecreatetruecolor($w,$h);
        if(imagecopyresampled($new,$imgres,0,0,$x,$y,$w,$h,$imgwidth-$x,$imgheight-$y)){
            imagedestroy($imgres);
            return $new;
        }else{
            imagedestroy($imgres);
            return false;
        }
    }

    //高级功能：裁剪图片
    private function cropimage($imgres,$x,$y,$w,$h){
        $imgwidth = imagesx($imgres);
        $imgheight = imagesy($imgres);
        if($x>$imgwidth) $x = $x-$imgwidth;
        if($y>$imgheight) $y = $y-$imgheight;
        if($w>$imgwidth-$x) $w=$imgwidth-$x;
        if($h==='auto') $h=$imgheight*$w/$imgwidth;
        if($h>$imgheight-$y) $h=$imgheight-$y;
        $new = imagecrop($imgres,array("x"=>$x,"y"=>$y,'width'=>$w,'height'=>$h));
        imagedestroy($imgres);
        return $new;
    }


    //初始化并缩放图片
    public function imageinit(){
        if($this->MIMETYPE=="image/jpeg"){
            $res = imagecreatefromjpeg($this->RawImage);
            $storage="imagejpeg";
        }else if($this->MIMETYPE=="image/gif"){
            $res = imagecreatefromgif($this->RawImage);
            $storage="imagegif";
        }else if($this->MIMETYPE=="image/png"){
            $res = imagecreatefrompng($this->RawImage);
            $storage="imagepng";
        }
        foreach($this->actions as $actions){
            $x = $actions['x'];
            $y = $actions['y'];
            $w = $actions['width'];
            $h = $actions['height'];
            if($actions['action'] === 'reduce'){
                $res = $this->reduceimage($res,$x,$y,$w,$h);
            }else if($actions['action']==='crop'){
                $res = $this->cropimage($res,$x,$y,$w,$h);
            }
        }
        @unlink($this->Image);
        $storage($res,$this->Image,ImageQuality);
    }

    public function display(){
        //压缩图片
        if($this->MIMETYPE=="image/jpeg" && defined("ImageCompress") && ImageCompress === true){
            $image = new JPEG($this->Image);
            $image->compress();
            $imagedata = $image->GetImageBin();
            header("Image-Compress:YES");
        }else{
            header("Image-Compress:No");
            $imagedata = file_get_contents($this->Image);
        }
        $etag = md5($imagedata);
        $lastmodified = gmdate("D, d M Y H:i:s T",filemtime($this->Image));
        $none_match = isset($_SERVER["HTTP_IF_NONE_MATCH"])?$_SERVER["HTTP_IF_NONE_MATCH"]:'';
        $modified_since = isset($_SERVER["HTTP_IF_MODIFIED_SINCE"])?strtotime($_SERVER["HTTP_IF_MODIFIED_SINCE"]):0;
        if($etag === $none_match){
            header("HTTP/1.1 304 Not Modified");
        }else if(strtotime($modified_since) >= strtotime($lastmodified)){
            header("HTTP/1.1 304 Not Modified");
        }else{
            $expires = strtotime(expires);
            $content = file_get_contents($this->Image);
            header("Content-Type:".$this->MIMETYPE);
            header("Last-Modified:".$lastmodified);
            header("ETag:".$etag);
            if($expires*1>1) header("Expires:".gmdate("D, d M Y H:i:s T",$expires));
            header("Content-Length:".strlen($content));
            echo $imagedata;
        }
        return; 
    }
}
//展示错误信息
function display_error($errorcode,$html){
    $error=array("403"=>"403 Forbidden","404"=>"404 Not Found","500"=>"Internal Server Error");
    header("HTTP/1.1 ".$error[$errorcode]);
    header("Content-Type:text/html;charset=utf-8");
    if(file_exists($html)){
        echo file_get_contents($html);
    }else{
        echo $html;
    }
    die;
}

//COOKIE方式获取图片分辨率:现在不使用
function CookieGetResolution(){
    $width="100%";
    $height="auto";
    $resolution = isset($_COOKIE['plm-resolution'])?$_COOKIE['plm-resolution']:false;
    if($resolution){
        $tmp = explode("*",$resolution);
        $devicewidth = (int)$tmp[0];
        $deviceheight = (int)$tmp[1];
        $devicepixeratio = (int)$tmp[2];
    }
    //计算设备像素，适用高清设备
    $devicewpix = $devicewidth*$devicepixeratio;
    $devicehpix = $deviceheight*$devicepixeratio;
    if($RawWidth>$devicewpix){
        $width=$devicewpix;
    }else{
        $width=$RawWidth;
    }
    if($RawHeight>$devicehpix){
        $height=$devicehpix;
    }else{
        $height=$RawHeight;
    }
    return array("width"=>$width,"height"=>$height);
}
