<?php
namespace app\common\service;
use MQ\Model\TopicMessage;
use think\facade\Config;

class MQProducer extends MQ
{
    protected $producer = null;
    protected  static $MQProducer = null;

    public function __construct($topic)
    {
        if(!isset($this->producer)){
            $env = Config::pull('env');
            if(!empty($env['env']) && $env['env'] == 'test'){
                $topic = $topic.'Test';
            }
            $data = Config::pull('alimq');
            parent::__construct($data[$topic]);
            $this->producer = $this->client->getProducer($this->instanceId, $this->topic);
        }
    }

    public static function getSingle($topic)
    {
        if(!isset(self::$MQProducer[$topic])){
            self::$MQProducer[$topic] =new self($topic);
        }
        return self::$MQProducer[$topic];
    }

    //参数之后再定
    public function run($msg,$tag=null)
    {
        //检查是否json
        if( !is_string($msg) ){
            $msg =  json_encode($msg);
        }

        try
        {

            $publishMessage = new TopicMessage(
                $msg// 消息内容
            );
            if($tag){
                $publishMessage->messageTag  =$tag;
            }
            $result = $this->producer->publishMessage($publishMessage);
            // print "Send mq message success. msgId is:" . $result->getMessageId() . ", bodyMD5 is:" . $result->getMessageBodyMD5() . "\n";

            if($result){
                return true;
            }
        } catch (\Exception $e) {
            print_r($e->getMessage() . "\n");
        }
    }

}
