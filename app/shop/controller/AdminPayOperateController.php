<?php
namespace app\shop\controller;

use api\shop\model\WxUserModel;
use app\common\enum\SharingStatus;
use app\common\model\PayOperateModel;
use app\common\model\SharingActiveModel;
use app\shop\model\OrdersModel;
use cmf\controller\AdminBaseController;
use app\shop\model\ProductModel;
use app\common\enum\OrderStatus;

class AdminPayOperateController extends AdminBaseController
{
    private $searchArray;
    public function index()
    {
        $this->searchArray['mobile'] = $this->request->param('mobile', '', 'trim');
        $mod = new PayOperateModel();
        $userMod = new \app\common\model\WxUserModel();
        $model = $mod->getModel()->alias('po')->field('po.*, u.id user_id, u.mobile, u.nick_name');
        $model->leftJoin('wx_user u', 'po.user_id = u.id');
        if(!empty($this->searchArray['mobile'])){
//            $user = $userMod::where('mobile', '=', $this->searchArray['mobile'])->find();
//            if(!empty($user->id)){
//                $model->where('user_id', '=', $user->id);
//            }else{
//                $model->get(0);
//            }
            $model->where('u.mobile', '=', $this->searchArray['mobile']);
        }
        $data = $model->paginate(30)->appends($this->searchArray);
//        for($i=0;$i<$data->count();$i++){
//            if(!empty($data[$i]['user_id'])){
//                $user = $userMod::get(['id', '=', $data[$i]['user_id']]);
//                $data[$i]['nick_name'] = !empty($user->nick_name) ? $user->nick_name : '';
//                $data[$i]['mobile'] = !empty($user->mobile) ? $user->mobile : '';
//            }
//        }
        $this->assign('sharing_map', SharingStatus::getSharingMap());
        $this->assign('status_map', OrderStatus::getStatusMap());
        $this->assign('pay_status_map', OrderStatus::getPayStatusMap());
        $this->assign('pay_type_map', OrderStatus::getPayTypeMap());
        $this->assign('mobile', $this->searchArray['mobile']);
        $this->assign('data', $data);
        $this->assign('page', $data->render());
        $this->assign('exports', http_build_query($this->searchArray));
        return $this->fetch();
    }
    public function view(){
        $id = $this->request->param('id');
        $sharingMod = new PayOperateModel();
        $data = $sharingMod::get(['id', '=', $id]);
        $userMod = new \app\common\model\WxUserModel();
        $user_info = $userMod::get(['id', '=', $data->user_id]);
        $this->assign('data', $data);
        $this->assign('mobile', $this->searchArray['mobile']);
        $this->assign('user_info', $user_info);
        return $this->fetch();
    }
}