<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: 小夏 < 449134904@qq.com>
// | Date: 2019/03/25
// | Time:下午 04:41
// +----------------------------------------------------------------------


namespace plugins\chat_demo;


use cmf\lib\Plugin;
use think\Db;

class ChatDemoPlugin extends Plugin
{
    public $info = [
        'name'        => 'ChatDemo',//Demo插件英文名，改成你的插件英文就行了
        'title'       => '聊天室demo',
        'description' => '聊天室demo',
        'status'      => 1,
        'author'      => '小夏',
        'version'     => '1.0',
        'demo_url'    => 'http://im.yyw66.cn',
        'author_url'  => 'http://www.thinkcmf.com'
    ];

    public $hasAdmin = 1;//插件是否有后台管理界面

    /**
     * 插件安装
     * @return bool
     * @throws \think\Exception
     */
    public function install()
    {
        $dbConfig = \think\facade\Config::pull('database');
        $dbSql    = cmf_split_sql(WEB_ROOT . 'plugins/' . 'chat_demo/data/cmf_plugin_chat_user.sql', $dbConfig['prefix'], $dbConfig['charset']);

        //检查合法性
        if (empty($dbConfig) || empty($dbSql)) {
            return false;
        }
        $db = Db::connect($dbConfig);
        foreach ($dbSql as $key => $sql) {
            try {
                $db->execute($sql);
            } catch (\Exception $e) {
                return false;
            }
        }
        return true;
    }

    /**
     * 插件卸载
     * @return bool
     * @throws \think\Exception
     */
    public function uninstall()
    {
        ///读取数据库配置内容
        $dbConfig = \think\facade\Config::pull('database');
        $sql      = 'DROP' . ' TABLE IF EXISTS ' . $dbConfig['prefix'] . 'plugin_chat_user';

        //检查合法性
        if (empty($dbConfig) || empty($sql)) {
            return false;
        }
        $db = Db::connect($dbConfig);
        // 启动事务
        Db::startTrans();
        try {
            $db->execute($sql);
            Db::commit();
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * 实现的自动连接时操作钩子方法
     * @param $param
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function swooleWebsocketOnOpen($param)
    {

        $server  = $param[0];
        $request = $param[1];

        Db::name('plugin_chat_user')
            ->data([
                'chat_id'       => $request->fd,
                'nickname'      => '用户' . $request->fd,
                'last_login_ip' => $request
            ])
            ->insert();
        if ($server->isEstablished($request->fd)) {
            $server->push($request->fd, json_encode([
                'my_id'      => $request->fd,
                "message_id" => $request->fd,
                'nickname'   => '用户' . $request->fd,
                "event"      => "index"
            ]));
        }
        $chatUser = Db::name('plugin_chat_user')->select();
        $count    = Db::name('plugin_chat_user')->where('type', 1)->count('id');

        $data = json_encode([
            'user'  => $chatUser,
            'count' => $count,
            "event" => "user_info"
        ]);


        foreach ($server->connections as $fd) {
            //判断链接是否建立
            if (!$server->isEstablished($fd)) {
                continue;
            }
            $server->push($fd, $data);
        }

    }

    /**
     * 断开链接自动删除用户
     * @param $param
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function swooleWebsocketOnClose($param)
    {
        $server  = $param[0];
        $request = $param[1];

        Db::name('plugin_chat_user')->where('chat_id', $request)->delete();
        $chatUser = Db::name('plugin_chat_user')->select();
        $count    = Db::name('plugin_chat_user')->where('type', 1)->count('id');
        $data     = json_encode([
            'user'  => $chatUser,
            'count' => $count,
            "event" => "user_info"
        ]);

        foreach ($server->connections as $fd) {
            //判断链接是否建立
            if (!$server->isEstablished($fd)) {
                continue;
            }

            $server->push($fd, $data);
        }
    }
}