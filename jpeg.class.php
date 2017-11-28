<?php
/*
 * JPEG解析函数
 * Copyright Hito 2017年11月28日
 * https://www.media.mit.edu/pia/Research/deepview/exif.html
 */
class JPEG{
    private $exifheader=array();
    private $jfifheader=array();
    private $stream;

    private $marker;

    public function __construct($filename){
        $res = fopen($filename,"rb");
        $header=fread($res,1);
        echo $header;
        var_dump($header==0xff);
    }







}

$a =  new JPEG("./demo.jpg");
