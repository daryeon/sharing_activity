<?php
namespace app\shop\controller;

use cmf\controller\AdminBaseController;

class AdminIndexController extends AdminBaseController
{
    public function index()
    {
        return $this->fetch();
    }
}