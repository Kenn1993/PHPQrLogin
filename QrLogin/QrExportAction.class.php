<?php
//二维码模块
class QrExportAction extends CommonAction{
    /*
     * 客户端把uiD写入缓存
     * 参数1:rand 随机数 *
     * 参数2:encryptId 加密用户ID *
     */
    public function exportData(){
        //接收参数
        $rand = $this->chkParamInt('rand',true);
        $encryptId = $this->chkParamStr('encryptId',true);
        //实例化SDK
        $countSDK = SDK::getSDK('count');
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
}