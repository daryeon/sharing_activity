<?php
namespace api\shop\controller;

use api\shop\model\GroupProductModel;
use api\shop\model\SharingActiveModel;
use app\common\enum\SharingStatus;
use app\common\model\OrderLogModel;
use app\common\model\OrdersModel;
use app\common\model\PayLogModel;
use app\common\model\PayOperateModel;
use app\common\service\HuilaimiPay;
use app\common\service\ManageApi;
use app\common\service\WxPay;
use api\shop\model\UserCouponModel;
use api\shop\controller\RestBaseController;
use app\common\enum\OrderStatus;
use think\App;
use app\common\service\RedisService;
use think\Db;
use think\facade\Log;


class OrdersController extends RestBaseController
{
    private $model;
    function __construct(App $app = null)
    {
        $this->model = new OrdersModel();
        parent::__construct($app);
        Log::init([
            'type'  =>  'File',
            'path'  =>  CMF_DATA.'/runtime/log/api'
        ]);
    }

    /**
     * 订单详情
     */
    public function detail(){
        $tid = $this->request->get('tid', '', 'trim');
        if(!$tid){
            $this->error('订单不存在或已取消');
        }
        $order = $this->model->detail($tid);
        if(empty($order)){
            $this->error('订单不存在或已取消');
        }
        $detail = $order->toArray();
        $this->success('ok', $detail);
    }

    private function checkParams(&$data){
        if(!$this->request->isPost()){
            $this->error('请求方式有误~');
        }
        $data = [];
        $data['goods_id'] = $this->request->param('goods_id', 0, 'intval');
        $data['sku_path'] = $this->request->param('sku_path', '', 'strval');
        $data['extra'] = explode(',', $this->request->param('extra', '', 'strval'));
        $data['org_id'] = $this->request->param('org_id', 0, 'intval');
        $data['active_id'] = $this->request->param('active_id', 0, 'intval');
        $data['realname'] = $this->request->param('realname', '', 'trim');
        $data['identify'] = $this->request->param('identify', '', 'trim');
        $data['coupon_id'] = $this->request->param('coupon_id', '', 'trim');
        $data['get_id'] = $this->request->param('get_id', '', 'trim');

        $param = $this->request->param();
        Log::debug("oder66");
        Log::debug($param);
        if(!isset($data['goods_id'])){
            $this->error('缺少必要参数~');
        }
        if(!isset($data['sku_path'])){
            $this->error('缺少必要参数~');
        }
        if(isset($data['sku_path'])){
            $data['coupon_id'] = explode(',',$data['coupon_id']);
        }

        foreach($data as $name => &$item){
            if(is_numeric($item)){
                $data[$name] = intval($item);
            }elseif(is_string($item)){
                $data[$name] = strval(trim($item));
            }elseif(is_array($item)){
                continue;
            }
        }
    }

    private function checkOldStudent($mobile){
        $mApi = ManageApi::getInstance();
        $res = $mApi->checkOldStudent(['mobile' => $mobile]);
        if(empty($res) || $res['status'] != 200 || empty($res['data'])){
            return false;
        }
        if($res['data']['status']){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 提交订单
     */
    public function buynow(){
        $data = [];
        $this->checkParams($data);
        if(empty($data['realname'])){
            $this->error('学员姓名不能为空~');
        }
        if(empty($data['identify'])){
            $this->error('身份证号不能为空~');
        }
        $followId = $this->request->param('get_id', '', 'trim');
        $coupon_id = $this->request->param('coupon_id', '', 'trim');

        $user_id = $this->getUserId();
        $user_info = $this->user->toArray();
        $order_type = OrderStatus::MASTER;
        if(!empty($data['org_id'])){
            $order_type = OrderStatus::SHARING;
        }

        $coupon_ids = [];
        if (!empty($coupon_id)){
            $coupon_ids = explode(",",$coupon_id);
        }

        $order = $this->model->getBuyNow($user_info, $data['goods_id'], 1, $data['sku_path'], @$data['extra'], $order_type, $coupon_ids);
        if(is_string($order)){
            if($order == '很抱歉，活动已结束'){
                $this->error(['code' => 20210101, 'msg' => $order]);
            }else{
                $this->error($order);
            }
        }

        $current_order = $this->model->getOrderByUserId($user_id);
        if(!empty($current_order)){
            $this->error('您已经有订单啦~');
        }

        if(!empty($user_info['mobile']) && empty($current_order)){
            //TODO 正式环境要取消注释
            if($this->checkOldStudent($user_info['mobile'])){
                $this->error('每个学员只能报名一次哦~');
            }
        }

        // 开团
        if($order_type == OrderStatus::SHARING){
            if(empty($order['sharing_active'])){
                $this->error('活动不存在或已结束~');
            }
//            if($order['sharing_active']['status'] != 1){
//                $this->error('活动不存在或已结束~');
//            }
            if(!empty($order['sharing_active']['end_time']) && $order['sharing_active']['end_time'] <= time()){
                $this->error('活动已结束~');
            }

            $sauMod = new \api\shop\model\SharingActiveUsersModel();
            $sharingActive = $sauMod->getSharingActiveByUserId($user_id);
            if(!empty($sharingActive)){
                $this->error('已有拼团，查看详情~');
            }
            if(!empty($data['active_id'])){
                $saMod = new SharingActiveModel();
                $sharingActive = $saMod::get(['id', '=', $data['active_id']]);
                if(empty($sharingActive)){
                    $this->error('当前拼单已结束~');
                }
                if(!in_array($sharingActive['status'], [0, 10])){
                    $this->error('当前拼单已结束~');
                }
                if (time() > $sharingActive['end_time']) {
                    $this->error('当前拼单已结束~');
                }
                if($sharingActive['actual_people'] >= $sharingActive['people']){
                    $this->error('当前拼单人数已满~');
                }
            }

        }

        $logMod = new PayOperateModel();
        $logMod->addLog($user_id, array_merge($data, $order), 'system', '1.发起hlm支付');
        $flag = $this->model->createOrder($user_id, $order, @$data['coupon_id'], $data['active_id'], '', $data['realname'], $data['identify']);

        if($flag){
            $ret = $this->unifiedorder2($user_id, $order, $followId);
            if(is_string($ret)){
                $this->error($ret);
            }
            $this->success('ok', [
                'payment' => $ret,
                'order_id' => $order['tid'],
                'order_type' => $order_type,
                'active_id' => $data['active_id'],
//                'new_order' => $order
            ]);
        }else{
            $this->error('订单创建失败~');
        }
    }

    public function paid(){
        $tid = $this->request->param('tid', '', 'trim');
        $followId = $this->request->param('get_id', '', 'trim');
        if(empty($tid)){
            $this->error('支付失败~');
        }
        $user_id = $this->getUserId();
        $order = $this->model->detail($tid);
        if(!empty($order)){
            if($order['status'] == OrderStatus::CANCEL || $order['pay_status'] != OrderStatus::WAIT_BUYER_PAY){
                $this->error('订单已取消或已支付~');
            }
            if($order['order_type'] == OrderStatus::SHARING){
                $groupProductMod = new GroupProductModel();
                $goods = $groupProductMod->getDetailNameById($order['item_id']);
                // -- 检查拼团商品是否是在执行活动商品
                if(!empty($goods)){
                    $activityProcessing = $groupProductMod->checkoutHuodongjieshu($goods['activity_id'], $goods['org_id']);
                    if(!$activityProcessing){
                        $this->error('很抱歉，活动已结束~');
                    }
                }
                $order['goods_name'] = !empty($goods['name']) ? $goods['name'] : '学车套餐';
            }else{
                $goods = $this->model->getDetailNameById($order['item_id']);
                $order['goods_name'] = !empty($goods['name']) ? $goods['name'] : '学车套餐';
            }
            $order['order_pay_price'] = $order['pay_price'];
            $ret = $this->unifiedorder2($user_id, $order, $followId);
            if(is_string($ret)){
                $this->error($ret);
            }
            $this->success('ok', [
                'payment' => $ret,
                'order_id' => $order['tid'],
                'order_type' => $order['order_type'],
                'active_id' => $order['active_id'],
            ]);
        }else{
            $this->error('订单不存在~');
        }
    }

    public function checkout(){
        $data = [];
        $this->checkParams($data);
        $user_id = $this->getUserId();
        $user_info = $this->user->toArray();
        $order_type = OrderStatus::MASTER;
        if(!empty($data['org_id'])){
            $order_type = OrderStatus::SHARING;
        }
        $coupon_id = $this->request->param('coupon_id', '', 'trim');

        $coupon_ids = [];
        if (!empty($coupon_id)){
            $coupon_ids = explode(",",$coupon_id);
        }

        $order = $this->model->getBuyNow($user_info, $data['goods_id'], 1, $data['sku_path'], @$data['extra'], $order_type, $coupon_ids);
        if(is_string($order)){
            if($order == '很抱歉，活动已结束'){
                $this->error(['code' => 20210101, 'msg' => $order]);
            }else{
                $this->error($order);
            }
        }
        $sign = $feeslowSign = '';
        if(!empty($order['goods_id'])){
            $redisSer = new RedisService();
            list($type, $driver_type) = explode('-', $order['goods_id']);
            $driver_type = empty($driver_type) ? 1 : intval($driver_type);
            $primary1 = 'sign@' . $type;
            $primary2 = 'feeslowsign@' . $type;
            $flag = $redisSer->get($primary1);
            if(!empty($flag)){
                $sign = $flag;
                $feeslowSign = $redisSer->get($primary2);
            }else{
                $res = $this->getSignContextByGoodsId($order['goods_id']);
                $sign = $res['sign'];$feeslowSign = $res['feeslow_sign'];
                $redisSer->set($primary1, $sign, 10800);
                $redisSer->set($primary2, $feeslowSign, 10800);
            }
            if(!empty($sign)){
                $sign = $this->changeSign($sign, $order['order_total_price'], $driver_type, $order['order_pay_price']);
            }
            $goods = current($order['goods_list']);
            $isManbipei = false;
            if(!empty($goods['goods_extra_checked'])){
                foreach($goods['goods_extra_checked'] as $extra){
                    if(!empty($extra['name']) && $extra['name'] == OrderStatus::MANBIPEI){
                        $isManbipei = true;
                    }
                }
            }
            if($isManbipei){
                $sign .= $feeslowSign;
            }
        }
        $this->success('ok', array_merge($order, ['sign' => $sign]));
    }

    /**
     * @follow_id 跟进人id
     */
    private function unifiedorder2($user_id, $order, $follow_id){

        try{
            $redisSer = new RedisService();
            $order_type = ($order['order_type']==OrderStatus::SHARING) ? 'sharing': 'master';
            // 抢团处理
            if($order['order_type']==OrderStatus::SHARING && $order['active_id'] > 0){
                $primary = 'sharing@' . $order['active_id'];
                $saMod = new SharingActiveModel();
                $sharingActive = $saMod::get($order['active_id']);
                if(empty($sharingActive)){
                    return '拼团已结束~';
                }
                if($sharingActive['status'] != SharingStatus::SHARING_ING){
                    return '拼团已完成或已结束~';
                }
                $hasSeat = $sharingActive['people'] - $sharingActive['actual_people'];
                if($hasSeat <= 0){
                    return '拼团已满~';
                }
                $a = 1;$isSelf = false;
                for($i = 1; $i <= $hasSeat; $i++){
                    $flag = $redisSer->get($primary.'@'.$i);
                    if(!empty($flag)){
                        $a++;
                        if($flag == $user_id){
                            $isSelf = true;
                        }
                    }
                }
                if($a <= $hasSeat){
                    // set a seat expire for 5 min
                    if(!$isSelf){
                        $redisSer->set($primary.'@'.$a, $user_id, 120);
                    }
                }else{
                    if(!$isSelf){
                        return '拼团已满~';
                    }
                }
            }
            $pay = new HuilaimiPay();
            $user_info = $this->user->toArray();
            $attach = $user_info['mobile'] . '|' . $user_info['nick_name'];
            $payLog = new PayLogModel();
            $log_id = $payLog->add($order['tid'], $order['order_pay_price'], ['user_id' => $user_id, 'order_type' => $order_type, 'follow_id' => $follow_id]);
            if(!empty($log_id)){
                $payment = $pay->unifiedorder($user_id, $log_id, $user_info['openid'], $order['order_pay_price'], $order['goods_name'], $attach, $order_type);
                return $payment;
            }
            return '支付失败~';
        }catch (\Exception $e){
            Log::debug("order441=>".$e->getMessage());
            return '支付失败~~';
        }
    }

    public function refund(){
        try{
            //$user_id = $this->getUserId();
            //$pay = new HuilaimiPay();
            //$pay->refund(9, '9976741d29e43eed9c38e32e1815b126','4200000703202009241142166333', '0.01', '大叶');
            $this->success('ok');
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
    }

    public function order_cancel(){
        $tid = $this->request->param('tid', '', 'trim');
        if (empty($tid)){
            $this->error("订单号不存在");
        }
        $user_id = $this->getUserId();
        $orderMod = new OrdersModel();
        $order = $orderMod->where('tid', '=', $tid)->where('user_id',$user_id)->find();
        if(empty($order)){
            // 订单不存在或已取消
            $this->error("订单不存在");
        }
        if($order['status'] == OrderStatus::CANCEL){
            // 订单不存在或已取消
            $this->error("已取消");
        }
        if($order['pay_status'] == OrderStatus::BUYER_IS_PAY){
            // 订单已经支付
            $this->error("请联系客服取消");
        }
        $orderLogMod = new OrderLogModel();

        try{
            $old_order = $order->toArray();
            Db::startTrans();
            $flag = $orderMod->where('tid', '=', $order['tid'])->update([
                'update_time' => time(),
                'status' => OrderStatus::CANCEL,
                'pay_status' => OrderStatus::ORDER_CANCEL,
            ]);
            if($flag){
                $order['status'] = OrderStatus::CANCEL;
                $order['desc'] = '用户个人中心取消订单';
                $orderLogMod->addOrderLog($order, $old_order, $user_id, 'cancel');
            }
            if (!empty($order['coupon_ids'])){
                $coupons = explode(",",$order['coupon_ids']);
                $coupons_user_mod = new UserCouponModel();
                $coupons_user_mod->whereIn('coupon_id',$coupons)->where('user_id',$user_id)->update(['is_use'=>0]);
            }
            Db::commit();
        }catch(\Exception $e){
            Db::rollback();
            $this->error($e->getMessage());
        }
        $this->success('ok',[]);
    }
}