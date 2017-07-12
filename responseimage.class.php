<?php
/*
 * 响应式图片
 */

class ResponseImage{
    //原始图片路径，长度和宽度
    public $RawImage;
    public $RawWidth;
    public $RawHeight;

    //新图片缓存目录
    private $CacheDir;
    //实际请求图片缓存路径和文件名
    private $Image;
    //新图片相关信息
    private $Width;
    private $Height;
    private $MIMETYPE;

    //其它相关信息
    //是否是直接请求位处理的图片
    private $isDirect = false;
    //支持的处理动作
    private $supportaction=array("crop","reduce");
    //图片处理动作:二维数组,格式为array(array(action=>"剪裁",x=>"起始X点",y=>"起始Y点",width=>"宽度",height=>"高度"),array(action=>"缩放",x=>"起始X点",y=>"起始Y点",width="宽度",height=>"高度"));
    private $psactions=array();

    public function __construct($cachedir){
        $this->CacheDir=realpath($cachedir)."/";
        //原始信息处理:获取请求的原始图片
        preg_match("/([^\/]*\.(jpg|gif|png|jpeg))(.*)$/i",$_SERVER['REQUEST_URI'],$m);
        if(!empty($m[1])){
            $this->RawImage = realpath($_SERVER['DOCUMENT_ROOT'])."/".$m[1];
        }else{
            display_error(500,"请求资源不存在，请检查您的伪静态规则是否正确!");
        }

        //初始化处理动作
        $actionresult = $this->PsActioninit();
        //图片参数错误
        if(!empty($m[3]) && $actionresult===false && defined("DisplayRaw") && DisplayRaw ===true){
            $this->Image=$this->RawImage;
        }else if(!empty($m[3]) && $actionresult===false && DisplayRaw ===false){
            display_error(500,"图片参数请求参数错误，请检查您的参数或者查看帮助文档!");
        }
        //原始图片相关信息
        $RAWInfo = getimagesize($this->RawImage);
        $this->RawWidth = $RAWInfo[0];
        $this->RawHeight = $RAWInfo[1];
        $this->MIMETYPE = $RAWInfo['mime'];
        
        //如果请求的图片不是原始图片，并且原始图片出现更改，请重新生成需要的图片
        if($this->Image!==$this->RawImage && (!file_exists($this->Image) || filemtime($this->Image) < filemtime($this->RawImage))){
            $this->imageinit();
        }
    }

    //srcset URL方式获取需要的图片分辨率URL格式为
    //剪裁:example.com/example.jpg/(crop:[x[,y,]]width[,height])
    //缩放:example.com/example.jpg/(reduce:[x[,y,]]width[,height])
    //括号里面为动作，可以连续多次使用:
    //example.com/example.jpg/(crop:[x[,y,]]width[,height])/(reduce:[x,[y,]]width[,height])为先进行剪裁，然后压缩处理
    //[]中的为可选值，x,y不填默认为0,height不填默认为图片高度（剪裁）和宽度缩小后图片高度（缩放）
    private function PsActioninit(){
        preg_match("/([^\/]*\.(jpg|jpeg|png|gif))([\/\:(reduce|crop)\,\d]*)$/i",$_SERVER['REQUEST_URI'],$m);
        if(empty($m[3])){
            return false;
        }
        $actions = explode("/",trim($m[3],"/"));
        foreach($actions as $a){
            $splitpos = strpos($a,":");
            $action = substr($a,0,$splitpos);
            if(!in_array($action,$this->supportaction)) continue;
            $datas = explode(",",substr($a,$splitpos+1));
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
            array_push($this->psactions,array("action"=>$action,"x"=>$x,"y"=>$y,"width"=>$width,"height"=>$height));
            $this->Image .= "$action-$x-$y-$width-$height-";
        }
        $this->Image=$this->CacheDir.$this->Image.$m[1];
        return true;
    }

    //低级功能：缩放图片：第一个参数为图片资源参数，以原始图片
    private function reduceimage($imgres,$x,$y,$w,$h){
        if($w>$this->RawWidth){
            $w=$this->RawWidth;
        }
        if($h==="auto"){
            $h = floor($this->RawHeight/($this->RawWidth/$w));
        }
        if($h>$this->RawHeight){
            $h=$this->RawHeight;
        }
        $new = imagecreatetruecolor($w,$h);
        if(imagecopyresampled($new,$imgres,0,0,$x,$y,$w,$h,($this->RawWidth-$x),($this->RawHeight-$y))){
            return $new;
        }else{
            return false;
        }
    }

    //高级功能：裁剪图片
    private function cropimage($imgres,$x,$y,$w,$h){
        if($w>$this->RawWidth){
            $w=$this->RawWidth;
        }
        if($h==="auto"){
            $h = floor($this->RawHeight/($this->RawWidth/$w));
        }
        if($h>$this->RawHeight){
            $h=$this->RawHeight;
        }
        return imagecrop($imgres,array("x"=>$x,"y"=>$y,'width'=>$w,'height'=>$h));
    }


    //初始化并缩放图片
    public function imageinit(){
        if($this->MIMETYPE=="image/jpeg"){
            $res = imagecreatefromjpeg($this->RawImage);
            $storage="imagejpeg";
        }else if($this->MEMETYPE=="image/gif"){
            $res = imagecreatefromgif($this->RawImage);
            $storage="imagegif";
        }else if($this->MEMETYPE=="image/png"){
            $res = imagecreatefrompng($this->RawImage);
            $storage="imagepng";
        }
        foreach($this->psactions as $actions){
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
        $storage($res,$this->Image);
    }


    public function display(){
        $etag = md5_file($this->Image);
        $lastmodified = gmdate("D, d M Y H:i:s T",filemtime($this->Image));
        $none_match = isset($_SERVER["HTTP_IF_NONE_MATCH"])?$_SERVER["HTTP_IF_NONE_MATCH"]:'';
        $modified_since = isset($_SERVER["HTTP_IF_MODIFIED_SINCE"])?strtotime($_SERVER["HTTP_IF_MODIFIED_SINCE"]):0;
        if($etag === $none_match){
            header("HTTP/1.1 304 Not Modified");
        }else if(strtotime($modified_since) >= strtotime($lastmodified)){
            header("HTTP/1.1 304 Not Modified");
        }else{
            header("HTTP/1.1 200 OK");
            header("Content-Type:".$this->MIMETYPE);
            header("Last-Modified:".$lastmodified);
            header("ETag:".$etag);
            echo file_get_contents($this->Image);
        }
        return; 
    }


    //COOKIE方式获取图片分辨率:现在不使用
    private function CookieGetResolution(){
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
        if($this->RawWidth>$devicewpix){
            $width=$devicewpix;
        }else{
            $width=$this->RawWidth;
        }
        if($this->RawHeight>$devicehpix){
            $height=$devicehpix;
        }else{
            $height=$this->RawHeight;
        }
        return array("width"=>$width,"height"=>$height);
    }
}

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
