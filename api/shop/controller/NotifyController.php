<?php
namespace api\shop\controller;

use api\shop\controller\RestBaseController;
use app\common\service\HuilaimiPay;
use think\App;


class NotifyController extends RestBaseController
{
    function __construct(App $app = null)
    {
        parent::__construct($app);
    }
    public function index(){
        $pay = new HuilaimiPay();
        $pay->notify($this->request->param());
    }
}