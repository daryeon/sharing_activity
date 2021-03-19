<?php
namespace app\common\service;

require __DIR__.'/../../../vendor/autoload.php';

use MQ\Config;
use MQ\Model\TopicMessage;
use MQ\MQClient;

abstract class MQ
{
    protected $client = null;
    protected $topic;
    protected $instanceId;

    /**
     * 初始化连接
     * 下面的参数之后使用配置文件的形式吧！
     */
    public function __construct($data)
    {
        if(!isset($this->client)) {
            $this->client = new MQClient(
            // 设置HTTP接入域名（此处以公共云生产环境为例）
                $data['url'],
                // AccessKey 阿里云身份验证，在阿里云服务器管理控制台创建
                $data['AccessKey'],
                // SecretKey 阿里云身份验证，在阿里云服务器管理控制台创建
                $data['SecretKey']
            );
        }
        // 所属的 Topic
        $this->topic = $data['topic'];
        // Topic所属实例ID，默认实例为空NULL
        $this->instanceId = "";
    }
}
