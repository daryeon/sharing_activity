<?php
namespace api\shop\controller;

use api\shop\model\CouponModel;
use api\shop\model\GroupActivityModel;
use api\shop\model\GroupProductModel;
use api\shop\model\GroupProductSkuModel;
use api\shop\model\SharingActiveModel;
use api\shop\model\SharingActiveUsersModel;
use app\common\enum\OrderStatus;
use app\common\enum\SharingStatus;
use app\common\model\OrdersModel;
use app\common\model\ProductModel;
use api\shop\controller\RestBaseController;
use api\shop\model\WxUserModel;
use think\App;
use think\cache\driver\Redis;


class GoodsController extends RestBaseController
{
    private $model;
    protected $need_login;
    function __construct(App $app = null)
    {
        $this->need_login = false;
        $this->model = new ProductModel();
        parent::__construct($app);
    }

    public function test(){
        $redisSer = new Redis();
        if($redisSer->has('hello')){
            $v = $redisSer->get('hello');
            $this->success('ok', compact('v'));
        }else{
            $v = 1;
            $redisSer->set('hello', $v, 5);
            $this->success('ok', compact('v'));
        }
    }

    /**
     * 商品列表
     * @param $list_rows 每页条数
     * @param $page 页码
     * @return array
     * @throws \think\exception\DbException
     */
    public function lists()
    {
        $listRows = $this->request->param('list_rows', 10, 'intval');
        $list = $this->model->getList($listRows);
        $this->success('ok' , compact('list'));
    }

    /**
     * 获取商品详情
     * @param $goods_id
     * @return array
     * @throws \app\common\exception\BaseException
     * @throws \think\exception\DbException
     */
    public function detail()
    {
        // 商品详情
        $goodId = $this->request->param('goods_id', 0, 'intval');
        $org_id = $this->request->param('org_id', 0, 'intval');
        $active_id = $this->request->param('active_id', 0, 'intval');
        if(empty($goodId)){
            $this->error(['code' => 20210101, 'msg' => '很抱歉，商品信息不存在或已下架']);
        }
        $detail = $this->model->detail($goodId);
        $isSharing = false;$spMod = new GroupProductModel();
        if((empty($detail) && !empty($org_id)) || !empty($org_id)){
            // 拼团商品
            $detail = $spMod->detail($goodId);
            if(empty($detail)){
                $this->error(['code' => 20210101, 'msg' => '很抱歉，活动已结束']);
            }
            // -- 检查拼团商品是否是在执行活动商品
            $activityMod = new GroupActivityModel();
            $activityProcessing = $activityMod->where('id','=',$detail['activity_id'])->find();
            if(!$activityProcessing){
                $this->error(['code' => 20210101, 'msg' => '很抱歉，活动已结束']);
            }

            $isSharing = true;
        }
        if(empty($detail)){
            $this->error(['code' => 20210101, 'msg' => '很抱歉，商品信息不存在或已下架']);
        }
        if(!empty($detail['video_src']) || !empty($detail['detail_src']) || !empty($detail['tx_video_id']) ){
            // video_src // detail_src
            $arr = [];
            $video_src = is_array($detail['video_src']) ? $detail['video_src'] : [$detail['video_src']];
            foreach($video_src as $video){
                if(!empty($video) && !empty($detail['cover_src'])){
                    $arr[] = ['type' => 'video', 'url' => $video, 'cover' => $detail['cover_src']];
                }
            }

            $tx_video_ids = is_array($detail['tx_video_id']) ? $detail['tx_video_id'] : [$detail['tx_video_id']];
            foreach($tx_video_ids as $video){
                if(!empty($video) && !empty($detail['cover_src'])){
                    $arr[] = ['type' => 'video', 'tx_video_id' => $video, 'cover' => $detail['cover_src']];
                }
            }

            $arr[] = ['type' => 'image', 'url' => $detail['main_cover_src']];
            $image_src = is_array($detail['detail_src']) ? $detail['detail_src'] : [$detail['detail_src']];
            foreach($image_src as $image){
                if(!empty($image)){
                    $arr[] = ['type' => 'image', 'url' => $image];
                }
            }
            $detail['somebox'] = $arr;
            //拼团分享链接进来的不显示陌生人拼团
            $detail['strange_switch'] = ($active_id==0) ? (empty($detail['strange_switch'])?0:$detail['strange_switch']) :0;
        }

        $saMod = new \api\shop\model\SharingActiveModel();
        $sharing_active_info = [];
        if($active_id > 0){
            $sharing_active_info = $saMod::get($active_id);
        }

        $max_coupon_price = 0;

        $sharing_active = [];
        if($isSharing){ // 拼团商品
            // 获取规格
            $properties = $this->model->getProperty($detail['org_id']);

            $skus = $spMod->getSkusByGoodId($detail['id'], $detail['org_id']);
            $detail = $detail->toArray() + $this->getPrice2($skus,$detail['org_id']);

            // 获取产品扩展值
            $extra = $spMod->getExtra($detail['id']);

            $sharing_active = [];
            if(!empty($detail['activity_id'])){
                $groupActivityMod = new GroupActivityModel();
                $sharing_active = $groupActivityMod->get($detail['activity_id']);
            }
            if(!empty($sharing_active)){
                $sharing_active['leave_time'] = $sharing_active['end_time'] - time();
                $sharing_active['partin_limit'] = /*$spMod->sharingActiveSucc()->count() + */$sharing_active['fake_count'];
            }

            $max_coupon_price = 0;
        }else{
            // 获取规格
            $properties = $this->model->getProperty($detail['id']);
            // 获取sku路径
            $skus = $this->model->getSkusByGoodId($detail['id']);
            $detail = $detail->toArray() + $this->getPrice($skus,$detail['id']);

            // 优惠券
            $couponModel = new CouponModel();
            $bigCoupon = $couponModel->cheapestCounpon($detail['id']);
            if(!empty($bigCoupon)){
                $max_coupon_price = round($bigCoupon,2);
            }

            // 获取产品扩展值
            $extra = $this->model->getExtra($detail['id']);
        }
        // 更多推荐
        $more['data'] = $this->get_more($detail['id']);

        $this->success('ok', compact(
            'detail',
            'properties',
            'extra', 'skus',
            'more',
            'sharing_active',
            'sharing_active_info',
            'max_coupon_price'
        ));
    }

    /**
     * 可拼团订单
     */
    public function can_join_active(){
        $goodId = $this->request->param('goods_id', 0, 'intval');
        $org_id = $this->request->param('org_id', 0, 'intval');
        $active_id = $this->request->param('active_id', 0, 'intval');
        if(empty($goodId)){
            $this->error(['code' => 20210101, 'msg' => '很抱歉，商品信息不存在或已下架']);
        }
        $detail = $this->model->detail($goodId);
        $spMod = new GroupProductModel();
        if((empty($detail) && !empty($org_id)) || !empty($org_id)){
            // 拼团商品
            $detail = $spMod->detail($goodId);
            if(empty($detail)){
                $this->error(['code' => 20210101, 'msg' => '很抱歉，活动已结束']);
            }
            // -- 检查拼团商品是否是在执行活动商品
            $activityMod = new GroupActivityModel();
            $activityProcessing = $activityMod->where('id','=',$detail['activity_id'])->find();
            if(!$activityProcessing){
                $this->error(['code' => 20210101, 'msg' => '很抱歉，活动已结束']);
            }
        }
        if(empty($detail)){
            $this->error(['code' => 20210101, 'msg' => '很抱歉，商品信息不存在或已下架']);
        }

        $order_mod = new OrdersModel();
        $orders_act = $order_mod->where('item_id',$goodId)
                    ->where('order_type',OrderStatus::SHARING)
                    ->where('pay_status',OrderStatus::BUYER_IS_PAY)
                    ->column('distinct active_id');
        $sharing_active_mod = new SharingActiveModel();
        $thisTime = time();
        $sharing_actives = $sharing_active_mod
                                            ->whereIn('id',$orders_act)
                                            ->where('status',SharingStatus::SHARING_ING)
                                            ->order('actual_people', 'DESC')
                                            ->order('end_time', 'ASC')
                                            ->limit(2)
                                            ->select();

        if (!empty($sharing_actives)){
            foreach ($sharing_actives as $k => &$v){
                $v['user_info'] = WxUserModel::get($v['creator_id']);
            }
        }
        $this->success('ok', $sharing_actives);

    }

    public function myorder(){
        $user_id = $this->getUserId();
        $orderModel = new OrdersModel();
        $order = $orderModel->getOrderByUserId($user_id);
        $this->success('ok', compact('order'));
    }

    private function getPrice2($skus,$org_id){
        $ret = ['min_along_price' => 0, 'min_price' => 0, 'max_price' => 0, 'org_min_price' => 0, 'org_max_price' => 0];
        if(empty($skus)){
            return $ret;
        }
        $ret['min_along_price'] = min(array_column($skus, 'price'));
        //$ret['org_min_price'] = min(array_column($skus, 'price'));
        //$ret['org_max_price'] = max(array_column($skus, 'org_price'));
        $ret['min_price'] = min(array_column($skus, 'group_price'));
        foreach($skus as $sku){
            if($ret['min_price'] == $sku['group_price']){
                $ret['org_min_price'] = $sku['price'];
            }
        }
        $couponModel = new CouponModel();
//        $coupon = $couponModel->biggestCoupon()->find();
        $coupon['amount'] = $couponModel->cheapestCounpon($org_id);

        $maxCoupon = 0;
        if(!empty($coupon)){
            $maxCoupon = $coupon['amount'];
        }

        $ret['min_coupon2_price'] = round(($ret['org_min_price'] - $maxCoupon),2);
        //$ret['max_price'] = max(array_column($skus, 'price'));
        $ret['min_coupon_price'] = round($ret['min_price'],2);
        return $ret;
    }

    private function getPrice($skus,$org_id){
        $ret = ['min_price' => 0, 'max_price' => 0, 'org_min_price' => 0, 'org_max_price' => 0];
        if(empty($skus)){
            return $ret;
        }
        $ret['org_min_price'] = min(array_column($skus, 'org_price'));
        //$ret['org_max_price'] = max(array_column($skus, 'org_price'));
        $ret['min_price'] = min(array_column($skus, 'price'));
        //$ret['max_price'] = max(array_column($skus, 'price'));
        $couponModel = new CouponModel();
//        $coupon = $couponModel->biggestCoupon()->find();
        $coupon['amount'] = $couponModel->cheapestCounpon($org_id);

        $maxCoupon = 0;
        if(!empty($coupon)){
            $maxCoupon = $coupon['amount'];
        }
        $ret['min_coupon_price'] = round(($ret['min_price'] - $maxCoupon),2);
        return $ret;
    }

    private function get_more($id){
        $activityMod = new \app\shop\model\GroupActivityModel();
        $groupProductMod = new GroupProductModel();
        $productModel = new \api\shop\model\ProductModel();
        $this_time = time();
        $products = $productModel->order("list_order ASC")->order("id DESC")->where('id','<>',$id)->where(['delete_time' => 0, 'status' => 1])->limit(2)->select();

        $product_ids = $activityMod->where('status','=',1)
            ->where('start_time','<=',$this_time)
            ->where('end_time','>=',$this_time)
            ->column('id,product_id,person_limit');
        $group_ids = [];
        $group_products = [];
        if(!empty($product_ids)){
            foreach ($product_ids as $k=>$v){
                $activity_product_arr = explode(',',$v['product_id']);        //一个活动里面包含的所有商品
                $group_ids = array_merge($activity_product_arr,$group_ids);
                foreach($products as &$product){
                    if(in_array($product['id'],$activity_product_arr)){ // 拼团商品
                        $groupProduct = $groupProductMod->where('org_id',$product['id'])->where('activity_id',$k)->find();
                        $product['group_product_id'] = $groupProduct['id'];
                        $product['group_person_limit'] = $v['person_limit'];

                        $cheapestSku = GroupProductSkuModel::where('product_id',$groupProduct['id'])->find();
                        if(!empty($cheapestSku)){
                            $product['price'] = $cheapestSku['group_price'];
                            $product['org_price'] = $cheapestSku['org_price'];
                        }else{
                            $this->error('商品有误，缺少sku');
                        }
                        $group_products[$product['id']] = $product;
                    }
                }
            }
        }
//        dump($group_products);
        $all_ordinary_products =  $productModel->order("list_order ASC")->order("id DESC")->where('id','<>',$id)->where(['delete_time' => 0, 'status' => 1])->limit(2)->select();
        $all_ordinary_products_arr = [];
        foreach($all_ordinary_products as  $k =>  &$product){
            if (!empty($group_products) && in_array($product['id'],array_keys($group_products))){
                unset($all_ordinary_products[$k]);
                continue;
            }
            // 普通商品
            $couponModel = new CouponModel();
            $coupon = $couponModel->biggestCoupon()->find();
            $cheapestSku = $product->cheapestSku()->find();
            if(!empty($cheapestSku)){
                $product['price'] = $cheapestSku['price'] - (empty($coupon) ? 0 : $coupon['amount']);
                $product['org_price'] = $cheapestSku['org_price'];
            }else{
                $this->error('商品有误，缺少sku');
            }
            $all_ordinary_products_arr[$product['id']] = $product;
        }
        $all_ordinary_products = json_decode(json_encode($all_ordinary_products_arr),true);
        $group_products = json_decode(json_encode($group_products),true);
        $products = array_merge($all_ordinary_products,$group_products);
        return $products;
    }
}