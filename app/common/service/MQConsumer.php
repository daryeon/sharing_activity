<?php
namespace app\common\service;
use think\facade\Config;

class MQConsumer extends MQ
{
    protected $consumer = null;
    protected static $MQConsumer = null;

    public function __construct($topic,$tag)
    {
        if(!isset($this->consumer)){
            $env = Config::pull('env');
            if(!empty($env['env']) && $env['env'] == 'test'){
                $topic = $topic.'Test';
            }
            $data = Config::pull('alimq');
            parent::__construct($data[$topic]);
            // 您在控制台创建的 Consumer ID(Group ID)
            $groupId = $data[$topic]['groupId'];
            $this->consumer = $this->client->getConsumer($this->instanceId, $this->topic,$groupId,$tag);
        }
    }

    public static function getSingle($topic,$tag=null)
    {
        if(!isset(self::$MQConsumer[$topic])){
            self::$MQConsumer[$topic]=new self($topic,$tag);
        }
        self::$MQConsumer[$topic];
    }

    //参数之后再定
    public function run($callback)
    {
        // 在当前线程循环消费消息，建议是多开个几个线程并发消费消息
        while (True) {
            try {
                // 长轮询消费消息
                // 长轮询表示如果topic没有消息则请求会在服务端挂住3s，3s内如果有消息可以消费则立即返回
                $messages = $this->consumer->consumeMessage(
                    3, // 一次最多消费3条(最多可设置为16条)
                    3 // 长轮询时间3秒（最多可设置为30秒）
                );
            } catch (\Exception $e) {
                if ($e instanceof \MQ\Exception\MessageNotExistException) {
                    // 没有消息可以消费，接着轮询
                    //printf("No message, contine long polling!RequestId:%s\n", $e->getRequestId());
                    continue;
                }
                //print_r($e->getMessage() . "\n");
                sleep(3);
                continue;
            }
            // 处理业务逻辑
            $receiptHandles = array();
            foreach ($messages as $message) {
                $receiptHandles[] = $message->getReceiptHandle();
//                printf("MessageID:%s TAG:%s BODY:%s \nPublishTime:%d, FirstConsumeTime:%d, \nConsumedTimes:%d, NextConsumeTime:%d\n",
//                    $message->getMessageId(), $message->getMessageTag(), $message->getMessageBody(),
//                    $message->getPublishTime(), $message->getFirstConsumeTime(), $message->getConsumedTimes(), $message->getNextConsumeTime());
                $msg =  $message->getMessageBody();
                $tag =  $message->getMessageTag();

                if(is_callable($callback)){
                    call_user_func($callback,$msg,$tag);
                }
            }

            // $message->getNextConsumeTime()前若不确认消息消费成功，则消息会重复消费
            // 消息句柄有时间戳，同一条消息每次消费拿到的都不一样
            //print_r($receiptHandles);

            try {
                $this->consumer->ackMessage($receiptHandles);
            } catch (\Exception $e) {
                if ($e instanceof MQ\Exception\AckMessageException) {
                    // 某些消息的句柄可能超时了会导致确认不成功
//                    printf("Ack Error, RequestId:%s\n", $e->getRequestId());
//                    foreach ($e->getAckMessageErrorItems() as $errorItem) {
//                        printf("\tReceiptHandle:%s, ErrorCode:%s, ErrorMsg:%s\n", $errorItem->getReceiptHandle(), $errorItem->getErrorCode(), $errorItem->getErrorCode());
//                    }
                }
            }
        }
    }

}
