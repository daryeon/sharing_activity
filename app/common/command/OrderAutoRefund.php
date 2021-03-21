<?php
namespace app\common\command;

use app\common\enum\OrderStatus;
use app\common\enum\SharingStatus;
use app\common\model\GroupProductModel;
use app\common\model\OrderLogModel;
use app\common\model\OrdersModel;
use app\common\model\PayLogModel;
use app\common\model\WxUserModel;
use app\common\service\HuilaimiPay;
use app\common\service\MSGApi;
use app\shop\model\SharingActiveModel;
use app\shop\model\SharingActiveUsersModel;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

class OrderAutoRefund extends Command{
    protected function configure()
    {
        $this->setName('order_auto_refund')
            ->setDescription('退款失败订单重新退款');
    }

    protected function execute(Input $input, Output $output)
    {
        $payLogMod = new PayLogModel();
        $orderMod = new OrdersModel();
        $orderLogMod = new OrderLogModel();
        $pay = new HuilaimiPay();
        $count = $orderMod->getRefundFailOrderCount();
        $limit = 50;
        $page = ceil($count / $limit);
        for($pn=0;$pn<$page;$pn++) {
            $records = $orderMod->getRefundFailOrders($pn,$limit)->select();
            foreach($records as $record){
                if(!empty($record)){
                    if($record['pay_status'] != OrderStatus::BUYER_IS_PAY){
                        $output->writeln(date('[Y-m-d H:i:s]') . "{$record['tid']} 订单未支付\r\n");
                        continue;
                    }
                    if(empty($record['transaction_id'])){
                        $output->writeln(date('[Y-m-d H:i:s]') . "{$record['tid']} 订单流水不存在\r\n");
                        continue;
                    }
                    try{
                        $old_order = $record->toArray();
                        $payLog = $payLogMod->getRefundPayIdByTid($record['tid']);
                        if(empty($payLog)){
                            // 支付日志不存在
                            $output->writeln(date('[Y-m-d H:i:s]') . "{$record['tid']} 支付日志不存在\r\n");
                            continue;
                        }
                        $ret = $pay->refund($record['user_id'], $payLog['pay_id'], $record['transaction_id'], $record['pay_price']);
                        if(!$ret['paid']){
                            $orderMod->where('tid', '=', $record['tid'])->update([
                                'status' => OrderStatus::CANCEL,
                                'update_time' => time(),'refund_state' => OrderStatus::REFUND_FAIL,
                                'refund_time' => time(), 'refund_message' => json_encode($ret)
                            ]);
                            $record['status'] = OrderStatus::CANCEL;$record['desc'] = '退款失败状态更新';
                            $orderLogMod->addOrderLog($record, $old_order, '系统管理员', 'cancel');
                            $output->writeln(date('[Y-m-d H:i:s]') . "{$record['tid']} 退款失败\r\n");
                            continue;
                        }
                        $flag = $orderMod->where('tid', '=', $record['tid'])->update([
                            'update_time' => time(),'status' => OrderStatus::CANCEL,
                            'pay_status' => OrderStatus::ORDER_REFUND, 'refund_state' => OrderStatus::REFUND_SUCCESS,
                            'refund_time' => time(), 'refund_message' => json_encode($ret)
                        ]);
                        $output->writeln(date('[Y-m-d H:i:s]') . "{$record['tid']} refund success\r\n");
                    }catch(\Exception $e){
                        $output->writeln(date('[Y-m-d H:i:s]') . "{$record['tid']} {$e->getMessage()}\r\n");
                    }
                }
            }
        }
        $output->writeln(date('[Y-m-d H:i:s]') . "成功退款订单{$count}\r\n");
    }
}