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
    //JPEG开始标记
    public $marker = 0xd8ff;

    //原始二级制数据
    public $rawData;

    //文件大小，单位字节
    public $size = 0;

    //是否时JPEG格式
    public $isJPEG = false;

    //JPEG图片头部格式的标记符名称和对应字节码
    public $headermarker = array("soi"=>0xd8,"sof0"=>0xc0,"sof2"=>0xc2,"dht"=>0xc4,"dqt"=>0xdb,"dri"=>0xdd,"sos"=>0xda,"rst"=>array(0xd0,0xd1,0xd2,0xd3,0xd4,0xd5,0xd6,0xd7),"app"=>array(0xe0,0xe1,0xe2,0xe3,0xe4,0xe5,0xe6,0xe7,0xe8,0xe9,0xea,0xeb,0xec,0xed,0xee,0xef),"com"=>0xfe,"eoi"=>0xd9);

    //JPEG头部数据
    public $headerData = array();

    //JPEG内容
    public $scanData;

    //当前文件
    private $file;

    //当前指针位置
    private $position = 0;

    /*
     * 初始化图片类型
     * @param $file string 文件路径或者二进制数据
     */
    public function __construct($file = NULL){
        if(@file_exists($file)){
            $this->rawData = file_get_contents($file);
            $this->size = filesize($file);
            $this->file = $file;
        }elseif(is_string($file)){
            $this->rawData = $file;
            $this->size = strlen($file); 
        }

        if($this->getbyte(2) == $this->marker){
            $this->isJPEG = true;
            $this->parse();
        }
    }

    /*
     * 获取指定长度的字节信息
     * @param $length int 长度
     * @param $postion int|null 开始的位置，默认从上次的位置开始
     * @return string|false 成功返回二级制字节信息，失败返回false
     */
    public function getbyte($length = 1, $position = NULL){
        $output = array();

        if($length > $this->size - $this->position)
            return false;

        if($position == NULL)
            $position = $this->position;

        $data = substr($this->rawData,$position,$length);
        $this->position += $length;

        if($length == 1)
            $output = unpack("C",$data);
        else if($length == 2)
            $output = unpack("S",$data);
        else if($length == 4)
            $output = unpack("L",$data);
        else
            $output[1] = $data;

        return $output[1];
    }

    /*
     * 根据字节获取二进制获取标记名称
     */
    public function get_marker($byte){
        foreach($this->headermarker as $name=>$bit){
            if(is_array($bit)){
                if(in_array($byte,$bit)) return $name;
            }else{
                if($byte == $bit) return $name;
            }
        }
        return NULL;
    }

    /*
     * 解析JPEG数据，把各个分段数据存储到数组结构中
     */
    public function parse(){
        if($this->isJPEG == false) return false;

        while(($byte = $this->getbyte()) !== false):
            if($byte == 0xff):
                $nxtbyte = $this->getbyte();
                $marker = $this->get_marker($nxtbyte);

                if($marker == 'eoi'){
                    break;
                //可变长度的字段
                }elseif($marker == 'sof0' || $marker == 'sof2' || $marker == 'dht' || $marker == 'dqt' || $marker == 'app' || $marker == 'com'){
                    $datalen = $this->getbyte(1)*256 + $this->getbyte(1) - 2;
                    $data = $this->getbyte($datalen);
                    $headerdata = $data;
                    $this->headerData[$nxtbyte][]= $headerdata;
                }
                //DRI
                elseif($marker == 'dri'){
                    $this->headerData[$nxtbyte][] = $this->getbyte(4);
                }
                //rst
                elseif($marker == 'rst'){
                    $this->headerData[$nxtbyte][] = '';
                }
                //JPEG主体数据
                elseif($marker == 'sos'){
                    while(($byte = $this->getbyte()) !== false){
                        if($byte == 0xff){
                            $nxtbyte = $this->getbyte();
                            if($nxtbyte == 0xd9) break;
                            else $this->scanData .= pack('C',$byte).pack('C',$nxtbyte);
                        }else{
                            $this->scanData .= pack('C',$byte);
                        }
                    }
                }
            endif;
        endwhile;
    }

    /*
     * 根据JPEG的所有头部二进制数据
     */
    public function getheaderdata(){
        $buffer = "";
        foreach($this->headerData as $d){
            foreach($d as $c){
                $buffer .= $c;
            }
        }
        return $buffer;
    }

    /*
     * 压缩图片
     */
    public function compress(){
        $this->clear_com();
        $this->clear_app();
    }

    /*
     * 移除JPEG COM数据段
     */
    public function clear_com(){
        unset($this->headerData[0xfe]);
    }

    /*
     * 移除JPEG的app段
     * 由于JFIF在0xe0上，这段不予移除
     */
    public function clear_app(){
        foreach($this->headermarker['app'] as $code)
            if($code != 0xe0)
                unset($this->headerData[$code]);
    }

    /*
     * 增加JPEG COM数据段
     */
    public function add_com($string){
        $this->headerData[0xfe][] = $string;
        return true;
    }

    /*
     * 增加JPEG APP数据段
     */
    public function add_app($string){
        $appmarker = 0xe1;
        foreach($this->headerData as $markercode=>$datas){
            if(in_array($markercode,$this->headermarker['app']))
                $appmarker++;
        }
        if($appmarker > 0xef)
            return false;
        $this->headerData[$appmarker][] = $string;
        return true;
    }

    /*
     * 获取压缩之后的二进制数组
     */
    public function getbin(){
        if(!$this->isJPEG)
            return $this->rawData;
        $bin = pack('S',$this->marker);
        foreach($this->headerData as $markercode=>$datas){
            foreach($datas as $data){
                $datalen = strlen($data) + 2;
                $markername = $this->get_marker($markercode);
                if(in_array($markername,array('sof0','sof2','dht','dqt','app','com')))
                    $bin .= pack('C',0xff).pack('C',$markercode).pack('n',$datalen).$data;
                elseif($markername == 'dri')
                    $bin .= pack('C',0xff).pack('C',$markercode).pack('L',$data);
                elseif($markername == 'rst')
                    $bin .= pack('C',0xff).pack('C',$markercode).$data;
            }
        }
        $bin .= pack('C',0xff).pack('C',0xda).$this->scanData.pack('C',0xff).pack('C',0xd9);
        return $bin;
    }

    /*
     * 覆盖保存原有图片
     */
    public function storage(){
        if(!empty($this->file)){
            $data = $this->getbin();
            return file_put_contents($this->file, $data, EX_LOCK);
        }
        return false;
    }
}
