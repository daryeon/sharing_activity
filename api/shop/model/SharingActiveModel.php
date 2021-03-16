<?php

namespace api\shop\model;

use app\common\enum\OrderStatus;
use app\common\model\OrdersModel;
use app\common\model\PayLogModel;
use app\common\model\WxUserModel;
use app\common\service\RedisService;
use think\Model;
use think\Exception;

class SharingActiveModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $type = [
        'status'    =>  'integer',
    ];

    public function getSharingActiveByActiveId($active_id){
        return $this->alias('sa')->where('sa.id', '=', $active_id)->leftJoin('SharingActive sa', 'sa.id = sau.active_id')->find();
    }

    public function onCreate($tid, $goods_id, $people, $user_id, $end_time, $actual_people=0,$activity_id=0){
        // 新增拼单记录
        $this->insert([
            'goods_id' => $goods_id,
            'people' => $people,
            'actual_people' => $actual_people,
            'creator_id' => $user_id,
            'end_time' => $end_time,
            'status' => 10,
            'create_time' => time(),
            'activity_id' => $activity_id,
        ]);
        $active_id = $this->getLastInsID();
        $sauMod = new SharingActiveUsersModel();
        $sauMod->insert([
            'active_id' => $active_id,
            'tid' => $tid,
            'user_id' => $user_id,
            'is_creator' => 1,
            'create_time' => time()
        ]);
        return $active_id;
    }

    public function onUpdate($active_id, $user_id, $tid){
        // 验证当前拼单是否允许加入新成员
        $redisSer = new RedisService();
        $shareActive = $this->where('id', '=', $active_id)->find();
        if(empty($shareActive)){
            return false;
        }

        // 支付则让座
        if($active_id > 0){
            $x = $shareActive['people'] - 1;
            $primary = 'sharing@' . $active_id;
            for($i=1;$i<=$x;$i++){
                $seat = $redisSer->get($primary.'@'.$i);
                if(!empty($seat) && $seat == $user_id){
                    $redisSer->del($primary.'@'.$i);
                }
            }
        }

        // 当前拼单已结束
        if (!in_array($shareActive['status'], [0, 10])) {
            return false;
        }
        // 当前拼单已结束
        if (time() > $shareActive['end_time']) {
            return false;
        }
        // 当前拼单人数已满
        if ($shareActive['actual_people'] >= $shareActive['people']) {
            return false;
        }
        // 新增拼单成员记录
        $sauMod = new SharingActiveUsersModel();
        $sauMod->insert([
            'active_id' => $shareActive['id'],
            'tid' => $tid,
            'user_id' => $user_id,
            'is_creator' => 0,
            'create_time' => time()
        ]);
        // 累计已拼人数
        $actual_people = $shareActive['actual_people'] + 1;
        // 更新拼单记录：当前已拼人数、拼单状态
        $status = $actual_people >= $shareActive['people'] ? 20 : 10;

        $wxUserModel = new WxUserModel();
        $groupProdctModel = new GroupProductModel();
        $groupActivityModel = new GroupActivityModel();
        if($status == 20){
            $goods = $groupProdctModel->getDetailNameById($shareActive['goods_id']);
            $sharingActiveUsers = $sauMod->where('active_id', '=', $shareActive['id'])->select();
            if(!empty($sharingActiveUsers)){
                $sendList = [];$member = '';
                foreach($sharingActiveUsers as $sharingActiveUser){
                    $wxUser = $wxUserModel::get($sharingActiveUser['user_id']);
                    $member .= ' ' . $wxUser['nick_name'];
                    $item = [];
                    $item['mobile'] = $wxUser['mobile'];
                    $item['openid'] = $wxUser['openid'];
                    $item['nick_name'] = $wxUser['nick_name'];
                    $item['goods_name'] = !empty($goods['name']) ? $goods['name'] : '学车商品';
                    $item['tid'] = $sharingActiveUser['tid'];
                    $sendList[] = $item;

                    // 同步业务后台
                    $this->syncOrderToYYmanage($sharingActiveUser['tid'], $wxUser);
                }
                foreach($sendList as $user){
                    $data = [];
                    $data['mobile'] = $user['mobile'];
                    $data['openid'] = $user['openid'];
                    $data['goods_name'] = $user['goods_name'];
                    $data['tid'] = $user['tid'];
                    $data['sharing_user'] = $member;
                    $data['url'] = '/pages/group/groupDetail?group_id='.$shareActive['id'];
                    \app\common\service\MQProducer::getSingle('shop')->run($data, 'sharing_active_succ');
                }
            }
        }

        $this->where('id', '=', $shareActive['id'])->update([
            'actual_people' => $actual_people,
            'status' => $status
        ]);

        // 人员变更通知团长
        if($status != 20 && !empty($shareActive)  && !empty($shareActive['creator_id'])){
            $a = $shareActive['people'] - $actual_people;
            $goods = $groupProdctModel->getDetailNameById($shareActive['goods_id']);
//            $activity = $groupActivityModel->where('id', $goods['activity_id'])->find();
//            $activity_name = '拼团活动';
//            if(!empty($activity)){
//                $activity_name = $activity['name'];
//            }
            $sharingActiveUsers = $sauMod->where('active_id', '=', $shareActive['id'])->select();
            $member = ''; // 团长团员都通知
            foreach($sharingActiveUsers as $sharingActiveUser){
                $wxUser = $wxUserModel::get($sharingActiveUser['user_id']);
                \app\common\service\MQProducer::getSingle('shop')->run(['mobile' => $wxUser['mobile'], 'goods_name' => $goods['name'], 'actual_people' => $actual_people, 'balance_people' => $a], 'sharing_active_input');
                $member .= ' ' . $wxUser['nick_name'];
                $data = [];
                $data['openid'] = $wxUser['openid'];
                $data['goods_name'] = !empty($goods['name']) ? $goods['name'] : '学车套餐';
                $data['status'] = '已参团'.$actual_people.'人，仅差'.$a.'人即可成团';
                $data['at'] = date('Y-m-d H:i:s', $shareActive['end_time']);
                $data['remarks'] = '拼团';
                $data['url'] = '/pages/group/groupDetail?group_id='.$shareActive['id'];
                \app\common\service\MQProducer::getSingle('shop')->run($data, 'sharing_active_change');
            }
        }
        return true;
    }

    public function test($tid, $user){
        $this->syncOrderToYYmanage($tid, $user);
    }

    private function syncOrderToYYmanage($tid, $user_info){
        $orderModel = new OrdersModel();
        $order = $orderModel->alias('o')->where('o.tid', '=', $tid)->find();
        if(!empty($order)){
            $data = [];
            $data['tid'] = $order['tid']; // 订单号
            $data['nick_name'] = $user_info['nick_name'];
            $data['mobile'] = $user_info['mobile'];
            $data['user_id'] = $order['user_id'];
            $data['realname'] = !empty($order['realname']) ? $order['realname'] : ''; // 学员姓名
            $data['identify'] = !empty($order['identify']) ? $order['identify'] : ''; // 身份证号
            $data['order_type'] = $order['order_type']; // 10 单独购买 20 拼团订单
            $data['active_id'] = $order['active_id']; // 拼团活动id
            $data['total_fee'] = $order['total_fee']; // 订单总金额
            $data['pay_price'] = $order['pay_price']; // 支付金额
            $data['pay_type'] = $order['pay_type']; // 支付类型
            $data['pay_status'] = 1; // 支付状态
            $data['pay_time'] = $order['pay_time']; // 支付时间
            $data['item_message'] = $order['item_message'];
            $data['service_list'] = [];
            $items = is_string($order['item_message']) ? json_decode($order['item_message'], true) : [];
            if(!empty($items)){
                foreach($items as $item){
                    if(!empty($item['goods_id'])){ // 学车套餐
                        $path = explode(',', $item['path_text']);
                        $data['type'] = $item['goods_id']; // 套餐班型
                        if((strstr($path[0], '2') !== false) ||
                            (strstr($path[0], 'c2') !== false) ||
                            (strstr($path[0], 'C2') !== false)){
                            $data['driver_type'] = 2; // C2自动挡
                        }else{
                            $data['driver_type'] = 1; // C1手动挡
                        }
                    }elseif($item['checked']){ // 增值服务
                        $data['service_list'][] = ['amount' => $item['amount'], 'name' => $item['name']];
                    }
                }
            }
            $data['num'] = $order['num'];
            $data['item_id'] = $order['item_id'];
            $data['sku_id'] = $order['sku_id'];
            $data['transaction_id'] = $order['transaction_id']; // 支付流水号
            $data['create_time'] = $order['create_time'];
            // 标记跟进人
            $payLogModel = new PayLogModel();
            $payLog = $payLogModel
                ->where('tid', '=', $order['tid'])
                // ->where('status', '=', 1)
                ->order('create_time', 'DESC')->find();
            if(!empty($payLog)){
                $wxUserModel = new WxUserModel();
                $attach = json_decode($payLog['attach'], true);
                if(!empty($attach) && !empty($attach['follow_id'])){
                    $followUser = $wxUserModel
                        ->where('id', '=', intval($attach['follow_id']))
                        //->where('status', '=', 1)
                        ->where('delete_time', '=', 0)
                        ->find();
                    if(!empty($followUser)){
                        $data['follow_mobile'] = $followUser['mobile'];
                    }
                }
            }
            \app\common\service\MQProducer::getSingle('shop')->run($data, 'shop_order_payment');
        }
    }

    public function add($goods_id, $people, $creator_id, $end_time, $actual_people=0){
        $this->startTrans();
        try{
            $data = [];
            $data['goods_id'] = $goods_id;
            $data['people'] = $people;
            $data['actual_people'] = $actual_people;
            $data['creator_id'] = $creator_id;
            $data['end_time'] = $end_time;
            $data['status'] = 0;
            $data['create_time'] = time();
            $activeId = $this->insert($data);
            $this->commit();
            return $activeId;
        }catch (\Exception $e){
            $this->rollback();
            return false;
        }
    }

    public function creator() {
        return $this->belongsTo('WxUserModel', 'creator_id')->where('delete_time', 0); 
    }
}
