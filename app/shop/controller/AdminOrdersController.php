<?php
namespace app\shop\controller;

use api\shop\model\WxUserModel;
use app\common\enum\SharingStatus;
use app\common\model\OrderLogModel;
use app\common\model\PayLogModel;
use app\common\service\HuilaimiPay;
use app\shop\model\GroupActivityModel;
use app\shop\model\GroupProductModel;
use app\shop\model\OrdersModel;
use cmf\controller\AdminBaseController;
use app\shop\model\ProductModel;
use app\common\enum\OrderStatus;

class AdminOrdersController extends AdminBaseController
{
    private $searchArray;
    public function index()
    {
        $this->searchArray['search_key'] = $this->request->param('search_key', '', 'trim');
        $this->searchArray['search_value'] = $this->request->param('search_value', '', 'trim');
        $this->searchArray['tid'] = $this->request->param('tid', '', 'trim');
        $this->searchArray['order_type'] = $this->request->param('order_type', 0, 'intval');
        $this->searchArray['some_state'] = $this->request->param('some_state', 0, 'intval');
        $this->searchArray['manbipei'] = $this->request->param('manbipei', '', 'trim');
        $this->searchArray['start_time'] = $this->request->param('start_time', '', 'trim');
        $this->searchArray['end_time'] = $this->request->param('end_time', '', 'trim');
        $this->searchArray['status'] = $this->request->param('status', 'all', 'trim');
        $productModel = new ProductModel();
        $orderMod = new OrdersModel();
        $model = $orderMod->alias('o')
            ->field('o.*,p.name pname,gp.name gpname,u.mobile,u.get_id,g.nick_name as g_name,u.nick_name,sa.create_time sharing_time');
            //->where('o.status', '<>', 2);
        $model->leftJoin('product p', 'o.item_id = p.id')
            ->leftJoin('group_product gp', 'o.item_id = gp.id')
            ->leftJoin('wx_user u', 'o.user_id = u.id')
            ->leftJoin('wx_user g', 'u.get_id = g.id')
            ->leftJoin('sharing_active sa', 'o.active_id = sa.id');
        if(!empty($this->searchArray['search_key']) && !empty($this->searchArray['search_value'])){
            switch ($this->searchArray['search_key']){
                case 'tid':
                    $model->where('tid', '=', $this->searchArray['search_value']);
                    break;
                case 'goods_name':
                    $model->whereOr('p.name', 'LIKE', '%'.$this->searchArray['search_value'].'%');
                    $model->whereOr('gp.name', 'LIKE', '%'.$this->searchArray['search_value'].'%');
                    break;
                case 'mobile':
                    $model->where('u.mobile', '=', $this->searchArray['search_value']);
                    break;
                case 'xxx':
                    break;
                case 'transaction_id':
                    $model->where('transaction_id', '=', $this->searchArray['search_value']);
                    break;
                case 'realname':
                    $model->where('o.realname','LIKE', '%'.$this->searchArray['search_value'].'%');
                    break;
                case 'identify':
                    $model->where('o.identify','=', $this->searchArray['search_value']);
                    break;
            }
        }
        if(!empty($this->searchArray['order_type']) && $this->searchArray['order_type'] > 0){
            $model->where('order_type', '=', $this->searchArray['order_type']);
        }
        if(!empty($this->searchArray['manbipei']) && $this->searchArray['manbipei'] == 'you'){
            $model->where('item_message', 'LIKE', '%manbipei%');
        }
        if(!empty($this->searchArray['manbipei']) && $this->searchArray['manbipei'] == 'wu'){
            $model->where('item_message', 'NOT LIKE', '%manbipei%');
        }
        if(!empty($this->searchArray['tid'])){
            $model->where('tid', $this->searchArray['tid']);
//            $model->where('tid', 'like', '%'.$tid.'%');
        }
        if(!empty($this->searchArray['status']) && $this->searchArray['status'] != 'all'){
            $model->where('pay_status', $this->searchArray['status']);

        }
        if(!empty($this->searchArray['some_state'])){
            switch ($this->searchArray['some_state']){
                case 1000:
                    $model->where('o.status', '<>', OrderStatus::CANCEL);
                    $model->where('pay_status', '=', OrderStatus::WAIT_BUYER_PAY);
                    break;
                case 2000:
                    $model->where('o.status', '<>', OrderStatus::CANCEL);
                    $model->where('active_id', '>', 0);
                    $model->where('sa.status', '=', 10);
                    break;
                case 3000:
                    $model->where('o.status', '<>', OrderStatus::CANCEL);
                    $model->where('pay_status', '=', OrderStatus::BUYER_IS_PAY);
                    break;
                case 4000:
                    $model->where('o.status', '=', OrderStatus::CANCEL);
                    break;
                case 5000:
                    $model->where('pay_status', '=', OrderStatus::ORDER_REFUND);
                    break;
            }
        }
        if(!empty($this->searchArray['start_time'])){
            $model->where('o.create_time', '>=', strtotime($this->searchArray['start_time']. ' 0:0:0'));
        }
        if(!empty($this->searchArray['end_time'])){
            $model->where('o.create_time', '<=', strtotime($this->searchArray['end_time'] . ' 23:59:59'));
        }
        $data = $model->paginate(20)->appends($this->searchArray);
        $this->assign('status_map', OrderStatus::getStatusMap());
        $this->assign('pay_status_map', OrderStatus::getPayStatusMap());
        $this->assign('pay_type_map', OrderStatus::getPayTypeMap());
        $this->assign('types', $productModel->typeText());
        $this->assign('order_types', OrderStatus::getOrderType());
        $this->assign('search_key', $this->searchArray['search_key']);
        $this->assign('search_value', $this->searchArray['search_value']);
        $this->assign('order_type', $this->searchArray['order_type']);
        $this->assign('some_state', $this->searchArray['some_state']);
        $this->assign('manbipei', $this->searchArray['manbipei']);
        $this->assign('start_time', $this->searchArray['start_time']);
        $this->assign('end_time', $this->searchArray['end_time']);
        $this->assign('status', $this->searchArray['status']);
        $this->assign('orders', $data);
        $this->assign('page', $data->render());
        $this->assign('exports', http_build_query($this->searchArray));
        return $this->fetch();
    }

    public function add()
    {
        echo 'add';die;
    }

    public function edit()
    {
        $tid = $this->request->param('tid', '', 'trim');
        if(empty($tid)){
            $this->error('缺少参数~',url('AdminOrders/index'));
        }
        $orderMod = new OrdersModel();
        $order = $orderMod->alias('o')
            ->field('o.*,p.name pname,gp.name gpname,u.mobile,u.get_id,g.nick_name as g_name,u.nick_name,
            sa.create_time sharing_time,sa.status sharing_status,ga.name activity_name,
            ga.id activity_id,ga.product_id gpid,uc.id coupon_id,c.name coupon_name,c.amount coupon_amount')
            ->leftJoin('product p', 'o.item_id = p.id')
            ->leftJoin('group_product gp', 'o.item_id = gp.id')
            ->leftJoin('wx_user u', 'o.user_id = u.id')
            ->leftJoin('wx_user g', 'u.get_id = g.id')
            ->leftJoin('sharing_active sa', 'o.active_id = sa.id')
            ->leftJoin('group_activity ga', 'gp.activity_id = ga.id')
            ->leftJoin('user_coupon uc', 'o.user_id = uc.user_id')
            ->leftJoin('coupon c', 'uc.coupon_id = c.id')
            ->where('o.tid', $tid)
            ->find();
        if(empty($order)){
            $this->error('订单不存在~',url('AdminOrders/index'));
        }
        $jump = $order['item_id'];
        if(!empty($order['active_id']) > 0 && !empty($order['gpid'])){
            $jump = $order['gpid'];
        }
        $productModel = new ProductModel();
        $activity = [];
        if($order['order_type'] == OrderStatus::SHARING){ // 拼团信息
            $groupProductModel = new GroupProductModel();
            $groupActivityModel = new GroupActivityModel();
            $goods = $groupProductModel::get($order['item_id']);
            $activity = $groupActivityModel::get($goods['activity_id']);
        }else{
            $goods = $productModel::get(['id', '=', $order->item_id]);
        }
        $status_text = '';
        $status_remarks = '';
        if($order['pay_status'] == OrderStatus::WAIT_BUYER_PAY){
            $status_text = '待支付';
            $status_remarks = '等待客户支付。';
        }elseif($order['pay_status'] == OrderStatus::BUYER_IS_PAY && $order['sharing_status'] == SharingStatus::SHARING_ING) {
            $status_text = '待成团';
            $status_remarks = '拼团中，成团后订单状态将变为已完成。';
        }elseif($order['status'] == 2 && $order['pay_status'] == OrderStatus::BUYER_IS_PAY && $order['refund_state'] == OrderStatus::REFUND_FAIL && $order['sharing_status'] == SharingStatus::SHARINGFAIL){
            $status_text = '已支付';
            $status_remarks = '拼团关闭：退款失败，订单未关闭。';
        }elseif($order['status'] != 2 && $order['pay_status'] == OrderStatus::BUYER_IS_PAY || ($order['status'] !=2 && $order['sharing_status'] == SharingStatus::SHARINGSUCC)){
            $status_text = '已完成';
            $status_remarks = '订单已完成，客户进入学车流程。';
        }elseif($order['pay_status'] == OrderStatus::ORDER_REFUND || $order['sharing_status'] == SharingStatus::SHARINGFAIL){
            $status_text = '已关闭';
            $status_remarks = '拼团失败：已原路退款，订单已关闭。';
        }elseif($order['status'] == 2) {
            $status_remarks = '支付失败，订单已关闭。';
        }
        $service_map = OrderStatus::getServiceNameBackendMap();
        $items = [];
        foreach(json_decode($order->item_message, true) as $i){
            $item = [];
            $item['sku_id'] = $i['id'];
            if(!empty($i['path_text'])){
                $item['sku_name'] = $i['path_text'];
                $item['sku_title'] = $i['path_text'];
            }elseif(!empty($i['name'])){
                foreach($service_map as $k => $v){
                    if($k == $i['name']){
                        $item['sku_name'] = $v;
                        $item['sku_title'] = $v;
                    }
                }
            }
            $items[] = $item;
        }
        $this->assign('tid', $tid);
        $this->assign('status_map', OrderStatus::getStatusMap());
        $this->assign('pay_status_map', OrderStatus::getPayStatusMap());
        $this->assign('pay_type_map', OrderStatus::getPayTypeMap());
        $this->assign('statuses', $productModel->statusText());
        $this->assign('types', $productModel->typeText());
        $this->assign('order_types', OrderStatus::getOrderType());
        $this->assign('manbipeis', $productModel->manbipeiText());
        $this->assign('require_info_types', $productModel->requireInfoTypeText());
        $this->assign('data', $order);
        $this->assign('status_text', $status_text);
        $this->assign('status_remarks', $status_remarks);
        $this->assign('goods', $goods);
        $this->assign('items', $items);
        $this->assign('jump', $jump);
        $this->assign('activity', $activity);
        return $this->fetch();
    }

    public function refund(){
        $tid = $this->request->param('tid', '', 'trim');
        if(empty($tid)){
            $this->error('缺少参数~');
        }
        $pay = new HuilaimiPay();
        $payLogMod = new PayLogModel();
        $orderMod = new OrdersModel();
        $orderLogMod = new OrderLogModel();
        $order = $orderMod->where('tid', '=', $tid)->find();
        if(empty($order)){
            $this->error('订单不存在~');
        }
        if($order['status'] == OrderStatus::CANCEL || $order['pay_status'] != OrderStatus::BUYER_IS_PAY || empty($order['transaction_id'])){
            $this->error('订单状态不匹配~');
        }
        try{
            $payLog = $payLogMod->getRefundPayIdByTid($order['tid']);
            if(empty($payLog)){
                $this->error('支付日志不存在~');
            }
            $ret = $pay->refund($order['user_id'], $payLog['pay_id'], $order['transaction_id'], $order['pay_price']);
            if(!$ret['paid']){
                $orderMod->where('tid', '=', $order['tid'])->update([
                    'update_time' => time(),'refund_state' => OrderStatus::REFUND_FAIL,
                    'refund_time' => time(), 'refund_message' => json_encode($ret)
                ]);
                $this->error($ret['resp_desc']);
            }
            $flag = $orderMod->where('tid', '=', $order['tid'])->update([
                'update_time' => time(),'status' => OrderStatus::CANCEL,
                'pay_status' => OrderStatus::ORDER_REFUND, 'refund_state' => OrderStatus::REFUND_SUCCESS,
                'refund_time' => time(), 'refund_message' => json_encode($ret)
            ]);
            if($flag){
                $old_order = $order->toArray();
                $order['status'] = OrderStatus::CANCEL;
                $order['desc'] = '手动关闭拼团订单取消[退款]';
                $orderLogMod->addOrderLog($order, $old_order, session('name'), 'cancel');
            }
            $this->success('订单已退款~');
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
    }

    public function save()
    {
        if ($this->request->isPost()) {
            $data = $this->request->param();
            $post   = $data['post'];
            $result = $this->validate($post, 'AdminOrders.edit');
            if ($result !== true) {
                $this->error($result);
            }
            $orderMod = new OrdersModel();
            $rs = $orderMod->updateOrder($post);
            if($rs === 1){
                $this->success('修改成功~');
            }elseif(is_string($rs)){
                $this->error($rs);
            }else{
                $this->error('修改失败~');
            }
        }
    }

    public function export(){
        $order_types = OrderStatus::getOrderType();
        $pay_status_map = OrderStatus::getPayStatusMap();
        $params = $this->request->param('params');
        $params = parse_url_params($params);
        $orderMod = new OrdersModel();
        $model = $orderMod->alias('o')->field('o.*,p.name pname,gp.name gpname,u.mobile,u.get_id,g.nick_name as g_name,u.nick_name,u.source_type,sa.create_time sharing_time');
        $model->leftJoin('product p', 'o.item_id = p.id')
            ->leftJoin('group_product gp', 'o.item_id = gp.id')
            ->leftJoin('wx_user u', 'o.user_id = u.id')
            ->leftJoin('wx_user g', 'u.get_id = g.id')
            ->leftJoin('sharing_active sa', 'o.active_id = sa.id');
        if(!empty($params['search_key']) && !empty($params['search_value'])){
            switch ($params['search_key']){
                case 'tid':
                    $model->where('tid', '=', $params['search_value']);
                    break;
                case 'goods_name':
                    $model->whereOr('p.name', 'LIKE', '%'.$params['search_value'].'%');
                    $model->whereOr('gp.name', 'LIKE', '%'.$params['search_value'].'%');
                    break;
                case 'mobile':
                    $model->where('u.mobile', '=', $params['search_value']);
                    break;
                case 'xxx':
                    break;
                case 'transaction_id':
                    $model->where('transaction_id', '=', $params['search_value']);
                    break;
                case 'realname':
                    $model->where('o.realname','LIKE', '%'.$params['search_value'].'%');
                    break;
                case 'identify':
                    $model->where('o.identify','=', $params['search_value']);
                    break;
            }
        }
        if(!empty($params['order_type']) && $params['order_type'] > 0){
            $model->where('order_type', '=', $params['order_type']);
        }
        if(!empty($params['manbipei']) && $params['manbipei'] == 'you'){
            $model->where('item_message', 'LIKE', '%manbipei%');
        }
        if(!empty($params['manbipei']) && $params['manbipei'] == 'wu'){
            $model->where('item_message', 'NOT LIKE', '%manbipei%');
        }
        if(!empty($params['tid'])){
            $model->where('tid', $params['tid']);
        }
        if(!empty($params['status']) && $params['status'] != 'all'){
            $model->where('pay_status', $params['status']);
        }
        if(!empty($params['some_state'])){
            switch ($params['some_state']){
                case 1000:
                    $model->where('o.status', '<>', OrderStatus::CANCEL);
                    $model->where('pay_status', '=', OrderStatus::WAIT_BUYER_PAY);
                    break;
                case 2000:
                    $model->where('o.status', '<>', OrderStatus::CANCEL);
                    $model->where('active_id', '>', 0);
                    $model->where('sa.status', '=', 10);
                    break;
                case 3000:
                    $model->where('o.status', '<>', OrderStatus::CANCEL);
                    $model->where('pay_status', '=', OrderStatus::BUYER_IS_PAY);
                    break;
                case 4000:
                    $model->where('o.status', '=', OrderStatus::CANCEL);
                    break;
                case 5000:
                    $model->where('pay_status', '=', OrderStatus::ORDER_REFUND);
                    break;
            }
        }
        if(!empty($params['start_time'])){
            $model->where('o.create_time', '>=', strtotime($params['start_time']. ' 0:0:0'));
        }
        if(!empty($params['end_time'])){
            $model->where('o.create_time', '<=', strtotime($params['end_time'] . ' 23:59:59'));
        }
        $data = $model->paginate(100000)->toArray();
        $rows = [];
        foreach($data['data'] as $d){
            $row = [];
            $row[] = !empty($d['tid']) ? $d['tid'] : '';
            $row[] = !empty($d['order_type']) ? $order_types[$d['order_type']] : '';
            if($d['active_id']>0){
                $goods_name = $d['gpname'];
            }else{
                $goods_name = $d['pname'];
            }
            $row[] = $goods_name;
            $path_text = '';
            if(!empty($d['item_message'])){
                $items = json_decode($d['item_message'], true);
                foreach($items as $item){
                    if(!empty($item['goods_id'])){
                        $path = explode(',', $item['path_text']);
                        if(!empty($path[0])){
                            $path_text .= $path[0] . ' ';
                        }
                        if(!empty($path[1])){
                            $path_text .= $path[1] . ' ';
                        }
                    }
                }
            }
            $row[] = $path_text;
            $row[] = !empty($d['total_fee']) ? $d['total_fee'] : 0;
            $row[] = !empty($d['pay_price']) ? $d['pay_price'] : 0;
            $row[] = !empty($d['nick_name']) ? $d['nick_name'] : '';
            $row[] = !empty($d['mobile']) ? $d['mobile'] : '';
            $row[] = !empty($d['source_type']) ? $d['source_type'] : '';
            if($d['status'] == 2){
                $status_text = '已关闭';
            }else{
                $status_text = $pay_status_map[$d['pay_status']];
            }
            $row[] = $status_text;
            $row[] = $d['get_id']>0 ? $d['g_name'] : '';
            $row[] = !empty($d['sharing_time']) ? date('Y-m-d H:i:s', $d['sharing_time']) : '';
            $row[] = !empty($d['pay_time']) ? date('Y-m-d H:i:s', $d['pay_time']) : '';
            $row[] = !empty($d['create_time']) ? date('Y-m-d H:i:s', $d['create_time']) : '';
            $rows[] = $row;
        }
        $title = ['订单号','订单类型','商品名称','规格','单价','实收金额','购买人','手机号','来源渠道','订单状态','获客人','成团时间','支付时间','下单时间'];
        export_excel(date('YmdHis').'_order.xls', $title, $rows);exit;
    }

    public function repush(){
        $tid = $this->request->param('tid', '', 'trim');
        if(empty($tid)){
            $this->error('缺少参数~');
        }
        $orderMod = new OrdersModel();
        $order = $orderMod->alias('o')->where('tid', '=', $tid)->leftJoin('wx_user u', 'o.user_id = u.id')->find();
        if(empty($order)){
            $this->error('订单不存在~');
        }
        if($order['status'] == OrderStatus::CANCEL || $order['pay_status'] != OrderStatus::BUYER_IS_PAY || empty($order['transaction_id'])){
            $this->error('订单状态不匹配~');
        }
        $data = [];
        $data['tid'] = $order['tid']; // 订单号
        $data['nick_name'] = $order['nick_name'];
        $data['mobile'] = $order['mobile'];
        $data['user_id'] = $order['user_id'];
        $data['realname'] = !empty($order['realname']) ? $order['realname'] : ''; // 姓名
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
                if(!empty($item['goods_id'])){ // 套餐
                    $path = explode(',', $item['path_text']);
                    $data['type'] = $item['goods_id']; // 套餐型
                    if((strstr($path[0], '2') !== false) ||
                        (strstr($path[0], 'c2') !== false) ||
                        (strstr($path[0], 'C2') !== false)){
                        $data['driver_type'] = 2;
                    }else{
                        $data['driver_type'] = 1;
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
        $payLog = $payLogModel->where('tid', '=', $order['tid'])->where('status', '=', 1)->order('create_time', 'DESC')->find();
        if(!empty($payLog)){
            $wxUserModel = new WxUserModel();
            $attach = json_decode($payLog['attach'], true);
            if(!empty($attach) && !empty($attach['follow_id'])){
                $followUser = $wxUserModel->where('id', intval($attach['follow_id']))->where('status', 1)->where('delete_time', 0)->find();
                if(!empty($followUser)){
                    $data['follow_mobile'] = $followUser['mobile'];
                }
            }
        }
        $falg = \app\common\service\MQProducer::getSingle('shop')->run($data, 'shop_order_payment');
        if($falg){
            $this->success('订单已发起同步~');
        }else{
            $this->error('订单同步失败~');
        }
        $this->error('订单同步失败~');
    }

    public function delete()
    {
       echo 'delete';die;
    }
}