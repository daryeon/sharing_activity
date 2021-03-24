<?php


namespace app\shop\middleware;


use app\shop\model\ProductLogModel;
use think\db\Query;
use think\facade\Middleware;
use app\common\service\RedisService;

class AdminAfter extends Middleware
{
    public function handle($request, \Closure $next)
    {
        $redisSer = new RedisService();
        $response = $next($request);
        $path = $request->path();
        //TODO 对特定控制器记录操作日志
        if (strpos($path,'admin_product') !== false ){
            $log = [];
            switch ($path) {
                case (strpos($path,'edit') !== false) :
                    $action = '编辑';
                    break;
                case (strpos($path,'add') !== false) :
                    $action = '新增';
                    break;
                case (strpos($path,'del') !== false) :
                    $action = '删除';
                    break;
                default :
                    return $response;
            }

            $token = $request->request('csrf_token');
            $log['old'] = @json_decode($redisSer->get($token),true);
            $new_post = $request->param('post');
            $log['main_id'] = $request->param('id');
            switch ($path) {
                case (strpos($path,'editpost') !== false) :
                    $log['action'] = '编辑';
                    if (isset($new_post['id'])){
                        $log['main_id'] = $new_post['id'];
                    }
                    if (isset($new_post['name'])){
                        $log['diff']['name'] = $new_post['name'];
                    }
                    break;
                case (strpos($path,'addpost') !== false) :
                    $log['action'] = '新增';
                    if ( $request->isPost() && !empty( $response->getData()) ){
                        $resp_arr = $response->getData();
                        if (isset($new_post['name'])){
                            $log['diff']['name'] = $new_post['name'];
                        }
                        $log['main_id'] = @$resp_arr['data']['id'];
                    }
                    break;
                case (strpos($path,'del') !== false) :
                    $log['action'] = '删除';
                    break;
                default :
                    return $response;
            }
            $log['diff'] = $new_post;
            $log['path'] = $path;
            $log['diff'] = @json_encode($log['diff']);
            $log['old'] = @json_encode($log['old']);
            $log['manager'] = session('name');
            $log['update_time'] = time();
            $mod = new ProductLogModel();
            $mod->create($log);
        }
        return $response;
    }
}