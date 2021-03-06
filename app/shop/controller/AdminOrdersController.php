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
            $this->error('????????????~',url('AdminOrders/index'));
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
            $this->error('???????????????~',url('AdminOrders/index'));
        }
        $jump = $order['item_id'];
        if(!empty($order['active_id']) > 0 && !empty($order['gpid'])){
            $jump = $order['gpid'];
        }
        $productModel = new ProductModel();
        $activity = [];
        if($order['order_type'] == OrderStatus::SHARING){ // ????????????
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
            $status_text = '?????????';
            $status_remarks = '?????????????????????';
        }elseif($order['pay_status'] == OrderStatus::BUYER_IS_PAY && $order['sharing_status'] == SharingStatus::SHARING_ING) {
            $status_text = '?????????';
            $status_remarks = '??????????????????????????????????????????????????????';
        }elseif($order['status'] == 2 && $order['pay_status'] == OrderStatus::BUYER_IS_PAY && $order['refund_state'] == OrderStatus::REFUND_FAIL && $order['sharing_status'] == SharingStatus::SHARINGFAIL){
            $status_text = '?????????';
            $status_remarks = '????????????????????????????????????????????????';
        }elseif($order['status'] != 2 && $order['pay_status'] == OrderStatus::BUYER_IS_PAY || ($order['status'] !=2 && $order['sharing_status'] == SharingStatus::SHARINGSUCC)){
            $status_text = '?????????';
            $status_remarks = '?????????????????????????????????????????????';
        }elseif($order['pay_status'] == OrderStatus::ORDER_REFUND || $order['sharing_status'] == SharingStatus::SHARINGFAIL){
            $status_text = '?????????';
            $status_remarks = '???????????????????????????????????????????????????';
        }elseif($order['status'] == 2) {
            $status_remarks = '?????????????????????????????????';
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
            $this->error('????????????~');
        }
        $pay = new HuilaimiPay();
        $payLogMod = new PayLogModel();
        $orderMod = new OrdersModel();
        $orderLogMod = new OrderLogModel();
        $order = $orderMod->where('tid', '=', $tid)->find();
        if(empty($order)){
            $this->error('???????????????~');
        }
        if($order['status'] == OrderStatus::CANCEL || $order['pay_status'] != OrderStatus::BUYER_IS_PAY || empty($order['transaction_id'])){
            $this->error('?????????????????????~');
        }
        try{
            $payLog = $payLogMod->getRefundPayIdByTid($order['tid']);
            if(empty($payLog)){
                $this->error('?????????????????????~');
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
                $order['desc'] = '??????????????????????????????[??????]';
                $orderLogMod->addOrderLog($order, $old_order, session('name'), 'cancel');
            }
            $this->success('???????????????~');
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
                $this->success('????????????~');
            }elseif(is_string($rs)){
                $this->error($rs);
            }else{
                $this->error('????????????~');
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
                $status_text = '?????????';
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
        $title = ['?????????','????????????','????????????','??????','??????','????????????','?????????','?????????','????????????','????????????','?????????','????????????','????????????','????????????'];
        export_excel(date('YmdHis').'_order.xls', $title, $rows);exit;
    }

    public function repush(){
        $tid = $this->request->param('tid', '', 'trim');
        if(empty($tid)){
            $this->error('????????????~');
        }
        $orderMod = new OrdersModel();
        $order = $orderMod->alias('o')->where('tid', '=', $tid)->leftJoin('wx_user u', 'o.user_id = u.id')->find();
        if(empty($order)){
            $this->error('???????????????~');
        }
        if($order['status'] == OrderStatus::CANCEL || $order['pay_status'] != OrderStatus::BUYER_IS_PAY || empty($order['transaction_id'])){
            $this->error('?????????????????????~');
        }
        $data = [];
        $data['tid'] = $order['tid']; // ?????????
        $data['nick_name'] = $order['nick_name'];
        $data['mobile'] = $order['mobile'];
        $data['user_id'] = $order['user_id'];
        $data['realname'] = !empty($order['realname']) ? $order['realname'] : ''; // ??????
        $data['identify'] = !empty($order['identify']) ? $order['identify'] : ''; // ????????????
        $data['order_type'] = $order['order_type']; // 10 ???????????? 20 ????????????
        $data['active_id'] = $order['active_id']; // ????????????id
        $data['total_fee'] = $order['total_fee']; // ???????????????
        $data['pay_price'] = $order['pay_price']; // ????????????
        $data['pay_type'] = $order['pay_type']; // ????????????
        $data['pay_status'] = 1; // ????????????
        $data['pay_time'] = $order['pay_time']; // ????????????
        $data['item_message'] = $order['item_message'];
        $data['service_list'] = [];
        $items = is_string($order['item_message']) ? json_decode($order['item_message'], true) : [];
        if(!empty($items)){
            foreach($items as $item){
                if(!empty($item['goods_id'])){ // ??????
                    $path = explode(',', $item['path_text']);
                    $data['type'] = $item['goods_id']; // ?????????
                    if((strstr($path[0], '2') !== false) ||
                        (strstr($path[0], 'c2') !== false) ||
                        (strstr($path[0], 'C2') !== false)){
                        $data['driver_type'] = 2;
                    }else{
                        $data['driver_type'] = 1;
                    }
                }elseif($item['checked']){ // ????????????
                    $data['service_list'][] = ['amount' => $item['amount'], 'name' => $item['name']];
                }
            }
        }
        $data['num'] = $order['num'];
        $data['item_id'] = $order['item_id'];
        $data['sku_id'] = $order['sku_id'];
        $data['transaction_id'] = $order['transaction_id']; // ???????????????
        $data['create_time'] = $order['create_time'];
        // ???????????????
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
            $this->success('?????????????????????~');
        }else{
            $this->error('??????????????????~');
        }
        $this->error('??????????????????~');
    }

    public function delete()
    {
       echo 'delete';die;
    }
}