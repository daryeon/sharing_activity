<?php

namespace app\common\model;

use app\common\enum\OrderStatus;
use api\shop\model\UserCouponModel;
use think\facade\Log;
use think\Model;
use think\Exception;

class OrdersModel extends Model
{
    public function __construct($data = [])
    {
        parent::__construct($data);
        Log::init([
            'type'  =>  'File',
            'path'  =>  CMF_DATA.'/runtime/log/api'
        ]);
    }

    protected $autoWriteTimestamp = true;

    protected $type = [
        'status'    =>  'integer',
    ];

    private $fieldStr = '`id`,`tid`,`user_id`,`status`,`type`,`order_type`,`active_id`,`total_fee`,`price`,`pay_price`,
`item_message`,`num`,`item_id`,`sku_id`,`pay_type`,`pay_status`,`refund_state`,`pay_time`,`expired_time`,`create_time`';

    public function getList($listRows){
        return $this->field($this->fieldStr)->where('status', '<>', 0)->where('delete_time', '=', 0)->order('list_order', 'ASC')->paginate($listRows, false, ['query' => \request()->request()]);
    }

    public function getVaildSharingOrderCount(){
        return $this->where('status', '<>', OrderStatus::CANCEL)
            ->where('pay_status', '=', OrderStatus::WAIT_BUYER_PAY)
            ->where('order_type', '=', OrderStatus::SHARING)
            ->where('create_time', '<=', time() - 900)
            ->count();
    }

    public function getVaildSharingOrder($page, $limit = 50){
        return $this->where('status', '<>', OrderStatus::CANCEL)
            ->where('pay_status', '=', OrderStatus::WAIT_BUYER_PAY)
            ->where('order_type', '=', OrderStatus::SHARING)
            ->where('create_time', '<=', time() - 900)
            ->limit($page, $limit);
    }

    public function getRefundFailOrderCount(){
        return $this->where('status', '=', OrderStatus::CANCEL)
            ->where('pay_status', '=', OrderStatus::BUYER_IS_PAY)
            ->where('refund_state', '=', OrderStatus::REFUND_FAIL)
            ->count();
    }

    public function getRefundFailOrders($page, $limit = 50){
        return $this->where('status', '=', OrderStatus::CANCEL)
            ->where('pay_status', '=', OrderStatus::BUYER_IS_PAY)
            ->where('refund_state', '=', OrderStatus::REFUND_FAIL)
            ->limit($page, $limit);
    }

    public function getOrderByTid($tid){
        return $this->where('tid', '=', $tid)->find();
    }

    public function getOrderByUserId($user_id){
        return $this->where('user_id', '=', $user_id)->where('status', '<>', OrderStatus::CANCEL)->order(['pay_time' => 'DESC', 'create_time' => 'DESC'])->find();
    }

    public function getRefundOrderByUserId($user_id){
        return $this->where('user_id', '=', $user_id)->where('refund_state', '=', OrderStatus::REFUND_SUCCESS)->order('create_time', 'DESC')->find();
    }

    public function getProperty($gid){
        $productPropertyModel = new \app\shop\model\ProductPropertyModel();
        $properties = $productPropertyModel->where('delete_time', 0)->where('product_id', $gid)->select();
        foreach($properties as $property){
            $property['values'] = $property->values()->where('delete_time', 0)->order('id', 'asc')->select();
        }
        return $properties->toArray();
    }

    public function getDetailNameById($goods_id){
        $productMod = new \api\shop\model\ProductModel();
        return $productMod->field('`name`, `main_cover_src`')->where('id', '=', $goods_id)->find();
    }

    public function orderNo(){
        return 'E'.date('YmdHis') . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8);
    }

    private function setCouponPrice(&$order, $coupon_id)
    {
        if (!empty($coupon_id) && !empty($order['coupon_list'])) {
            // 获取优惠券信息
            $coupons = [];
            $coupon_price = 0;
            foreach ($order['coupon_list'] as $coupon){
                if (empty($coupon)) throw new Exception('未找到优惠券信息');
                $coupon_price += $coupon['amount'];
                $coupons[] = $coupon['id'];
            }
            // 记录订单信息
            $order['coupon_id'] = 0;
            $order['coupon_ids'] = (!empty($coupons)) ? implode(",",$coupons):'';
            $order['coupon_price'] = $coupon_price;
            if ($coupon_price > $order['order_total_price']){
                return false;
            }
            // 设置优惠券使用状态
            if (!empty($coupons)){
                $coupons_user_mod = new UserCouponModel();
                $coupons_user_mod->whereIn('coupon_id',$coupons)->where('user_id',$order['user_info']['id'])->update(['is_use'=>1]);
            }

            return true;
        }
        $order['coupon_id'] = 0;
        $order['coupon_ids'] = '';
        $order['coupon_price'] = 0.00;
        return true;
    }

    public function createOrder($user_id, &$order, $coupon_id = null, $active_id = 0, $remark = '', $realname = '', $identify = ''){
        $this->startTrans();
        try {
            $this->where('user_id', '=', $user_id)->where('status','<>', OrderStatus::CANCEL)->update(['status' => OrderStatus::CANCEL]);
            // 设置优惠信息
            $set_coupon = $this->setCouponPrice($order, $coupon_id);
            if (!$set_coupon){
                throw new \Exception('优惠券金额大于商品原价');
            }
            // 记录订单信息
            $tid = $this->orderNo();
            $order['tid'] = $tid;
            $order['active_id'] = $active_id;
            $flag = $this->add($tid, $user_id, $order, $remark, $realname, $identify);
            if($flag){
                $this->commit();
                return true;
            }
            $this->rollback();
            return false;
        } catch (\Exception $e) {
            $this->rollback();
            return false;
        }
    }

    private function add($tid, $user_id, &$order, $remark = '', $realanem = '', $identify = '')
    {
        $this->startTrans();
        try{
            $goods = count([$order['goods_list']])==1 ? $order['goods_list']: current($order['goods_list']);
            $item_message = array_merge(@[$goods['goods_sku']], array_values(@$goods['goods_extra_checked']));
            $data = [
                'user_id' => $user_id,
                'tid' => $tid,
                'total_fee' => $order['order_total_price'],
                'price' => $order['order_total_price'],
                'pay_price' => $order['order_pay_price'],
                'coupon_id' => @$order['coupon_id'],
                'coupon_ids' => @$order['coupon_ids'],
                'coupon_price' => $order['coupon_price'],
                'buyer_remark' => trim($remark),
                'order_type' => $order['order_type'],
                'item_message' => json_encode($item_message),
                'type' => @$goods['type'],
                'status' => OrderStatus::INIT,
                'num' => @$goods['total_num'],
                'item_id' => @$goods['id'],
                'sku_id' => @$goods['goods_sku']['id'],
                'pay_type' => OrderStatus::NUTSET,
                'pay_status' => OrderStatus::WAIT_BUYER_PAY, //待支付
                'active_id' => !empty($order['active_id']) ? $order['active_id'] : 0,
                'realname' => $realanem,
                'identify' => $identify,
                // refund_state
                // pay_time
                // expired_time
                'create_time' => time()
            ];
            $this->insert($data);
            $logMod = new OrderLogModel();
            $logMod->addOrderLog($data, $order);
            $this->commit();
            return true;
        }catch (\Exception $e){
            Log::debug("order=>178=>".$e->getMessage());
            $this->rollback();
            return false;
        }
    }

    public function detail($tid){
        return $this->field($this->fieldStr)->where('tid', '=', $tid)->find();
    }

    public function getSkusByGoodId($gid){
        $model = new \app\shop\model\ProductSkuModel();
        return $model->skus($gid);
    }

    public function getSkuById($skuId){

    }

    public function getExtra($gid){
        $model = new \app\shop\model\ExtraValueModel();
        $ext = $model->where('product_id', '=', $gid)->where('support', '=', 1)->select();
        if(!empty($ext)){
            foreach($ext as &$_ext){
                if(!empty($_ext['name'])){
                    $_ext['name_text'] = OrderStatus::getServiceNameMap()[$_ext['name']];
                }
            }
            return $ext;
        }else{
            return NULL;
        }
    }

    public function getGroupExtra($gid){
        $model = new \app\shop\model\GroupExtraValueModel();
        $ext = $model->where('product_id', '=', $gid)->where('support', '=', 1)->select();
        if(!empty($ext)){
            foreach($ext as &$_ext){
                if(!empty($_ext['name'])){
                    $_ext['name_text'] = OrderStatus::getServiceNameMap()[$_ext['name']];
                }
            }
            return $ext;
        }else{
            return NULL;
        }
    }

    public function getBuyNow($user, $goods_id, $goods_num, $sku_path, $extra = [], $order_type = OrderStatus::MASTER, $coupon_ids = []){
        // 商品信息
        switch ($order_type){
            case OrderStatus::MASTER:
                $goodModel = new ProductModel();
                $goods = $goodModel->detail($goods_id);
                break;
            case OrderStatus::SHARING:
                $goodModel = new \api\shop\model\GroupProductModel();
                $goods = $goodModel->detail($goods_id);
                // -- 检查拼团商品是否是在执行活动商品
                if(!empty($goods)){
                    $activityProcessing = $goodModel->checkoutHuodongjieshu($goods['activity_id'], $goods['org_id']);
                    if(!$activityProcessing){
                        return '很抱歉，活动已结束';
                    }
                }

                break;
        }
        // 判断商品是否下架
        if (!$goods || !empty($goods['delete_time'])) {
            return '很抱歉，商品信息不存在或已下架';
        }
        // 商品sku信息
        $goods['goods_sku'] = $goodModel->getSkuByGoodIdAndSkuPath($goods['id'], $sku_path);

        if(empty($goods['goods_sku'])){
            return '很抱歉，商品信息不存在或已下架!';
        }
        // 返回的数据
        $returnData = [];
        // 商品单价
        $goods['goods_price'] = ($order_type==OrderStatus::SHARING) ? $goods['goods_sku']['group_price'] : $goods['goods_sku']['price'];
        // 增值服务
        $goods['goods_extra'] = ($order_type==OrderStatus::SHARING) ? $this->getGroupExtra($goods['id']): $this->getExtra($goods['id']);
        if(!empty($extra)){
            $goods_extra_checked = $this->_checkoutExtra($goods['goods_extra'], $goods, $extra);
            $goods['goods_extra_checked'] = $goods_extra_checked;
        }
        // 商品总价
        $goods['total_num'] = $goods_num;
        $goods['total_price'] = $goodsTotalPrice = bcmul($goods['goods_price'], $goods_num, 2);
        // 可用优惠券列表
        $discount = 0;
        $userCouponModel = new UserCouponModel();
        $availableCoupons = [];
        $userCoupons = $userCouponModel->where('delete_time', 0)->where('user_id', $user['id'])->select();
        $couponId = 0;
        foreach($userCoupons as $userCoupon){
            $coupon = $userCoupon->availableCoupon()->find();
            if(!empty($coupon)){
                if($coupon['id'] == $couponId){
                    continue;
                }
                $couponId = $coupon['id'];
                if(in_array($coupon['id'], $coupon_ids)){
                    $discount += $coupon['amount'];
                    $couponArr = $coupon->toArray();
                    $couponArr['user_coupon_id'] = $userCoupon['id'];
                    $availableCoupons[] = $couponArr;
                }
            }
        }
        $sharingActive = !empty($goods['activity_id']) ? $goodModel->where('activity_id','=',$goods['activity_id'])->find() : [];
        return array_merge([
            'user_info' => $user,
            'goods_list' => $goods,   // 商品详情
            'order_total_num' => $goods_num,            // 商品总数量
            'order_total_price' => $goodsTotalPrice,    // 商品总金额
            'order_pay_price' => ($order_type==OrderStatus::SHARING) ? $goodsTotalPrice: $goodsTotalPrice - $discount,      // 订单总金额
            'order_type' => $order_type, // 订单类型 10 单独购买 20 拼团订单
            'coupon_list' => $availableCoupons, // 优惠券列表
            'sharing_active' => $sharingActive, // 拼团活动信息
            'goods_name' => !empty($goods['name']) ? $goods['name'] : '',
            'goods_id' => !empty($goods['goods_sku']['goods_id']) ? $goods['goods_sku']['goods_id'] : 0,
        ], $returnData);
    }

    private function _checkoutExtra(&$data, &$goods, $checked){
        if(empty($checked)) $checked = [];
        $total = 0;$goods_extra_checked = !empty($goods['goods_extra_checked']) ? $goods['goods_extra_checked'] : [];
        foreach($data as &$ext){
            if(!empty($ext['name']) && in_array($ext['name'], $checked)){
                $total += $ext['amount'];
                $ext['checked'] = true;
                array_push($goods_extra_checked, $ext);
            }else{
                $ext['checked'] = false;
            }
        }
        $goods['goods_price'] = $goods['goods_price'] + $total;
        return $goods_extra_checked;
    }
}
