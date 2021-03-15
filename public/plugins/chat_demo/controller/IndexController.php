<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: 小夏 < 449134904@qq.com>
// | Date: 2019/03/25
// | Time:下午 05:00
// +----------------------------------------------------------------------


namespace plugins\chat_demo\controller;


use cmf\controller\PluginBaseController;
use think\swoole\WebSocketFrame;

class IndexController extends PluginBaseController
{
    /**
     * @return mixed|string
     * @throws \Exception
     */
    public function index()
    {

        return $this->fetch('index');
    }

    /**
     * 消息发送
     */
    public function send()
    {
        $client = WebSocketFrame::getInstance();

        $userId  = $this->request->post('user_id');
        $message = $this->request->post('message');
        $myFd    = $client->getFrame()->fd;
        if (!empty($userId) && !empty($message)) {
            $data = [
                'data'  => [
                    "message_id" => $client->getFrame()->fd,
                    'nickname'   => '用户' . $client->getFrame()->fd,
                    'message'    => $message
                ],
                "event" => "message"
            ];
            foreach ($client->getServer()->connections as $fd) {
                if ($myFd != $fd) {
                    $client->sendToClient($fd, $data);
                }
            }
            /*$client->pushToClients([
                'data' => [
                    "message_id"   => $client->getFrame()->fd,
                    'nickname' => '用户' . $client->getFrame()->fd,
                    'message'=>$message
                ],
                "event"   => "message"
            ]);//参数为数组，字符串，数字*/
        }
    }
}