<?php

namespace app\http\middleware;

class UserLogin
{
    public function handle($request, \Closure $next)
    {
        echo 111;die;
    }
}