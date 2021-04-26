<?php
namespace app\shop\controller;

use api\shop\model\WxUserModel;
use app\common\enum\SharingStatus;
use app\common\model\OrderLogModel;
use app\common\model\PayLogModel;
use app\common\service\HuilaimiPay;
use app\shop\model\GroupActivityModel;
use app\shop\model\GroupProductModel;
use app\shop\model\SharingActiveModel;
use app\shop\model\SharingActiveUsersModel;
use app\shop\model\OrdersModel;
use cmf\controller\AdminBaseController;
use app\shop\model\ProductModel;
use app\common\enum\OrderStatus;

class AdminSharingController extends AdminBaseController
{
    private $searchArray;
    public function index()
    {
        $this->searchArray['search_key'] = $this->request->param('search_key', '', 'trim');
        $this->searchArray['search_value'] = $this->request->param('search_value', '', 'trim');
        $this->searchArray['tid'] = $this->request->param('tid', '', 'trim');
        $this->searchArray['status'] = $this->request->param('status', -1, 'intval');
        $this->searchArray['start_time'] = $this->request->param('start_time', '', 'trim');
        $this->searchArray['end_time'] = $this->request->param('end_time', '', 'trim');
        $sharingMod = new SharingActiveModel();
        $model = $sharingMod->getModel()->alias('sa')
            ->field('sa.*, gp.name gpname, ga.name ganame, ga.id activity_id, ga.end_time activity_time');
        $model->leftJoin('group_product gp', 'sa.goods_id = gp.id')
            ->leftJoin('group_activity ga', 'gp.activity_id = ga.id');
        $sauMod = new SharingActiveUsersModel();
        $userMod = new \app\common\model\WxUserModel();
        if(isset($this->searchArray['status']) && $this->searchArray['status'] > 0){
            $model->where('sa.status', '=', $this->searchArray['status']);
        }
        if(!empty($this->searchArray['search_key']) && !empty($this->searchArray['search_value'])){
            switch ($this->searchArray['search_key']){
                case 'active_id':
                    $model->where('sa.id', '=', $this->searchArray['search_value']);
                    break;
                case 'tid':
                    $sharing_active_user = $sauMod->where('tid', '=', $this->searchArray['search_value'])->find();
                    if(!empty($sharing_active_user)){
                        $model->where('sa.id', '=', $sharing_active_user['active_id']);
                    }else{
                        $model->where('sa.id', '=', 0);
                    }
                    break;
                case 'goods_name':
                    $model->where('gp.name', 'LIKE', '%'.$this->searchArray['search_value'].'%');
                    break;
                case 'mobile':
                    $wx_user = $userMod->where('mobile', '=', $this->searchArray['search_value'])->find();
                    $ids = [];
                    if(!empty($wx_user)){
                        $wx_users = $sauMod->where('user_id', '=', $wx_user['id'])->select();
                        if(!empty($wx_users)){
                            $ids = array_map(function($u){return $u['active_id'];}, $wx_users->toArray());
                        }
                    }
                    if(!empty($ids)){
                        $model->whereIn('sa.id', $ids);
                    }else{
                        $model->where('sa.id', '=', 0);
                    }
                    break;
                case 'activity_name':
                    $model->where('ga.name', 'LIKE', '%'.$this->searchArray['search_value'].'%');
                    break;
            }
        }
        if(!empty($this->searchArray['start_time'])){
            $model->where('sa.create_time', '>=', strtotime($this->searchArray['start_time']. ' 0:0:0'));
        }
        if(!empty($this->searchArray['end_time'])){
            $model->where('sa.create_time', '<=', strtotime($this->searchArray['end_time'] . ' 23:59:59'));
        }
        $data = $model->paginate(20)->appends($this->searchArray);
        $sauMod = new SharingActiveUsersModel();
//        $groupProductMod = new GroupProductModel();
//        $groupActivityMod = new GroupActivityModel();
        $sharing_active_users = [];
        for($i=0;$i<$data->count();$i++){
            if(!empty($data[$i]['id'])){
//                $groupProduct = $groupProductMod::get(['id', '=', $data[$i]['goods_id']]);
//                $groupActivity = $groupActivityMod::get(['id', '=', $groupProduct['activity_id']]);
                $sharingActiveUsers = $sauMod->where('active_id', '=', $data[$i]['id'])->select();
                $sharing_active_users[$data[$i]['id']] = $sharingActiveUsers->toArray();
//                $data[$i]['goods_name'] = $groupProduct['name'];
//                $data[$i]['activity_id'] = $groupProduct['activity_id'];
//                $data[$i]['activity_name'] = $groupActivity['name'];
                $data[$i]['leave_day'] = ceil(($data[$i]['end_time'] - time()) / 86400);
                if($data[$i]['activity_time'] < $data[$i]['end_time']){
                    $data[$i]['clustering_time'] = $data[$i]['activity_time'];
                }else{
                    $data[$i]['clustering_time'] = $data[$i]['end_time'];
                }
                if($data[$i]['clustering_time']>time()){
                    $blance_second = $data[$i]['clustering_time'] - time();
                    $day = floor($blance_second/(3600*24));
                    $second = $blance_second%(3600*24);
                    $hour = floor($second/3600);
                    $second = $second%3600;
                    $minute = floor($second/60);
                    $second = $second%60;
                    $data[$i]['clustering_at'] = "{$day}天 {$hour}时{$minute}分{$second}秒";
                }else{
                    $data[$i]['clustering_at'] = '0天 0时0分0秒';
                }
            }
        }
        $this->assign('sharing_map', SharingStatus::getSharingMap());
        $this->assign('status_map', OrderStatus::getStatusMap());
        $this->assign('pay_status_map', OrderStatus::getPayStatusMap());
        $this->assign('pay_type_map', OrderStatus::getPayTypeMap());
        $this->assign('search_key', $this->searchArray['search_key']);
        $this->assign('search_value', $this->searchArray['search_value']);
        $this->assign('start_time', $this->searchArray['start_time']);
        $this->assign('end_time', $this->searchArray['end_time']);
        $this->assign('tid', $this->searchArray['tid']);
        $this->assign('status', $this->searchArray['status']);
        $this->assign('sharing_active_users', $sharing_active_users);
        $this->assign('data', $data);
        $this->assign('page', $data->render());
        $this->assign('exports', http_build_query($this->searchArray));
        return $this->fetch();
    }

    public function close(){
        $active_id = $this->request->param('active_id', 0, 'intval');
        if(empty($active_id)){
            $this->error('缺少参数~');
        }
        $groupProductModel = new GroupProductModel();
        $wxUserModel = new WxUserModel();
        $payLogMod = new PayLogModel();
        $orderMod = new OrdersModel();
        $orderLogMod = new OrderLogModel();
        $pay = new HuilaimiPay();
        $sharingActiveModel = new SharingActiveModel();
        $sharingActiveUsersModel = new SharingActiveUsersModel();
        $sharingActive = $sharingActiveModel->getSharingActiveByActiveId($active_id);
        if(empty($sharingActive)){
            $this->error('拼团不存在');
        }
        if($sharingActive['status'] != SharingStatus::SHARING_ING){
            $this->error('该团已关闭或不是拼团中');
        }
        if($sharingActive['end_time'] <= time()){
            $this->error('该团已结束');
        }
        $sharingActiveUsers = $sharingActiveUsersModel->getSharingActiveUsersByActiveId($sharingActive['id']);
        foreach($sharingActiveUsers as $sharingActiveUser){
            $order = $orderMod->where('tid', '=', $sharingActiveUser['tid'])->find();
            if(empty($order)){
                // 订单不存在或已取消
                continue;
            }
            if($order['status'] == OrderStatus::CANCEL){
                // 订单不存在或已取消
                continue;
            }
            if($order['pay_status'] != OrderStatus::BUYER_IS_PAY){
                // 订单未支付
                continue;
            }
            if(empty($order['transaction_id'])){
                // 订单流水不存在
                continue;
            }
            try{
                $old_order = $order->toArray();
                $payLog = $payLogMod->getRefundPayIdByTid($sharingActiveUser['tid']);
                if(empty($payLog)){
                    $orderMod->where('tid', '=', $order['tid'])->update([
                        'status' => OrderStatus::CANCEL, 'update_time' => time(),
                        'refund_state' => OrderStatus::REFUND_FAIL, 'refund_time' => time(),
                    ]);
                    $order['status'] = OrderStatus::CANCEL;$order['desc'] = '手动关闭拼团订单取消';
                    $orderLogMod->addOrderLog($order, $old_order, session('name'), 'cancel');
                    // 支付日志不存在
                    continue;
                }
                $ret = $pay->refund($sharingActiveUser['user_id'], $payLog['pay_id'], $order['transaction_id'], $order['pay_price']);
                if(!$ret['paid']){
                    $orderMod->where('tid', '=', $order['tid'])->update([
                        'status' => OrderStatus::CANCEL,
                        'update_time' => time(),'refund_state' => OrderStatus::REFUND_FAIL,
                        'refund_time' => time(), 'refund_message' => json_encode($ret)
                    ]);
                    $order['status'] = OrderStatus::CANCEL;$order['desc'] = '手动关闭拼团订单取消';
                    $orderLogMod->addOrderLog($order, $old_order, session('name'), 'cancel');
                    // 退款失败
                    continue;
                }
                $flag = $orderMod->where('tid', '=', $order['tid'])->update([
                    'update_time' => time(),'status' => OrderStatus::CANCEL,
                    'pay_status' => OrderStatus::ORDER_REFUND, 'refund_state' => OrderStatus::REFUND_SUCCESS,
                    'refund_time' => time(), 'refund_message' => json_encode($ret)
                ]);
                if($flag){
                    $order['status'] = OrderStatus::CANCEL;
                    $order['desc'] = '手动关闭拼团订单取消';
                    $orderLogMod->addOrderLog($order, $old_order, session('name'), 'cancel');

                    $user = $wxUserModel::get($order['user_id']);
                    if(!empty($user)){
                        $goods = $groupProductModel::get($order['item_id']);
                        $data = [];
                        $data['openid'] = $user['openid'];
                        $data['mobile'] = $user['mobile'];
                        $data['goods_name'] = !empty($goods['name']) ? $goods['name'] : '学车套餐';
                        $data['remarks'] = '超过规定时间未成团。抱歉，您发起/参与的拼团失败，您可以再次发起拼团并分享好友！';
                        $data['url'] = '/pages/group/groupDetail?group_id='.$sharingActiveUser['active_id'];
                        \app\common\service\MQProducer::getSingle('shop')->run($data, 'sharing_active_fail');
                    }
                }
            }catch(\Exception $e){
                $this->error($e->getMessage());
            }
        }

        $flag = $sharingActiveModel->where('id', '=', $sharingActive['id'])->update(['update_time' => time(), 'status' => SharingStatus::SHARINGFAIL]);
        if($flag){
            $flag = $orderMod->where('status', '<>', OrderStatus::CANCEL)->where('active_id', '=', $sharingActive['id'])->update(['status' => OrderStatus::CANCEL, 'update_time' => time()]);
            $this->success('该团已关闭');
        }else{
            $this->error('该团关闭失败');
        }
    }

    public function export(){
        $params = $this->request->param('params');
        $params = parse_url_params($params);
        $mod = new SharingActiveModel();
        $model = $mod->getModel();
        if(!empty($params['tid'])){
            $model->where('id', '=', $params['tid']);
        }
        if(isset($params['status'])){
            $model->where('status', '=', $params['status']);
        }
        $data = $model->paginate(10000);
        $rowsCount = $data->count();
        $rows = [];
        for($i=0;$i<$rowsCount;$i++){
            $data = $data->toArray()['data'];

            $row = [];
            $row[] = !empty($data[$i]['id']) ? $data[$i]['id'] : '';
            $row[] = !empty($data[$i]['goods_id']) ? $data[$i]['goods_id'] : '';
            $row[] = !empty($data[$i]['goods_id']) ? $data[$i]['goods_id'] : '';
            $row[] = date('Y-m-d H:i:s', $data[$i]['create_time']);
            $row[] = date('Y-m-d H:i:s', $data[$i]['end_time']);
            $row[] = date('H时i分s秒', $data[$i]['end_time'] - $data[$i]['create_time']);
            $row[] = !empty($data[$i]['people']) ? $data[$i]['people'] : 0;
            $row[] = !empty($data[$i]['actual_people']) ? $data[$i]['actual_people'] : 0;
            $row[] = SharingStatus::getSharingMap()[$data[$i]['status']];
            $rows[] = $row;

        }
        $title = ['拼单号','活动名称','商品名称','发起拼团时间','拼团结束时间','成团有效时间','成团人数','已参团人数','拼团状态'];
        export_excel(date('YmdHis').'_sharing.xls', $title, $rows);exit;
    }
}