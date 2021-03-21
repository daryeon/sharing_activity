<?php
namespace app\common\command;

use api\shop\model\CouponModel;

use api\shop\model\UserCouponModel;
use app\common\enum\OrderStatus;
use app\common\model\OrderLogModel;
use app\common\model\OrdersModel;
use app\shop\model\PromotionModel;
use think\console\Command;
use think\console\Input;

use think\console\Output;
use think\Db;

class AutoCancelOrders extends Command{
    protected function configure()
    {
        $this->setName('auto_cancel_orders')
            ->setDescription('订单自下单起24小时未支付即自动取消');
    }

    protected function execute(Input $input, Output $output)
    {
        $orderMod = new OrdersModel();
        $orders_list = $orderMod->where('status', '<>', OrderStatus::CANCEL)
                                ->where('pay_status',OrderStatus::WAIT_BUYER_PAY)
                                ->order('create_time','desc')
                                ->select();
        $orderLogMod = new OrderLogModel();
        $i = 0;
        if(!empty($orders_list)){
            // 订单不存在或已取消
          foreach ($orders_list as $v){
              if ( !((time() - $v['create_time']) > 86400) ){          //创建订单后 5秒没有支付
                  continue;
              }
              try{
                  $order = $old_order = $v->toArray();
                  Db::startTrans();
                  $flag = $orderMod->where('id', '=', $v['id'])->update([
                      'update_time' => time(),
                      'status' => OrderStatus::CANCEL,
                      'pay_status' => OrderStatus::ORDER_CANCEL,
                  ]);
                  if($flag){
                      $order['status'] = OrderStatus::CANCEL;
                      $order['desc'] = '用户个人中心取消订单';
                      $orderLogMod->addOrderLog($order, $old_order, 'script', 'cancel');
                  }
                  if (!empty($order['coupon_ids'])){
                      $coupons = explode(",",$order['coupon_ids']);
                      $coupons_user_mod = new UserCouponModel();
                      $coupons_user_mod->whereIn('coupon_id',$coupons)->where('user_id',$order['user_id'])->update(['is_use'=>0]);
                  }
                  Db::commit();
                  $i++;
              }catch(\Exception $e){
                  Db::rollback();
                  $output->writeln(date('[Y-m-d H:i:s]') . "{$e->getMessage()}\r\n");
              }
          }
        }

        $output->writeln(date('[Y-m-d H:i:s]') . "自动取消订单条数{$i}\r\n");
    }
}