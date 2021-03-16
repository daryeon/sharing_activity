<?php
namespace api\shop\service;
use OSS\OssClient;
use OSS\Core\OssException;
/**
*
*/
class OssService
{
    var $config;
    static $ins = NULL;
    public static function getInstance($config = []){
        if(!self::$ins)
        self::$ins = new OssService($config);
        return self::$ins;
    }
    private function log($msg){
        error_log($msg . PHP_EOL,3,sys_get_temp_dir().'/alioss.log');
    }
    function __construct($config = [])
    {
        $this->config = $config;
        if(empty($this->config)){
            $this->config = [
                'AccessKeyId' => config('oss.AccessKeyId'),
                'AccessKeySecret'=>config('oss.AccessKeySecret'),
                'Endpoint'=>config('oss.Endpoint'),
                'InterEndpoint'=>config('oss.InterEndpoint'),
                'UseInterEndpoint'=>config('oss.UseInterEndpoint'),
                'Public_Bucket'=>config('oss.Public_Bucket'),
                'Private_Bucket'=>config('oss.Private_Bucket'),
                'access_url' => config('oss.access_url'),
            ];
        }
    }
    public function getOssClient()
    {
        $accessKeyId = $this->config['AccessKeyId'];
        $accessKeySecret = $this->config['AccessKeySecret'];
        $endpoint = $this->config['Endpoint'];
        return new OssClient($accessKeyId, $accessKeySecret, $endpoint);
    }
    /*
     * 上传走内网 $type='private'
     * 读取走外网 $type='public'
     */
    private function get_oss_client(){
        $accessKeyId = $this->config['AccessKeyId'];
        $accessKeySecret = $this->config['AccessKeySecret'];
        $endpoint = $this->config['Endpoint'];

        if($this->config['UseInterEndpoint']){
            $endpoint = $this->config['InterEndpoint'];
        }
//        $this->log($endpoint);

        return new OssClient($accessKeyId, $accessKeySecret, $endpoint);
    }
    //上传文件使用文件名
    public function upload_file_by_path($filename,$file_path,$bucket = 'Public_Bucket'){
        try {
            $bucket = $this->config[$bucket];
            $ossClient = $this->get_oss_client();
            return $ossClient->multiuploadFile($bucket, $filename, $file_path);
        } catch (OssException $e) {
            throw new \think\Exception($e->getMessage(), 100006);
            return false;
        }


    }
    //上传文件 使用文件内容
    public function upload_file_by_content($filename,$file_content,$bucket = 'Public_Bucket'){
        try{
            $bucket = $this->config[$bucket];
            $ossClient = $this->get_oss_client();
            return $ossClient->putObject($bucket, $filename, $file_content);
        } catch (OssException $e) {
            $this->log($e->getMessage());
            return false;
        }

    }
    public function  get_file($key,$bucket = 'Private_Bucket'){
        $bucket = $this->config[$bucket];
        $ossClient = $this->get_oss_client();
        return $ossClient->getObject($bucket,$key);
    }
    public function getAccessUrl($file_name){
        return $this->config['access_url'] .  '/' .  $file_name;

    }
    public function getAuthUrl($key, $timeout = 60, $bucket = 'Private_Bucket')
    {
        $bucket = $this->config[$bucket];
        $ossClient = $this->get_oss_client();
        return $ossClient->signUrl($bucket, $key, $timeout);
    }
    public function getFileList($bucket = 'Private_Bucket', $options = null)
    {
        $bucket = $this->config[$bucket];
        $ossClient = $this->get_oss_client();
        return $ossClient->listObjects($bucket, $options);
    }
}

?>
