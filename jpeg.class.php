<?php
/*
 * JPEG解析函数
 * Copyright Hito 2017年11月28日
 * 参考资料:
 * http://dev.exiv2.org/projects/exiv2/wiki/The_Metadata_in_JPEG_files
 * https://www.media.mit.edu/pia/Research/deepview/exif.html
 * http://vip.sugovica.hu/Sardi/kepnezo/JPEG%20File%20Layout%20and%20Format.htm
 */
class JPEG{
    //JPEG Metadata Structure
    private $markerkey=array("soi"=>0xd8,"sof0"=>0xc0,"sof2"=>0xc2,"dht"=>0xc4,"dqt"=>0xdb,"dri"=>0xdd,"sos"=>0xda,"rst"=>array(0xd0,0xd1,0xd2,0xd3,0xd4,0xd5,0xd6,0xd7),"app"=>array(0xe0,0xe1,0xe2,0xe3,0xe4,0xe5,0xe6,0xe7,0xe8,0xe9,0xea,0xeb,0xec,0xed,0xee,0xef),"com"=>0xfe,"eoi"=>0xd9);
    private $markerdata=array();
    private $ImageScan;


    private $Image;
    private $PosPointer;
    private $ImageSize;
    public function __construct($filename){
        $this->Image = fopen($filename,"rb");
        $this->ImageSize= filesize($filename);
        if($this->GetByte(2)!==0xd8ff){
            die("Not a JPEG Image");
        }
        $this->Parse();
    }

    public function GetByte($len){
        if(feof($this->Image)) return NULL;
        $tmp =  fread($this->Image,$len);
        $tmp2=array();
        $this->PosPointer=ftell($this->Image);
        if($len==1){
            $tmp2=unpack("C",$tmp);
        }else if($len==2){
            $tmp2=unpack("S",$tmp);
        }else if($len==4){
            $tmp2=unpack("L",$tmp);
        }else{
            $tmp2[1]=$tmp;
        }
        return $tmp2[1];
    }
    public function GetMarkerData(){
        $buffer="";
        foreach($this->markerdata as $d){
            foreach($d as $c){
                $buffer.=$c;
            }
        }
        return $buffer;
    }

    public function get_marker($byte){
        foreach($this->markerkey as $meta=>$bit){
            if(is_array($bit)){
                foreach($bit as $b){
                    if($byte == $b) return $meta;
                }
            }
            if($byte == $bit) return $meta;
        }
        return NULL;
    }

    public function compress(){
        //Remove Com Marker
        $this->markerdata['com']=array();
        //Remove App Marker
        $this->markerdata['app']=array();
    }

    public function Parse(){
        while(($byte=$this->GetByte(1))!==NULL){
            $nxtbyte = @$this->GetByte(1);
            if($byte == 0xff){
                $marker = $this->get_marker($nxtbyte);
                //Variable Size Marker
                if($marker == "sof" || $marker == "sof2" || $marker == "dht" || $marker == "dqt" || $marker == "sof0" || $marker == "app" || $marker == "com"){
                    $offset1= $this->GetByte(1);
                    $offset2 = $this->GetByte(1);
                    $len = $offset1*256+$offset2;
                    $data = $this->GetByte($len-2);
                    //full data include marker and length
                    $fulldata = pack("C",0xff).pack("C",$nxtbyte).pack("C",$offset1).pack("C",$offset2).$data;
                    $this->markerdata[$marker][]= $fulldata;
                }else if($marker=="dri"){
                    //pass
                }else if($marker=="rst"){
                    //pass
                }else if($marker=="sos"){
                    //SOS Segment
                    $len = $this->ImageSize-$this->PosPointer-1;
                    $this->ImageScan =  pack("C",0xff). pack("C",0xda).$this->GetByte($len);
                }
            }
        }
    }
    public function GetImageBin(){
        return pack("S",0xd8ff).$this->GetMarkerData().$this->ImageScan.pack("S",0xd9ff);
    }
    public function Storage($filename){
        if(file_exists($filename)) unlink($filename);
        $f = fopen($filename,"ab+");
        fwrite($f,$this->GetImageBin());
        fclose($f);
    }
    public function __destruct(){
        fclose($this->Image);
    }
}
