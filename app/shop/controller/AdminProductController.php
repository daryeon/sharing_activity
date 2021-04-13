<?php
namespace app\shop\controller;

use app\http\middleware\UserLogin;
use app\shop\model\ProductLogModel;
use cmf\controller\AdminBaseController;
use app\shop\model\ProductModel;
use app\shop\model\ProductPropertyModel;
use app\shop\model\CouponModel;
use app\shop\model\ProductSkuModel;
use app\shop\service\OssService;

class AdminProductController extends AdminBaseController
{
    protected $middleware = ['Userlogin'];
    public function index()
    {
        $keyword = $this->request->param('keyword', '');
        $productModel = new ProductModel();
        $model = $productModel->order("list_order ASC")->order("id DESC")->where('delete_time', 0);
        if(!empty($keyword)){
            $model->where('name', 'like', '%'.$keyword.'%');
        }
        $data = $model->paginate(20);
        foreach($data as &$product){
            $couponModel = new CouponModel();
            $coupon = $couponModel->biggestCoupon()->find();
            $cheapestSku = $product->cheapestSku()->find();
            $product['price'] = $cheapestSku['price'];
            $product['coupon_value'] = empty($coupon) ? 0 : $coupon['amount'];
        }
        $this->assign('keyword', $keyword);
        $this->assign('products', $data);
        $this->assign('status_text', $productModel->statusText());
        $this->assign('type_text', $productModel->typeText());
        $this->assign('page', $data->render());
        return $this->fetch();
    }

    public function property()
    {
        $id = $this->request->param('id', 0, 'intval');
        $productPropertyModel = new ProductPropertyModel();
        $properties = $productPropertyModel->where('delete_time', 0)->where('product_id', $id)->select();
        foreach($properties as $property){
            $property['values'] = $property->values()->where('delete_time', 0)->order('id', 'asc')->select();
        }
        $this->assign('properties_count', count($properties));
        $this->assign('id', $id);
        $this->assign('properties', $properties);

        //设置表单csrf_token 有bug 隐藏
         set_csrf_token($properties);

        return $this->fetch();
    }

    public function sku()
    {
        $id = $this->request->param('id', 0, 'intval');
        $type = $this->request->param('type', '');
        $productSkuModel = new ProductSkuModel();
        $skus = $productSkuModel->skus($id);

        if($type == 'json'){
            return $this->success('ok', null, $skus);
        }
        $this->assign('id', $id);
        $this->assign('skus', $skus);

        //设置表单csrf_token
         set_csrf_token($skus);

        return $this->fetch();
    }

    public function editPostProperty() {
        if ($this->request->isPost()) {
            $data = $this->request->param();
            $post = empty($data['post']) ? [] : $data['post'];
            $id = $data['id'];
            if(empty($post)){
                $this->error('请输入数据再提交！');
            };
            if(empty($id)){
                $this->error('缺少商品id，请返回重试！');
            }
            foreach($post as $property) {
                $result = $this->validate($property, 'AdminProductProperty.add');
                if ($result !== true) {
                    $this->error($result);
                }
            }
            $productPropertyModel = new ProductPropertyModel();
            $productPropertyModel->adminAddProductProperty($id, $data['post']);
            //TODO 设置日志
            $this->success('保存成功!', url('AdminProduct/property', ['id' => $id]));
        }
    }

    public function editPostSku() {
        if ($this->request->isPost()) {
            $data = $this->request->param();
            $post = $data['post'];
            $id = $data['id'];
            if(empty($id)){
                $this->error('缺少商品id，请返回重试！');
            }
            foreach($post as $sku) {
                $result = $this->validate($sku, 'AdminProductSku.add');
                if ($result !== true) {
                    $this->error($result);
                }
            }
            $productSkuModel = new ProductSkuModel();
            $productSkuModel->adminEditProductSku($id, $data['post']);

            //TODO 设置日志,商品id,商品名
            $this->success('保存成功!', url('AdminProduct/sku', ['id' => $id]));
        }
    }

    public function add()
    {
        $productModel = new ProductModel();
        $listOrder = $productModel->defaultListOrder();
        $this->assign('extra_value_inputs', $productModel::extraValueInputs());
        $this->assign('statuses', $productModel->statusText());
        $this->assign('list_order', $listOrder);
        $this->assign('types', $productModel->typeText());
        $this->assign('manbipeis', $productModel->manbipeiText());
        $this->assign('require_info_types', $productModel->requireInfoTypeText());
        $this->assign('post', ['src' => '']);
        return $this->fetch();
    }

    public function edit()
    {

        $id = $this->request->param('id', 0, 'intval');

        $productModel = new ProductModel();
        $post            = $productModel->where('id', $id)->find();
        $photos = [];
        if(!empty($post['detail_src'])){
            $photos = explode(',', $post['detail_src']);
        }
        $extraValueInputs = $productModel::extraValueInputs();
        foreach($extraValueInputs as &$evi) {
            $evi['id'] = '';
            $evi['support'] = 0;
            $evi['amount'] = 0;
            $evi['src'] = '';
            $extraValue = $post->extraValueByName($evi['name'])->find();
            if(!empty($extraValue)){
                $evi['id'] = $extraValue['id'];
                $evi['support'] = $extraValue['support'];
                $evi['amount'] = $extraValue['amount'];
                $evi['src'] = $extraValue['src'];
            };
        }
        $post['photos'] = $photos;

        $this->assign('id', $id);
        $this->assign('statuses', $productModel->statusText());
        $this->assign('types', $productModel->typeText());
        $this->assign('manbipeis', $productModel->manbipeiText());
        $this->assign('extra_value_inputs', $extraValueInputs);
        $this->assign('post', $post);
        //设置表单csrf_token
         set_csrf_token($post);
        return $this->fetch();
    }

    public function addPost()
    {
        if ($this->request->isPost()) {
            $data = $this->request->param();
            if($data['post']['status'] > 0){
                $this->error('商品未有sku，不能为可购买状态');
            }

            $ossClient = OssService::getInstance();
            if (!empty($data['photo_names']) && !empty($data['photo_urls'])) {
                $data['post']['details'] = [];
                foreach ($data['photo_urls'] as $key => $url) {
                    if($key < 5){
                        $url = $ossClient->upload($url);
                        array_push($data['post']['details'], $url);
                    }
                }
            }
            $data['post']['detail_src'] = implode(',', empty($data['post']['details']) ? [] : $data['post']['details']);

            $post = $data['post'];

            $result = $this->validate($post, 'AdminProduct.add');
            if ($result !== true) {
                $this->error($result);
            }

            $productModel = new ProductModel();

            $inst = $productModel->adminAddProduct($data['post']);
            $id =$inst->id;
            //TODO 直接设置新增商品id,商品名到日志

            $this->success('保存成功!','' , compact('id'));

        }
    }

    public function editPost()
    {

        if ($this->request->isPost()) {
            $productModel = new ProductModel();
            $data = $this->request->param();
            if($data['post']['status'] > 0){
                $product = $productModel->get($data['post']['id']);
                if(empty($product->properties()->find()) || empty($product->skus()->find())){
                    $this->error('未设置商品规格和sku，不可设置商品可购买');
                };
            }

           $ossClient = OssService::getInstance();
            if (!empty($data['photo_urls'])) {
                $data['post']['details'] = [];
                foreach ($data['photo_urls'] as $key => $url) {
                    if($key < 5){
                       $url = $ossClient->upload($url);
                        array_push($data['post']['details'], $url);
                    }
                }
            }
            $data['post']['detail_src'] = implode(',', empty($data['post']['details']) ? [] : $data['post']['details']);

            $post = $data['post'];

            $result = $this->validate($post, 'AdminProduct.edit');
            if ($result !== true) {
                $this->error($result);
            }


            $productModel->adminEditProduct($data['post']);

            $this->success('保存成功!');
        }
    }

    public function delete()
    {
        $param           = $this->request->param();
        $productModel = new ProductModel();

        if (isset($param['id'])) {
            $id           = $this->request->param('id', 0, 'intval');
            $productModel
                ->where('id', $id)
                ->update(['delete_time' => time()]);
            //TODO 设置日志

            $this->success("删除成功！", '');
        }
    }

    public function dealLog(){
        $clumd = [
            'list_order'=>"排序",
            'status'=>"状态",
            'name'=>"名称",
            'intro'=>"商品介绍",
            'type'=>"学车类型",
            'video_src'=>"详情页视频",
            'cover_src'=>"视频封面",
            'main_cover_src'=>"主图",
            'photos'=>"商品图",
            'description'=>"图文详情",
            'manbipei'=>"慢必赔",
            'one_v_one'=>"一人一车",
            'property'=>"规格",
            'path_text'=>"规格sku",
            'price'=>"售价",
            'org_price'=>"原价",
            'tx_video_id'=>"腾讯视频id",
        ];
        $id = $this->request->param('id');
        $update_time = $this->request->param('update_time');
        $mod = new ProductLogModel();
        $mod = $mod
                ->where('main_id', $id);
        if (!empty($update_time)){
            $mod = $mod->where('update_time',$update_time);
        }
        $data = $mod->order('id','desc')
                ->limit(1)
                ->find();
        $history = (new ProductLogModel())
            ->where('main_id', $id)
            ->order('update_time','desc')
            ->column('update_time');
        $old = json_decode($data['old'],true);
        $diff = json_decode($data['diff'],true);
        $str = '';
        if($data['path'] == 'shop/admin_product/editpostproperty'){
            foreach ($diff as $k=>$v){
                $str .= $v['property']."   ||   ";
                $temp['property'][] = $v['property'];
                foreach ($v['property_value'] as $vul){
                    $temp['property_value'][$v['property']][] = $vul['name'];
                }
            }
            $str.="\r\n";
            foreach ($temp['property_value'] as $value){
                $str .= implode(',',$value)."   ||   ";
            }

        }elseif ($data['path'] == 'shop/admin_product/editpostsku'){
            $str = "规格sku || 售价  || 原价  || 关联id \r\n";
            foreach ($diff as $k=>$v){
                $str .= $v['path_text']."      ".$v['price'].'   '.$v['org_price'].'   '.$v['goods_id']."\r\n";
            }
            $temp['sku'] = $str;
        }else{
            $str = '';
            foreach ($diff as $k=>$v){
                if (!isset($old[$k]) || $v!= $old[$k]){
                    $str.= $clumd[$k]."     ||     ";
                    $temp[$k] = is_array($v)? json_encode($v):$v;
                }
            }
            $_str = implode('     ||     ',$temp);
            $str .= "\r\n".$_str;
        }

        $this->assign('data', $data);
        $this->assign('history', $history);
        $this->assign('main_id', $id);
        $this->assign('str', $str);
        return $this->fetch();
    }

    private function get_diff_array_by_filter($arr1,$arr2)
    {
        try {
            return array_filter($arr1, function ($v) use ($arr2) {
                return !in_array($v, $arr2);
            });
        } catch (\Exception $exception) {
            return $arr1;
        }
    }
}