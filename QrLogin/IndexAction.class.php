<?php
//首页类
class IndexAction extends CommonAction{
    /*
     * 首页界面（生成随机二维码）
     */
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
}