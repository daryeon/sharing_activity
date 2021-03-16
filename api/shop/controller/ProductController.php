<?php
namespace api\shop\controller;

use api\shop\model\GroupProductModel;
use api\shop\model\GroupProductSkuModel;
use app\shop\model\GroupActivityModel;
use cmf\controller\RestBaseController;
use api\shop\model\ProductModel;
use api\shop\model\CouponModel;
use think\App;
use think\facade\Log;

class ProductController extends RestBaseController
{
    public function __construct(App $app = null)
    {
        parent::__construct($app);
        Log::init([
            'type'  =>  'File',
            'path'  =>  CMF_DATA.'/runtime/log/api'
        ]);
    }

    public function index()
    {
        $goods_org_id =  $this->request->param('org_id', 0, 'intval');
        $limit =  $this->request->param('limit', 1000, 'intval');

        $groupProductMod = new GroupProductModel();
        $productModel = new ProductModel();
        $products = $productModel->order("list_order ASC")
                                 ->order("id DESC")
                                 ->where(['delete_time' => 0, 'status' => 1])
                                 ->where('id','<>',$goods_org_id)
                                 ->select();
        //拼团活动
        $activityMod = new GroupActivityModel();
        $this_time = time();
        $product_ids = $activityMod->where('status','=',1)
            ->where('start_time','<=',$this_time)
            ->where('end_time','>=',$this_time)
            ->where('delete_time','=',0)
            ->field('id,product_id,person_limit')->select();
        $group_ids = [];
        $group_products = [];
        $couponModel = new CouponModel();

        if(!empty($product_ids)){
            foreach ($product_ids as $k=>$v){
                $activity_product_arr = explode(',',$v['product_id']);        //一个活动里面包含的所有商品
                $group_ids = array_merge($activity_product_arr,$group_ids);
                foreach($products as &$product){
                    if(in_array($product['id'],$activity_product_arr)){ // 拼团商品
                        $groupProduct = $groupProductMod->where('org_id',$product['id'])->where('activity_id',$v['id'])->find();
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
        $all_ordinary_products =  $productModel->order("list_order ASC")
            ->order("id DESC")
            ->where(['delete_time' => 0, 'status' => 1])
            ->where('id','<>',$goods_org_id)
            ->select();
        $all_ordinary_products_arr = [];
        foreach($all_ordinary_products as  $k =>  &$product){
            if (!empty($group_products) && in_array($product['id'],array_keys($group_products))){
                unset($all_ordinary_products[$k]);
                continue;
            }
            // 普通商品
            $couponModel = new CouponModel();
            $cheapestSku = $product->cheapestSku()->find();
            $amount = $couponModel->cheapestCounpon($product['id']);
            if(!empty($cheapestSku)){
                $product['price'] = $cheapestSku['price'] - $amount;
                $product['org_price'] = $cheapestSku['org_price'];
                $product['amount'] = $amount;
            }else{
                $this->error('商品有误，缺少sku');
            }
            $all_ordinary_products_arr[$product['id']] = $product;
        }
        $all_ordinary_products = json_decode(json_encode($all_ordinary_products_arr),true);
        $group_products = json_decode(json_encode($group_products),true);
        $products = array_merge($all_ordinary_products,$group_products);
        $list_order = array_column($products,'list_order');
        array_multisort($list_order,SORT_ASC,$products);

        $this->success('ok', array_slice(array_values($products),0,$limit));
    }

    public function detail(){
        $id = $this->request->get('id', 0, 'intval');
        if(!$id){
            $this->error('商品不存在~');
        }

        $this->success('ok', ['id' => $id, 'detail' => [], 'sku' => []]);
    }
}
