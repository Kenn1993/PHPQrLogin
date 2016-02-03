# PHP+移动端模拟微信网页版登录
[![Support](https://img.shields.io/badge/support-PHP-blue.svg?style=flat)](http://www.php.net/)
[![Support](https://img.shields.io/badge/support-ThinkPHP-red.svg?style=flat)](http://www.thinkphp.cn/)
[![Support](https://img.shields.io/badge/support-phpqrcode-yellow.svg?style=flat)](http://phpqrcode.sourceforge.net/)
[![Support](https://img.shields.io/badge/support-redis-green.svg?style=flat)](http://redis.io/)

## 原理介绍
1. php自动生成随机数；
2. 利用phpqrcode类根据随机数生成二维码输出到网页前端显示（随机数越长越复杂二维码的密集程度越高）；
3. 移动端扫描二维码后将二维码中的随机数和用户ID作为参数调用php接口，php接口实现将用户ID和随机数存入redis缓存；
4. 网页前端设置一个定时器，隔一段时间调用php方法查看redis中是否存在该随机数，如果存在，则获取该用户ID实现登录并删除缓存和二维码图片，如果不存在，则继续等待；

## 浅谈phpqrcode类
这是一个php扩展类，能根据设定参数（二维码内容、大小、中间logo）等自动生成二维码，相对其他第三方自动生成二维码的接口它生成二维码速度更快更稳定（因为需要跨域调用第三方接口，还需要承担第三方接口失效的风险）。

## 浅谈redis缓存
redis是一个key-value缓存系统,和Memcached类似。但他相对Memcached拥有更多的存储类型，以下则是它的几种类型和使用情况。

1. string(字符串)：适用于简单的key-value储存，没有太多的数据逻辑
2. list(链表)：相当于一个索引数组，一个key对应多个value,适用于队列操作，注意数据(队员名)拿出队列后将丢失!
3. set(集合)：也相当于一个索引数组，一个key对应多个value,相对队列集合不能从左右两边拿到数据，通常用于查看该value是否存在这个集合之中，获取该集合所有value。
4. zset(有序集合)：相当于一个关联数组，一个key对应多个拥有键名的value,而且“键名”是一个分数作为排序根据，可以操作有序集合进行分数的增加或减少，每次有序集合数据变化都会自动排序，非常适用于排行榜。
5. hash（哈希类型）：相当于一个简单的关系型数据表，hashkey-key-value的结构，适用于存储数据逻辑复杂的情况。**本次这运用了hash类型进行对随机数和用户Id的缓存，可以理解为是一张二维码对应表，其中有2个字段，一个是随机数，一个是用户Id，每次扫描二维码都插入一条新的记录，然后根据该随机数来查询对应的用户Id来实现登录。**


## 代码详情
### 1、生成随机数、利用phpqrcode类生成二维码显示到web前端
	 public function index(){
		//生成随机数
		$rand = '';
		for($i=0;$i<8;$i++){
			$rand.=rand(0,9);
		}
		//实例化phpqrcode类
		require THINK_PATH.'Extend/Library/ORG/Util/phpQrcode/phpqrcode.php';
		$errorCorrectionLevel = 'M';//容错级别
		$matrixPointSize = 7.4;//生成图片大小
		//生成二维码图片
		QRcode::png($rand, '/mnt/mfs/jmh_data/img/Qrcode/qrcode.png', $errorCorrectionLevel, $matrixPointSize, 2);
		$logo = '/mnt/mfs/jmh_data/img/Qrcode/logo.png';//准备好的logo图片
		$QR = '/mnt/mfs/jmh_data/img/Qrcode/qrcode.png';//已经生成的原始二维码图
		if ($logo !== FALSE) {
			$QR = imagecreatefromstring(file_get_contents($QR));
			$logo = imagecreatefromstring(file_get_contents($logo));
			$QR_width = imagesx($QR);//二维码图片宽度
			$QR_height = imagesy($QR);//二维码图片高度
			$logo_width = imagesx($logo);//logo图片宽度
			$logo_height = imagesy($logo);//logo图片高度
			$logo_qr_width = $QR_width / 5;
			$scale = $logo_width/$logo_qr_width;
			$logo_qr_height = $logo_height/$scale;
			$from_width = ($QR_width - $logo_qr_width) / 2;
			//重新组合图片并调整大小
			imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width,$logo_qr_height, $logo_width, $logo_height);
		}
		//输出图片
		imagepng($QR,'/mnt/mfs/jmh_data/img/Qrcode/jmhqrcode_'.$rand.'.png');
		$this->assign('rand',$rand);
		$this->display();
	}
### 2、移动端扫描二维码，调用php接口将用户ID和随机数存入redis
    /*
     * 客户端把用户ID写入缓存
     * 参数1:rand 随机数 *
     * 参数2:encryptId 加密用户ID *
     */
    public function exportData(){
	    //接收参数
	    $rand = $this->chkParamInt('rand',true);
	    $encryptId = $this->chkParamStr('encryptId',true);
	    //实例化redis
	    if(!class_exists("SDKcache")){
		    //导入缓存类库
		    require_once dirname ( __FILE__ ) . '/include/SDKcache.class.php';
	    }
	    $redisObj = new SDKcache ( $this->config['cache_redis'] );
	    //拼接缓存键
	    $qrExportCacheKey = 'qrExportCache';
	    //存入hash表
	    $re = $redisObj->hSet($qrExportCacheKey,$rand,$encryptId);
	    unset($redisObj);
	    //删除二维码图片
	    unlink('***.png');
	    if($re){
	    $this->resultType(1,'','成功');
	    }else{
	    $this->resultType(0,'失败','',400);
	    }
    }
### 3、web前端设置延时调用php查看该随机数是否存在于redis，存在则ajax返回用户ID到前端实现登录跳转
    /*
     * 根据随机数从缓存获取用户Id
     * 参数1:rand 随机数 *
     */
    public function getEncryptId(){
	    //接收参数
	    $rand = $this->chkParamInt('rand',true);
	    //实例化redis
	    if(!class_exists("SDKcache")){
	    //导入缓存类库
	    require_once dirname ( __FILE__ ) . '/include/SDKcache.class.php';
	    }
	    $redisObj = new SDKcache ( $this->config['cache_redis'] );
	    //拼接缓存键
	    $qrExportCacheKey = 'qrExportCache';
	    //获取缓存内容
	    $re = $redisObj->hGet($qrExportCacheKey,$rand);
	    if($re){
		    //删除缓存
		    $redisObj->hDel($qrExportCacheKey,$rand);
		    unset($redisObj);
		    $result=array();
		    $result['status']=1;
		    $result['encryptId'] = $re;
		    }else{
		    unset($redisObj);
		    $result=array();
		    $result['status']=0;
	    }
	    echo json_encode($result);
    }