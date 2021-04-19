<?php

namespace app\shop\model;

use app\common\enum\OrderStatus;
use think\Model;
use think\Db;

class OrdersModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $type = [
        'status'    =>  'integer',
    ];

    public function updateOrder($data)
    {
        $data['update_time'] = time();
        $this->startTrans();
        try{
            $tid = $data['tid'];
            unset($data['tid']);
//            if(isset($data['status']) && $data['status'] == OrderStatus::CANCEL){
//                $flag = $this->cancelOrder($tid, $data['status']);
//                unset($data['status']);
//                if($flag != true){
//                    throw new \Exception($flag);
//                }
//            }

            //$db = Db::name('orders');
            $rs = $this->where('tid', $tid)->update($data);
            $this->commit();
            return $rs;
        }catch(\Exception $e){
            $this->rollback();
            return $e->getMessage();
        }
    }

    public function cancelOrder($tid, $status){
        try{
            $order = OrdersModel::get(['tid' => $tid]);
            if(!empty($order->tid) && $order->status != $status){
                // 订单取消相应的逻辑

                $order->save(['status' => $status], ['id' => $order->id]);
            }
            return true;
        }catch (\Exception $e){
            return $e->getMessage();
        }
    }
}
