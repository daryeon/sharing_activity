<?php
namespace api\shop\controller;

use api\shop\model\CouponModel;
use api\shop\model\GroupActivityModel;
use api\shop\model\GroupProductModel;
use api\shop\model\GroupProductSkuModel;
use api\shop\model\SharingActiveModel;
use api\shop\model\SharingActiveUsersModel;
use app\common\enum\SharingStatus;
use app\common\model\OrdersModel;
use app\common\model\ProductModel;
use api\shop\controller\RestBaseController;
use app\common\enum\OrderStatus;
use app\common\service\ManageApi;
use think\facade\Config;
use think\App;


class SharingController extends RestBaseController
{
    function __construct(App $app = null)
    {
        parent::__construct($app);
    }
    public function index(){
        $active_id = $this->request->param('active_id', 0, 'intval'); // 参团人员
        $user_id = $this->getUserId();
        $user_info = $this->user;
        $orderModel = new OrdersModel();
        $productModel = new ProductModel();
        $groupProductModel = new GroupProductModel();
        $groupProductSkuModel = new GroupProductSkuModel();
        $groupActivityModel = new GroupActivityModel();

        $order = $orderModel->getOrderByUserId($user_id);
        $select_sku_path = '';
        $sign_url = Config::get('env.sign_url');
        $isKaiTuan = false;$sharing_goods = NULL;
        $status = 0;$is_stop = false;
        if(!empty($order)){
            $sign_url .= $order['tid'];
            if($order['order_type'] == OrderStatus::SHARING){
                $goods = $groupProductModel->getDetailNameById($order['item_id']);
            }else{
                $goods = $productModel->getDetailNameById($order['item_id']);
            }
            $order['goods_name'] = !empty($goods->name) ? $goods->name : '';
            $order['goods_image'] = !empty($goods->main_cover_src) ? $goods->main_cover_src : '';
            $order['status_text'] = OrderStatus::getStatusMap()[$order['status']];
            $order['pay_status_text'] = OrderStatus::getCenterPayStatusMap()[$order['pay_status']];
            $order['order_type_text'] = OrderStatus::getOrderType()[$order['order_type']];
            $order['create_time_text'] = date('Y md H:i', $order['create_time']);
//            if($order['pay_status'] == OrderStatus::BUYER_IS_PAY && $order['pay_time'] <= time() - 86400 * 2){
            if($order['pay_status'] == OrderStatus::BUYER_IS_PAY && $order['status'] == 1){
                $order['pay_status'] = 'WAIT_ORDER_APPLY';
                $order['pay_status_text'] = '订单审核中';
            }
            $service = [];
            $items = json_decode($order['item_message'], true);
            foreach($items as $item){
                // 规格
                if(!empty($item['path_text'])){
                    $order['skus'] = array_values(explode(',', $item['path_text']));
                    $select_sku_path = $item['path'];
                // 增值服务
                }else{
                    array_push($service,  ['key' => $item['name'], 'name' => OrderStatus::getServiceNameMap()[$item['name']], 'amount' => $item['amount']]);
                }
            }
            if($order['order_type'] == OrderStatus::SHARING && $order['pay_status'] == OrderStatus::WAIT_BUYER_PAY && $active_id == 0){
                $isKaiTuan = true;
                $sharing_goods = $groupProductModel->detail($order['item_id']);
                $skus = $groupProductSkuModel->skus($sharing_goods['id'], $sharing_goods['org_id']);
                $sharing_goods['sku'] = $this->_checkedSku($skus, $select_sku_path);
            }
            $order['down_time'] = 900 - (time() - $order['create_time']);
        }
        $saMod = new SharingActiveModel();
        $sauMod = new SharingActiveUsersModel();
        $is_creator = false;
        $sharing_active = NULL;$sharing_active_users = NULL;$sharing_creator_order = NULL;
        if($active_id > 0){ // 参团人员
            $sharing_active = $saMod::get($active_id);
            $sharing_active_users = $sauMod->alias('sau')->where('sau.active_id', '=', $active_id)->leftJoin('wx_user u', 'sau.user_id = u.id')->order('is_creator', 'DESC')->select();
            $sharing_goods = $groupProductModel->detail($sharing_active['goods_id']);
            $skus = $groupProductSkuModel->skus($sharing_goods['id'], $sharing_goods['org_id']);
            $sharing_goods['sku'] = $this->_checkedSku($skus, $select_sku_path);
            $sharing_creator_order = $orderModel->getOrderByUserId($sharing_active['creator_id']);
            if(!empty($sharing_creator_order)){
                $gosign = $this->checkOrderSyncStatus($sharing_creator_order['tid']);
                if($sharing_creator_order['order_type'] == OrderStatus::SHARING){
                    $goods = $groupProductModel->getDetailNameById($sharing_creator_order['item_id']);
                }else{
                    $goods = $productModel->getDetailNameById($sharing_creator_order['item_id']);
                }
                $sharing_creator_order['goods_name'] = !empty($goods->name) ? $goods->name : '';
                $sharing_creator_order['goods_image'] = !empty($goods->main_cover_src) ? $goods->main_cover_src : '';
                $sharing_creator_order['status_text'] = OrderStatus::getStatusMap()[$sharing_creator_order['status']];
                $sharing_creator_order['pay_status_text'] = OrderStatus::getCenterPayStatusMap()[$sharing_creator_order['pay_status']];
                $sharing_creator_order['order_type_text'] = OrderStatus::getOrderType()[$sharing_creator_order['order_type']];
                $sharing_creator_order['create_time_text'] = date('Y md H:i', $sharing_creator_order['create_time']);
                $items = json_decode($sharing_creator_order['item_message'], true);
                foreach($items as $item){
                    // 规格
                    if(!empty($item['path_text'])){
                        $sharing_creator_order['skus'] = array_values(explode(',', $item['path_text']));
                    }
                }
                $sharing_creator_order['down_time'] = time() - $sharing_creator_order['create_time'];
            }
        }else{ // 团长
            $sharing_active = $saMod->where('status', '<>', SharingStatus::SHARINGFAIL)->where('creator_id', '=', $user_id)->order('create_time', 'DESC')->find();
            if(!empty($sharing_active)){
                $sharing_active_users = $sauMod->getSharingActiveUsers($sharing_active['id']);
            }
            if(empty($sharing_active_users) && $isKaiTuan){
                $sharing_active_users[] = $user_info;
            }
        }

        if(!empty($sharing_active)){
            $sharing_active['end_second'] = $sharing_active['end_time'] - time();
            $sharing_active['status_text'] = SharingStatus::getSharingMap()[$sharing_active['status']];
            $sharing_active['total_people'] = rand(123, 698);
            $sharing_active['create_at'] = date('Y-m-d H:i', $sharing_active['create_time']);

            $is_creator = $sharing_active['creator_id'] == $user_id ? true : false;
            if(!empty($sharing_active)){
                $goods = $groupProductModel->detail($sharing_active['goods_id']);
                if(empty($goods)){
//                    $status = 1;
                    $is_stop = true;
                }
                $activity = $groupActivityModel::where('id',$sharing_active['activity_id'])->where('status','<>',0)->find();
                $sharing_active['total_people'] =  !empty($activity['fake_count'])? $activity['fake_count'] : rand(123, 698);
                if(empty($activity)){
//                    $status = 1;
                    $is_stop = true;
                }
                if($activity['end_time'] <= time()){
//                    $status = 1;
                    $is_stop = true;
                }
                if($activity['end_time'] < $sharing_active['end_time']){
                    $sharing_active['end_second'] = $activity['end_time'] - time();
                }else{
                    $sharing_active['end_second'] = $sharing_active['end_time'] - time();
                }
            }
        }
        // 1活动已结束 2拼团中(团长) 3拼团成功(团长团员) 4拼团失败(团长) 5拼团中(团员) 6拼团失败满人(团员) 7拼团成功(团员) 8已有拼团 9活动已结束
        $self_sharing_active_info = NULL;
        if($status == 0){
            if($is_creator){ // 团长
                if(!empty($sharing_active) && $sharing_active['status'] == SharingStatus::SHARING_ING){
                    $status = 2;
                    if(!empty($order)){
                        $sharing_goods = $groupProductModel->detail($order['item_id']);
                        $skus = $groupProductSkuModel->skus($sharing_goods['id'], $sharing_goods['org_id']);
                        $items = json_decode($order['item_message'], true);
                        foreach($items as $item){
                            if(!empty($item['path_text'])){
                                $order['skus'] = array_values(explode(',', $item['path_text']));
                                $select_sku_path = $item['path'];
                            }
                        }
                        $sharing_goods['sku'] = $this->_checkedSku($skus, $select_sku_path);
                    }
                }elseif(!empty($sharing_active) && $sharing_active['status'] == SharingStatus::SHARINGSUCC){
                    $status = 3;
                    if(!empty($order)){
                        $gosign = $this->checkOrderSyncStatus($order['tid']);
                        if($order['pay_status'] == OrderStatus::BUYER_IS_PAY){
                            if($gosign){
                                // origin status
                            }else{
                                $order['pay_status'] = 'WAIT_ORDER_SYNC';
                                $order['pay_status_text'] = '订单同步中';
                            }
                        }
                    }
                }elseif(!empty($sharing_active) && $sharing_active['status'] == SharingStatus::SHARINGFAIL && $order['pay_status'] == 'BUYER_IS_PAY' && $order['refund_state'] == 'REFUND_FAIL'){
                    $status = 4;
                    if(!empty($order)) {
                        $sharing_goods = $groupProductModel->detail($order['item_id']);
                        $skus = $groupProductSkuModel->skus($sharing_goods['id'], $sharing_goods['org_id']);
                        $items = json_decode($order['item_message'], true);
                        foreach ($items as $item) {
                            if (!empty($item['path_text'])) {
                                $order['skus'] = array_values(explode(',', $item['path_text']));
                                $select_sku_path = $item['path'];
                            }
                        }
                        $sharing_goods['sku'] = $this->_checkedSku($skus, $select_sku_path);
                    }else{
//                        $status = 9;
                        $is_stop = true;
                    }
                }elseif(!empty($sharing_active) && $sharing_active['status'] == SharingStatus::SHARINGFAIL && empty($order)){
                    $status = 4;
                    if(empty($order) && !empty($sharing_active_users)){
                        foreach($sharing_active_users as $sharing_active_user){
                            if($sharing_active_user['user_id'] == $user_id){
                                $order = $orderModel->getOrderByTid($sharing_active_user['tid']);
                            }
                        }
                        if(!empty($order)) {
                            $sharing_goods = $groupProductModel->detail($order['item_id']);
                            $skus = $groupProductSkuModel->skus($sharing_goods['id'], $sharing_goods['org_id']);
                            $items = json_decode($order['item_message'], true);
                            foreach ($items as $item) {
                                if (!empty($item['path_text'])) {
                                    $order['skus'] = array_values(explode(',', $item['path_text']));
                                    $select_sku_path = $item['path'];
                                }
                            }
                            $sharing_goods['sku'] = $this->_checkedSku($skus, $select_sku_path);
                            if (/*$order['pay_status'] == OrderStatus::ORDER_REFUND && */(isset($activity) && $activity['status'] ==0 ) ){  //退款活动,已经结束
                                $is_stop = true;
                            }
                        }/*else{                        //失效团长进失效拼团,活动未结束,呈现活动已结束的bug 20201120 19:03
//                            $status = 9;
                            $is_stop = true;
                        }*/
                        //dump(236);

                    }
                }
            }else{
                if(!empty($sharing_active) && $sharing_active['status'] == SharingStatus::SHARING_ING && (empty($order) || $order['order_type'] == 10)){
                    $status = 5;
                    if(!empty($sharing_active)){
                        $order = $orderModel->getOrderByUserId($sharing_active['creator_id']);
                    }
                    if(!empty($order)){
                        $sharing_goods = $groupProductModel->detail($order['item_id']);
                        $skus = $groupProductSkuModel->skus($sharing_goods['id'], $sharing_goods['org_id']);
                        $items = json_decode($order['item_message'], true);
                        foreach($items as $item){
                            if(!empty($item['path_text'])){
                                $order['skus'] = array_values(explode(',', $item['path_text']));
                                $select_sku_path = $item['path'];
                            }
                        }
                        $sharing_goods['sku'] = $this->_checkedSku($skus, $select_sku_path);
                    }
                }elseif(!empty($sharing_active) && $sharing_active['status'] == SharingStatus::SHARING_ING && !empty($order['active_id'])){
                    $status = 5;
                    if(!empty($sharing_active)){
                        $order = $orderModel->getOrderByUserId($sharing_active['creator_id']);
                    }
                    if(!empty($order)){
                        $sharing_goods = $groupProductModel->detail($order['item_id']);
                        $skus = $groupProductSkuModel->skus($sharing_goods['id'], $sharing_goods['org_id']);
                        $items = json_decode($order['item_message'], true);
                        foreach($items as $item){
                            if(!empty($item['path_text'])){
                                $order['skus'] = array_values(explode(',', $item['path_text']));
                                $select_sku_path = $item['path'];
                            }
                        }
                        $sharing_goods['sku'] = $this->_checkedSku($skus, $select_sku_path);
                    }
                    if(!empty($sharing_active_users)){
                        foreach($sharing_active_users as $sharing_active_user){
                            if($sharing_active_user['user_id'] == $user_id){
                                $status = 7;
                            }
                        }
                    }
                }elseif(!empty($sharing_active) && $sharing_active['status'] == SharingStatus::SHARINGSUCC && $sharing_active['actual_people'] <= $sharing_active['people']){
                    $isSelf = false;
                    foreach($sharing_active_users as $sharing_active_user){
                        if($sharing_active_user['user_id'] != $user_id){
                            continue;
                        }
                        $isSelf = true;
                    }
                    $status = 3;
                    if(!empty($order)){
                        $gosign = $this->checkOrderSyncStatus($order['tid']);
                        if($order['pay_status'] == OrderStatus::BUYER_IS_PAY){
                            if($gosign){
                                // origin status
                            }else{
                                $order['pay_status'] = 'WAIT_ORDER_SYNC';
                                $order['pay_status_text'] = '订单同步中';
                            }
                        }
                    }
                    if(!$isSelf){
                        $status = 6;
                        if(!empty($sharing_active)){
                            $order = $orderModel->getOrderByUserId($sharing_active['creator_id']);
                        }
                        if(!empty($order)){
                            $sharing_goods = $groupProductModel->detail($order['item_id']);
                            $skus = $groupProductSkuModel->skus($sharing_goods['id'], $sharing_goods['org_id']);
                            $items = json_decode($order['item_message'], true);
                            foreach($items as $item){
                                if(!empty($item['path_text'])){
                                    $order['skus'] = array_values(explode(',', $item['path_text']));
                                    $select_sku_path = $item['path'];
                                }
                            }
                            $sharing_goods['sku'] = $this->_checkedSku($skus, $select_sku_path);
                        }
                    }
                }elseif(!empty($sharing_active) && $sharing_active['status'] == SharingStatus::SHARINGSUCC && $sharing_active['actual_people'] >= $sharing_active['people']){
                    $status = 6;
                    if(!empty($sharing_active)){
                        $order = $orderModel->getOrderByUserId($sharing_active['creator_id']);
                    }
                    if(!empty($order)){
                        $sharing_goods = $groupProductModel->detail($order['item_id']);
                        $skus = $groupProductSkuModel->skus($sharing_goods['id'], $sharing_goods['org_id']);
                        $items = json_decode($order['item_message'], true);
                        foreach($items as $item){
                            if(!empty($item['path_text'])){
                                $order['skus'] = array_values(explode(',', $item['path_text']));
                                $select_sku_path = $item['path'];
                            }
                        }
                        $sharing_goods['sku'] = $this->_checkedSku($skus, $select_sku_path);
                    }
                }elseif(!empty($sharing_active) && $sharing_active['status'] == SharingStatus::SHARINGFAIL && $order['pay_status'] == 'BUYER_IS_PAY' && $order['refund_state'] == 'REFUND_FAIL'){
                    $status = 4;
                    //dump(337);
                    if(!empty($order)) {
                        $sharing_goods = $groupProductModel->detail($order['item_id']);
                        $skus = $groupProductSkuModel->skus($sharing_goods['id'], $sharing_goods['org_id']);
                        $items = json_decode($order['item_message'], true);
                        foreach ($items as $item) {
                            if (!empty($item['path_text'])) {
                                $order['skus'] = array_values(explode(',', $item['path_text']));
                                $select_sku_path = $item['path'];
                            }
                        }

                        $sharing_goods['sku'] = $this->_checkedSku($skus, $select_sku_path);
                    }else{
                        $is_stop = true;
//                        $status = 9;
                    }
                }elseif(!empty($sharing_active) && $sharing_active['status'] == SharingStatus::SHARINGFAIL){
                    $status = 4;
                    //dump(356);

                    if(empty($order) && !empty($sharing_active_users)){
                        foreach($sharing_active_users as $sharing_active_user){
                            if($sharing_active_user['user_id'] == $sharing_active['creator_id']){
                                $order = $orderModel->getOrderByTid($sharing_active_user['tid']);
                                continue;
                            }
                        }
                        if(!empty($order)){
                            $sharing_goods = $groupProductModel->detail($order['item_id']);
                            $skus = $groupProductSkuModel->skus($sharing_goods['id'], $sharing_goods['org_id']);
                            $items = json_decode($order['item_message'], true);
                            foreach($items as $item){
                                if(!empty($item['path_text'])){
                                    $order['skus'] = array_values(explode(',', $item['path_text']));
                                    $select_sku_path = $item['path'];
                                }
                            }
                            $sharing_goods['sku'] = $this->_checkedSku($skus, $select_sku_path);
                        }else{
                            $is_stop = true;
                        }
                    }
                    if (/*$order['pay_status'] == OrderStatus::ORDER_REFUND &&*/ (isset($activity) && $activity['status'] ==0 ) ){  //退款但是活动未结束
                        $is_stop = true;
                    }
                    //重新发起订单undefined
                    $old_order = $orderModel->where('user_id',$user_id)->where('active_id',$active_id)->where('pay_status',OrderStatus::ORDER_REFUND)->find();
                    if (!empty($old_order)){
                        $order = $old_order;
                    }
                    $sharing_goods = $groupProductModel->detail($order['item_id']);
                    $skus = $groupProductSkuModel->skus($sharing_goods['id'], $sharing_goods['org_id']);
                    $items = json_decode($order['item_message'], true);

                    foreach($items as $item){
                        if(!empty($item['path_text'])){
                            $order['skus'] = array_values(explode(',', $item['path_text']));
                            $select_sku_path = $item['path'];
                        }
                    }
                    $sharing_goods['sku'] = $this->_checkedSku($skus, $select_sku_path);
                    //重新发起订单undefined

                }elseif(!empty($sharing_active) && $sharing_active['status'] == SharingStatus::SHARING_ING && !empty($sharing_active_users)){
                    foreach($sharing_active_users as $sharing_active_user){
                        if($sharing_active_user['user_id'] == $user_id){
                            $status = 7;
                        }
                    }
                }
            }
            if($status == 0 && $active_id > 0){
                $this_active = $saMod->where('id',$active_id)->find();
                if ( $this_active['status'] != SharingStatus::SHARINGFAIL){         //优先判断当前平团订单是否失效
                    $self_sharing_active = $saMod
                        ->where('creator_id', '=', $user_id)
                        ->whereIn('status', [SharingStatus::SHARING_ING, SharingStatus::SHARINGSUCC])->order('create_time', 'DESC')
                        ->find();
                    if(!empty($self_sharing_active)){
                        $status = 8;
                        $self_sharing_active_info = $self_sharing_active;
                    }
                    $hh = $sauMod->field('sau.*,sa.status sharing_active_status')->alias('sau')
                        ->leftJoin('sharing_active sa', 'sau.active_id = sa.id')
                        ->where('user_id', '=', $user_id)->order('create_time', 'DESC')->find();
                    if(!empty($hh) && $hh['sharing_active_status'] == SharingStatus::SHARINGSUCC){
                        $status = 8;
                        $self_sharing_active_info = $hh;
                    }
                }else{
                    //拼团订单失效
                    //重新发起订单undefined
                    $old_order = $orderModel->where('user_id',$user_id)->where('active_id',$active_id)->where('pay_status',OrderStatus::ORDER_REFUND)->find();
                    if (!empty($old_order)){
                        $order = $old_order;
                    }
                    $sharing_goods = $groupProductModel->detail($order['item_id']);
                    $skus = $groupProductSkuModel->skus($sharing_goods['id'], $sharing_goods['org_id']);
                    $items = json_decode($order['item_message'], true);

                    foreach($items as $item){
                        if(!empty($item['path_text'])){
                            $order['skus'] = array_values(explode(',', $item['path_text']));
                            $select_sku_path = $item['path'];
                        }
                    }
                    $sharing_goods['sku'] = $this->_checkedSku($skus, $select_sku_path);
                    $is_stop = false;
                    //重新发起订单undefined
                    //团长进去失效活动不可以出现发起拼团
                    $activity = $groupActivityModel::where('id',$sharing_active['activity_id'])->where('status','<>',0)->find();
                    if(empty($activity)){
                        $is_stop = true;
                    }

                    $status = 4;
                }
            }
        }
        $this->success('ok', compact(
            'sign_url',
            'user_info',
            'order',
            'service',
            'sharing_active',
            'sharing_active_users',
            'is_creator',
            'sharing_goods',
            'sharing_creator_order',
            'status',
            'self_sharing_active_info',
            'is_stop'
        ));
    }

    /**
     * 获取参团商品列表
     * @param $active_id 拼单id
     */
    public function get_group_products(){
        $active_id = $this->request->param('active_id', 0, 'intval');
        $saMod = new SharingActiveModel();
        $sharing_active = $saMod::get($active_id);

        $groupProductModel = new GroupProductModel();
        $groupActivityModel = new GroupActivityModel();
        $groupProductSkuModel = new GroupProductSkuModel();
        $ordersModel = new OrdersModel();
        $active_id_orders = $ordersModel->where('active_id',$active_id)
                                        ->where('order_type',OrderStatus::SHARING)
                                        ->where('pay_status','=',OrderStatus::BUYER_IS_PAY)
                                        ->column('distinct item_id');
        $activity = $groupActivityModel->where('id',$sharing_active['activity_id'])
            ->where('status','<>',0)
            ->where('delete_time',0)
            ->find();
        $product_ids = explode(',',$activity['product_id']);

        $sharing_goods = [];
        if (is_array($product_ids)){
            foreach ($product_ids as $k=>$v){
//                dump($v);
                $df = $groupProductModel->where('org_id',$v)->where('activity_id',$activity['id'])->where('status','<>',0)->find();
                if (empty($df['id'])){
                    continue;
                }
                $sharing_goods[$v] = $df;
                if (in_array($df['id'],$active_id_orders)){
                    $sharing_goods[$v]['friend_buy'] = 1;
                }else{
                    $sharing_goods[$v]['friend_buy'] = 0;
                }
//                $activity = GroupActivityModel::where('id',$activity['id'])->find();
                $sharing_goods[$v]['join_activity_people'] =  !empty($activity['fake_count'])? $activity['fake_count'] : rand(123, 698);
                $sharing_goods[$v]['person_limit'] = $activity['person_limit'];
                $skus = $groupProductSkuModel->skus($sharing_goods[$v]['id'], $sharing_goods[$v]['org_id']);
                $sharing_goods[$v]['sku'] = $skus;
            }
        }
//        dump($sharing_goods);
        $this->success('ok', ['goods_list'=>array_values($sharing_goods),'active_id'=>$active_id]);
    }


    private function _checkedSku($skus, $checked){
        if(empty($skus)){
            return null;
        }
        foreach($skus as $sku){
            if($sku['path'] == $checked){
                return $sku;
            }
        }
        return null;
    }


    public function message(){
        $user_id = $this->getUserId();
        $orderModel = new OrdersModel();
        /*
         1 您有一张未使用的优惠券 立即查看
         2 您有一个未支付的订单 立即查看
         3 您的电子协议还没完成 立即查看
         */
        $ret = ['status' => 0];
        $order = $orderModel->getOrderByUserId($user_id);
        if(!empty($order)){
            $ret['status'] = 1;
        }elseif(!empty($order) && $order['pay_status'] == OrderStatus::WAIT_BUYER_PAY){
            $ret['status'] = 2;
        }elseif(!empty($order) && $order['pay_status'] == OrderStatus::BUYER_IS_PAY && $order['status'] == 1){
            $ret['status'] = 3;
        }
        $this->success('ok', $ret);
    }

    private function getPrice2($skus){
        $ret = ['min_along_price' => 0, 'min_price' => 0, 'max_price' => 0, 'org_min_price' => 0, 'org_max_price' => 0];
        if(empty($skus)){
            return $ret;
        }
        $ret['min_along_price'] = min(array_column($skus, 'price'));
        //$ret['org_min_price'] = min(array_column($skus, 'price'));
        //$ret['org_max_price'] = max(array_column($skus, 'org_price'));
        $ret['min_price'] = min(array_column($skus, 'group_price'));
        foreach($skus as $sku){
            if($ret['min_price'] == $sku['group_price']){
                $ret['org_min_price'] = $sku['price'];
            }
        }
        $couponModel = new CouponModel();
        $coupon = $couponModel->biggestCoupon()->find();
        $maxCoupon = 0;
        if(!empty($coupon)){
            $maxCoupon = $coupon['amount'];
        }
        $ret['min_coupon2_price'] = $ret['org_min_price'] - $maxCoupon;
        //$ret['max_price'] = max(array_column($skus, 'price'));
        $ret['min_coupon_price'] = $ret['min_price'];
        return $ret;
    }
}