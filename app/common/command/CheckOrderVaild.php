<?php
namespace app\common\command;

use app\common\enum\OrderStatus;
use app\common\model\GroupProductModel;
use app\common\model\OrderLogModel;
use app\common\model\OrdersModel;
use app\common\model\WxUserModel;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

class CheckOrderVaild extends Command{
    protected function configure()
    {
        $this->setName('check_order_vaild')
            ->setDescription('检测拼团订单15分钟有效性');
    }

    protected function execute(Input $input, Output $output)
    {
        $groupProductModel = new GroupProductModel();
        $wxUserModel = new WxUserModel();
        $orderMod = new OrdersModel();
        $orderLogMod = new OrderLogModel();
        $count = $orderMod->getVaildSharingOrderCount();
        $limit = 50;
        $page = ceil($count / $limit);
        for($pn=0;$pn<$page;$pn++) {
            $records = $orderMod->getVaildSharingOrder($pn,$limit)->select();
            foreach($records as $record){
                if(!empty($record)){
                    try{
                        $flag = $orderMod->where('tid', '=', $record['tid'])->update([
                            'update_time' => time(),'status' => OrderStatus::CANCEL
                        ]);
                        if($flag){
                            $old_order = $record->toArray();
                            $record['status'] = OrderStatus::CANCEL;
                            $orderLogMod->addOrderLog($record, $old_order, '系统管理员', 'cancel');

                            $user = $wxUserModel::get($record['user_id']);
                            if(!empty($user)){
//                                $goods = $groupProductModel::get($record['item_id']);
//                                $data = [];
//                                $data['openid'] = $user['openid'];
                                $data['mobile'] = $user['mobile'];
//                                $data['goods_name'] = !empty($goods['name']) ? $goods['name'] : '学车套餐';
                                $data['remarks'] = '，订单超过15分钟未支付，自动取消。您可以再次发起拼团/参团！';
//                                $data['url'] = '/pages/myInfo/myInfo';
                                \app\common\service\MQProducer::getSingle('shop')->run($data, 'sharing_active_order_cancel');
                            }
                            $output->writeln(date('[Y-m-d H:i:s]') . "{$record['tid']} 取消成功\r\n");
                        }else{
                            $output->writeln(date('[Y-m-d H:i:s]') . "{$record['tid']} 取消失败\r\n");
                        }
                    }catch (\Exception $e){
                        $output->writeln(date('[Y-m-d H:i:s]') . "{$record['tid']} {$e->getMessage()}\r\n");
                    }
                }
            }
        }
        $output->writeln(date('[Y-m-d H:i:s]') . "成功取消过期拼团订单条数{$count}\r\n");
    }
}