<?php
namespace api\shop\controller;

use api\shop\model\CouponModel;
use api\shop\service\CustomerCenterService;
use api\shop\model\GroupProductModel;
use api\shop\model\SharingActiveModel;
use api\shop\model\SharingActiveUsersModel;
use api\shop\model\UserCouponModel;
use app\common\enum\SharingStatus;
use app\common\model\OrdersModel;
use app\common\model\ProductModel;
use api\shop\controller\RestBaseController;
use app\common\enum\OrderStatus;
use app\common\service\ManageApi;
use think\facade\Config;
use think\App;


class CenterController extends RestBaseController
{
    function __construct(App $app = null)
    {
        parent::__construct($app);
    }

    public function index(){
        $sign_flag = $this->request->param('sign_flag', 0, 'intval');
        $user_id = $this->getUserId();
        $user_info = $this->user;
        $orderModel = new OrdersModel();
        $productModel = new ProductModel();
        $groupProductModel = new GroupProductModel();
        $sign_url = Config::get('env.sign_url');
        $order = $orderModel->getOrderByUserId($user_id);
        if($sign_flag == 1){
            $orderModel->where('tid', '=', $order['tid'])->update(['status' => 1]); // 已签署协议
        }
        $is_old_student = false;
        if(!empty($user_info['mobile']) && empty($order)){
            if($this->checkOldStudent($user_info['mobile'])){
                $is_old_student = true;
                $this->success('ok', compact('user_info', 'is_old_student'));
            }
        }
        if(empty($order)){
            $order = $orderModel->getRefundOrderByUserId($user_id);
        }
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
            $order['create_time_text'] = date('Y.m.d H:i', $order['create_time']);
            $order['enums_status'] = $order['pay_status'];
            $order['enums_status_text'] = $order['pay_status_text'];
            $gosign = $this->checkOrderSyncStatus($order['tid']);           //历史数据
//            $gosign = null;           //历史数据
            if($order['pay_status'] == OrderStatus::BUYER_IS_PAY){           //已经支付
                if($gosign){                                                //同步成功
                    // origin status
                }else{                                                       //同步中
                    $order['pay_status'] = 'WAIT_ORDER_SYNC';
                    $order['pay_status_text'] = '订单同步中';
                    $order['enums_status'] = 'WAIT_ORDER_SYNC';
                    $order['enums_status_text'] = '订单同步中';
                }

            }
            if($order['pay_status'] == OrderStatus::BUYER_IS_PAY && ($order['status'] == 1 || $sign_flag == 1)){
                $order['pay_status'] = 'WAIT_ORDER_APPLY';
                $order['pay_status_text'] = ''; #订单审核中
                $order['enums_status'] = 'WAIT_ORDER_APPLY';
                $order['enums_status_text'] = ''; #订单审核中
            }
            $order['coupon_list'] = NULL;
            if($order['order_type'] == OrderStatus::MASTER){
                $userCouponModel = new UserCouponModel();
                $coupon_list = $userCouponModel->getUserCouponByUserId($user_id);
                if(!empty($coupon_list)){
                    $cuponList = NULL;
                    $couponId = 0;
                    foreach($coupon_list as $k => $coupon){
                        if(!empty($coupon) && $coupon['coupon_id'] == $couponId){
                            continue;
                        }
                        $couponId = $coupon['coupon_id'];
                        $create_time_text = date('Y.m.d H:i', $coupon['create_time'] + 30 * 86400);
                        $cuponList[$k] = $coupon;
                        $cuponList[$k]['create_time_text'] = $create_time_text;
                    }
                    $order['coupon_list'] = $cuponList;
                }
            }
            $service = [];
            $items = json_decode($order['item_message'], true);
            foreach($items as $item){
                // 规格
                if(!empty($item['path_text'])){
                    $order['skus'] = array_values(explode(',', $item['path_text']));
                // 增值服务
                }else{
                    $order['total_fee'] = $order['total_fee'] - $item['amount'];
                    array_push($service,  ['key' => $item['name'], 'name' => OrderStatus::getServiceNameMap()[$item['name']], 'amount' => $item['amount']]);
                }
            }
            // 团信息
            $sharing_info = NULL;
            if($order['order_type'] == OrderStatus::SHARING){
                $saMod = new SharingActiveModel();
                $sauMod = new SharingActiveUsersModel();
                if(!empty($order['active_id'])){
                    $sharingActive = $saMod::get($order['active_id']);
                    if(!empty($sharingActive)){
                        $sharingActive['status_text'] = SharingStatus::getSharingMap()[$sharingActive['status']];
                    }
                    $sharingActiveUsers = $sauMod->getSharingActiveUsers($sharingActive['id']);
                    $sharing_info['is_creator'] = (!empty($sharingActive)&&$sharingActive['creator_id']==$user_id) ? true : false;
                    $sharing_info['sharing_active'] = !empty($sharingActive) ? $sharingActive : [];
                    $sharing_info['sharing_active_users'] = !empty($sharingActiveUsers) ? $sharingActiveUsers : [];
                    if(/*$sharing_info['is_creator'] && */$sharingActive['status'] == SharingStatus::SHARING_ING){
                        $order['enums_status_text'] = '待成团';
                        $order['enums_status'] = 'REQUEST_FRIEND'; // 邀请好友参团
                    }elseif($sharingActive['status'] == SharingStatus::SHARINGFAIL && $order['pay_status'] == OrderStatus::BUYER_IS_PAY && $order['refund_state'] == OrderStatus::REFUND_FAIL){
                        $order['enums_status_text'] = '已关闭，交易取消';
                        $order['enums_status'] = 'CLOSE_DOOR'; // 查看详情
                    }elseif($sharingActive['status'] == SharingStatus::SHARINGFAIL && $order['refund_state'] == OrderStatus::REFUND_SUCCESS){
                        $order['enums_status_text'] = '已关闭，交易取消';
                        $order['enums_status'] = 'CLOSE_DOOR'; // 查看详情
                    }elseif($sharingActive['status'] == SharingStatus::SHARING_ING && $order['pay_status'] == OrderStatus::WAIT_BUYER_PAY){
                        $order['enums_status_text'] = '待支付';
                        $order['enums_status'] = 'WAIT_BUYER_PAY'; // 去支付
                    }elseif($sharingActive['status'] == SharingStatus::SHARINGSUCC && $order['pay_status'] == OrderStatus::BUYER_IS_PAY && ($order['status'] == 0 || $sign_flag != 1)){
                        $order['enums_status'] = 'WAIT_ORDER_SYNC';
                        $order['enums_status_text'] = '订单同步中';
                        if($order['pay_status'] == OrderStatus::BUYER_IS_PAY && $gosign){
                            $order['enums_status_text'] = '已支付，待签署协议';
                            $order['enums_status'] = 'BUYER_IS_PAY'; // 签署协议
                        }
                    } elseif($sharingActive['status'] == SharingStatus::SHARINGSUCC && $order['pay_status'] == OrderStatus::BUYER_IS_PAY && ($order['status'] == 1 || $sign_flag == 1)){
                        $order['enums_status'] = 'WAIT_ORDER_APPLY';
                        $order['enums_status_text'] = ''; #订单审核中
                    }
                }

            }
        }
        $this->success('ok', compact('sign_url', 'user_info', 'order', 'service', 'sharing_info', 'is_old_student'));
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
        $userCouponModel = new UserCouponModel();
        if(!empty($order) && $order['pay_status'] == OrderStatus::WAIT_BUYER_PAY){
            $ret['status'] = 2;
        }elseif($userCouponModel->where('user_id', '=', $user_id)->count() > 0 && $order['pay_status'] == OrderStatus::WAIT_BUYER_PAY){
            $ret['status'] = 1;
        }elseif(!empty($order) && $order['pay_status'] == OrderStatus::BUYER_IS_PAY && $order['status'] != 1){
            if($order['active_id'] > 0){
                $sharingActiveModel = new SharingActiveModel();
                $sharingActive = $sharingActiveModel::get($order['active_id']);
                if(!empty($sharingActive) && $sharingActive['status'] == SharingStatus::SHARINGSUCC){
                    $ret['status'] = 3;
                }
            }else{
                $ret['status'] = 3;
            }
        }
        $this->success('ok', $ret);
    }

    public function checkout_login_status(){
        $user_id = $this->getUserId();
        $this->success('ok', $user_id);
    }
}