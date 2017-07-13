# Responsive Image

>Responsive Image是一个能根据Web要求自动裁剪图片的工具，适用于当前Web碎片化环境下对图片的要求。

## Responsive Image有哪些功能
* 自动处理图片
    * 能够根据处理语法缩小图片
    * 能够根据处理语法裁剪图片
* 强大的缓存功能
    * 切割后的图片自动在硬盘上保存
    * HTTP缓存，节省加载时间和资源
* 图片盗用保护
    * 可以手动指定是否启动外链包好，禁止第三方盗用
    * 有基于IP,用户代理和Referer的三种白名单

## 如何部署
* 下载代理并部署到图片服务器上
* 把所有的图片访问都重定向到'Responsive Image'的'plm.php'文件，可参见示例.htaccess文件
* 创建一个图片缓存目录，并给于相应的权限
* 打开plm.php文件，对404页面，目录地址，HTTP缓存时间等进行配置

## 如何工作
Responsive Image 1.0版本提供两种方式的图片处理，缩减和裁剪图片，处理语法如如下:

缩减图片: 'reduce:[x,[y]]width,[height]'

裁剪图片: 'crop:[x,[y]]width,[height]'

[]表示可选;

x,y:代表开始开始的坐标点,默认为0,0;

width,height代表目标图片的宽度和高度，如height为空，系统按照原图片比例进行缩放或者裁剪。

### 示例操作:
* example.com/demo.jpg    请求demo.jpg
* example.com/demo.jpg/reduce:0,0,200,300  把demo.jpg从0,0开始缩放成200像素宽，300像素高的图片
* example.com/demo.jpg/crop:10,10,200,200  把demo.jpg从10,10开始剪裁出一个200像素宽的正方形图片
* example.com/demo.jpg/crop:10,300/reduce:100 把demo.jpg从10,0开始裁剪从一个和原图片等比例的宽为300像素的新图，然后缩放成宽为100像素。

## 问题反馈
在使用中有任何问题，欢迎反馈给我，可以用以下联系方式跟我交流

* 邮件(vip#hitoy.org)
* QQ: 478881958
* WeChat: hitoy\_

## 关于作者
* Name: hito
* Blog: https://www.hitoy.org/
