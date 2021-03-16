<?php
namespace api\shop\service;
use Guzzle\Http\Client;
use \think\Log;
use \think\App;
use app\common\service\RedisService;
class WxAppService{

    static $ins = NULL;
    var $client = null;
    var $config = [];
    public static function getInstance()
    {
        if(!self::$ins)
        self::$ins = new WxAppService();
        return self::$ins;
    }
    function __construct()
    {
        $this->client = new Client('https://api.weixin.qq.com');
        $this->config['appid'] = config('wxapp.appid');
        $this->config['secret'] = config('wxapp.appsecret');

        $this->log = (new Log(new App()))->init(['type' => 'File', 'path' => '/tmp/logs/wxapp/']);
    }


    /*
     * 开放消息中心用
     * todo redis设置time
     */
    public function getAccessToken($invalid = false) {
        $redisSer = new RedisService();
        $redisKey = 'wxapp:access_token';
        if($invalid){
            $redisSer->del($redisKey, null);
        }
        $cachedToken = $redisSer->get($redisKey);
        if(!empty($cachedToken)){
            return $cachedToken;
        }
        $appid = $this->config['appid'];
        $secret = $this->config['secret'];
        $url = "/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$secret}";
        $res = $this->client->get($url, [
            'headers' => [
                'Accept'     => 'application/json',
            ]])->send();
        $body = $res->getBody();
        $data = json_decode($body);
        if(!empty($data->access_token)){
            $redisSer->set($redisKey, $data->access_token);
            return $data->access_token;
        }else{
            return null;
        }
    }

    private function postJsonDecode($url, $params) {
        try{
            $res = $this->client->post($url, [
                    'Accept'     => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                json_encode($params)
            );
            $_res = $res->send();
            $body = $_res->getBody();
            return json_decode($body);
        }catch(\Exception $e){
            $this->log->write('['.__FUNCTION__.']'.$e->getMessage(), 'error');
            return false;
        }
    }

    private function post($url, $params) {
        try{
            $res = $this->client->post($url, [
                    'Accept'     => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                json_encode($params)
            );
            $_res = $res->send();
            $body = $_res->getBody();
            return $body;
        }catch(\Exception $e){
            $this->log->write('['.__FUNCTION__.']'.$e->getMessage(), 'error');
            return false;
        }
    }

    private function postByAccessToken($url, $params) {
        $token = $this->getAccessToken();
        $data = $this->post($url.'?access_token='.$token, $params);
        $dataJson = json_decode($data);
        if(!empty($dataJson) && ($dataJson->errcode == 40001 || $dataJson->errcode == 40014 || $dataJson->errcode == 42001 || $dataJson->errcode == 41001)){
            $token = $this->getAccessToken(true);
            return $this->post($url.'?access_token='.$token, $params);
        }
        return $data;
    }

    private function postByAccessTokenJsonDecode($url, $params) {
        $token = $this->getAccessToken();
        $data = $this->postJsonDecode($url.'?access_token='.$token, $params);
        if($data->errcode->errcode == 40001 || $data->errcode == 40014 || $data->errcode == 42001 || $data->errcode == 41001){
            $token = $this->getAccessToken(true);
            return $this->postJsonDecode($url.'?access_token='.$token, $params);
        }else{
            return $data;
        }
    }

    public function getSessionByCode($code)
    {
        $appid = $this->config['appid'];
        $secret = $this->config['secret'];
        $url = "/sns/jscode2session?appid={$appid}&secret={$secret}&js_code={$code}&grant_type=authorization_code";
        $res = $this->client->get($url, [
            'headers' => [
                'Accept'     => 'application/json',
            ]])->send();
        $body = $res->getBody();
        $data = json_decode($body);
        $this->log->write('['.__FUNCTION__.']'.json_encode($data), 'info');
        return $data;
    }

    public function getWxCodeUnlimit($page, $scene, $width = 430) {
        return $this->postByAccessToken('/wxa/getwxacodeunlimit', ['page' => $page, 'scene' => $scene, 'width' => $width]);
    }

    public function wxDataCrypt($encryptedData, $sessKey, $iv)
    {
        try{
            if (strlen($sessKey) != 24) {
                $this->log->write('['.__FUNCTION__.']sessionKey length wrong', 'error');
                return false;
            }
            $aesKey=base64_decode($sessKey);
            if (strlen($iv) != 24) {
                $this->log->write('['.__FUNCTION__.']iv length wrong', 'error');
                return false;
            }
            $aesIV=base64_decode($iv);
            $aesCipher=base64_decode($encryptedData);
            $result=openssl_decrypt( $aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);
            $data=json_decode($result);
            if(empty($data))
            {
                $this->log->write('['.__FUNCTION__.']data empty', 'error');
                return false;
            }
            if($data->watermark->appid != $this->config['appid'])
            {
                $this->log->write('['.__FUNCTION__.']watermark wrong: '.$data->watermark->appid, 'error');
                return false;
            }
            $this->log->write('['.__FUNCTION__.']return: '.json_encode($data), 'info');
            return $data;
        }catch(\Exception $e){
            $this->log->write('['.__FUNCTION__.']'.$e->getMessage(), 'error');
            return false;
        }
    }
}

?>
