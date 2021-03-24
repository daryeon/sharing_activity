<?php


namespace app\shop\middleware;


use think\facade\Middleware;

class AdminBefore extends Middleware
{
    public function handle($request, \Closure $next)
    {
        // 添加中间件执行代码
//        dump($request);
        return $next($request);
    }
}