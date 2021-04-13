<?php

namespace app\shop\model;
use app\shop\model\ExtraValueModel;
use app\shop\model\GroupExtraValueModel;
use app\shop\service\OssService;

use think\Model;

class ProductModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $type = [
        'list_order'    =>  'integer',
        'type'    =>  'integer',
        'price'    =>  'integer',
        'manbipei'    =>  'integer',
        'require_info_type'    =>  'integer',
        'status'    =>  'integer',
    ];

    public function typeText() {
        return [1 => '学车'];
    }

    
    public function statusText() {
        return [1 => '上架', 2 => '下架-可购买', 0 => '下架-不可购买'];
    }

    public function manbipeiText() {
        return [0 => '不支持', 1 => '支持'];
    }

    public function requireInfoTypeText() {
        return [1 => '登录+姓名+身份证'];
    }

    public function cheapestSku() {
        return $this->hasOne('ProductSkuModel', 'product_id')
                    ->where('delete_time', 0)
                    ->order("price ASC")->order("org_price ASC"); 
    }

    public function skus() {
        return $this->hasMany('ProductSkuModel', 'product_id')
                    ->where('delete_time', 0);
    }

    public function groupProduct() {
        return $this->hasOne('GroupProductModel', 'org_id')
                    ->where('delete_time', 0);
    }

    public function groupProducts() {
        return $this->hasMany('GroupProductModel', 'org_id')
                    ->where('delete_time', 0);
    }

    static function extraValueInputs() {
        return [
            ['title' => '增值服务：慢必赔', 'required' => true, 'name' => 'manbipei', 'src_title' => '慢必赔说明弹窗'],
            ['title' => '增值服务：1人1车', 'required' => true, 'name' => 'one_v_one', 'src_title' => '1人1车说明弹窗'],
        ];
    }

    public function extraValues() {
        return $this->hasMany('ExtraValueModel', 'product_id')
                    ->limit(2);
    }

    public function extraValueByName($name) {
        return $this->hasMany('ExtraValueModel', 'product_id')
                    ->where('name', $name);
    }

    public function properties() {
        return $this->hasMany('ProductPropertyModel', 'product_id')
                    ->where('delete_time', 0);
    }

    public function defaultListOrder() {
        $index = 0;
        $products = $this->distinct(true)->field('list_order')->where('delete_time', 0)->order('list_order ASC')->limit(50)->select();
        while(count($products) == 50){
            $index = ($index + 49);
            $products = $this->distinct(true)->field('list_order')->where('delete_time', 0)->order('list_order ASC')->limit(50)->select();
        }
        for ($i=0; $i<=49; $i++){
            $_index = ($index + $i);
            if(empty($products[$_index]) || ($products[$_index]['list_order'] != $_index)){
                return $_index;
            }
        }

        return 10000;
    }

    public function adminAddProduct($data)
    {
        $ossClient = OssService::getInstance();
        $str = $data['description'];
        $parrern = '/src="\/upload\/[^\s>]*/i';  //i忽略大小写 括号中内容放到内存中
        preg_match($parrern, $str, $match);
        while(!empty($match)){
            $relPath = substr($match[0], 13, strlen($match[0]) - 14);
            $src = $ossClient->upload($relPath);
            $str = preg_replace($parrern,'src="'.$src.'"', $str, 1);
            preg_match($parrern, $str, $match);
        }
        $data['description'] = $str;
        $data['cover_src'] = $ossClient->upload($data['cover_src']);
        $data['main_cover_src'] = $ossClient->upload($data['main_cover_src']);
//        $data['video_src'] = $ossClient->upload($data['video_src']);
        $thisModel = new ProductModel();
        $extraValueModel = new ExtraValueModel();
        $inst = $thisModel->create($data);
        $data['manbipei']['product_id'] = $inst->id;
        $data['manbipei']['name'] = 'manbipei';
        $data['manbipei']['src'] = $ossClient->upload($data['manbipei']['src']);
        $extraValueModel->adminAddExtraValue($data['manbipei']);
        $data['one_v_one']['product_id'] = $inst->id;
        $data['one_v_one']['name'] = 'one_v_one';
        $data['one_v_one']['src'] = $ossClient->upload($data['one_v_one']['src']);
        $extraValueModel->adminAddExtraValue($data['one_v_one']);
        return $inst;

    }

    public function adminEditProduct($data)
    {
        $ossClient = OssService::getInstance();
        $str = $data['description'];
        $parrern = '/src="\/upload\/[^\s>]*/i';  //i忽略大小写 括号中内容放到内存中
        preg_match($parrern, $str, $match);
        while(!empty($match)){
            $relPath = substr($match[0], 13, strlen($match[0]) - 14);
            $src = $ossClient->upload($relPath);
            $str = preg_replace($parrern,'src="'.$src.'"', $str, 1);
            preg_match($parrern, $str, $match);
        }
        $data['description'] = $str;
        $data['cover_src'] = $ossClient->upload($data['cover_src']);
        $data['main_cover_src'] = $ossClient->upload($data['main_cover_src']);
//        $data['video_src'] = $ossClient->upload($data['video_src']);
        $extraValueModel = new ExtraValueModel();
        $this->allowField(true)->data($data, true)->isUpdate(true)->save();
        // 更新拼团商品
        $productModel = new ProductModel();
        $product = $productModel->get($data['id']);
        $groupProducts = $product->groupProducts()->select();
        if(!empty($groupProducts)){
            foreach($groupProducts as $groupProduct) {
                $_data = $data;
                $_data['id'] = $groupProduct['id'];
                $groupProduct->adminUpdate($_data);
            }
        }
        // 增值服务
        $data['manbipei']['src'] = $ossClient->upload($data['manbipei']['src']);
        $data['manbipei']['product_id'] = $data['id'];
        $data['manbipei']['name'] = 'manbipei';
        if(!empty($data['manbipei']['id'])){
            $extraValueModel->adminEditExtraValue($data['manbipei']);
        }else{
            $extraValueModel->adminAddExtraValue($data['manbipei']);
        }
        
        $data['one_v_one']['product_id'] = $data['id'];
        $data['one_v_one']['name'] = 'one_v_one';
        $data['one_v_one']['src'] = $ossClient->upload($data['one_v_one']['src']);
        if(!empty($data['one_v_one']['id'])){
            $extraValueModel->adminEditExtraValue($data['one_v_one']);
        }else{
            $extraValueModel->adminAddExtraValue($data['one_v_one']);
        }
        // 更新拼团商品增值服务
        $groupExtraValueModel = new GroupExtraValueModel();
        if(!empty($groupProducts)){
            foreach($groupProducts as $groupProduct){
                $groupExtraValueManbipei = $groupProduct->groupExtraValuesManbipei()->find();
                if(!empty($groupExtraValueManbipei)){
                    $manbipei = $data['manbipei'];
                    $manbipei['product_id'] = $groupProduct['id'];
                    $manbipei['id'] = $groupExtraValueManbipei['id'];
                    $groupExtraValueModel->adminUpdate($manbipei);
                }else{
                    $manbipei = $data['manbipei'];
                    $manbipei['product_id'] = $groupProduct['id'];
                    unset($manbipei['id']);
                    $groupExtraValueModel->adminAdd($manbipei);
                }
    
                $groupExtraValueOneVone = $groupProduct->groupExtraValuesOneVone()->find();
                if(!empty($groupExtraValueOneVone)){
                    $oneVone = $data['one_v_one'];
                    $oneVone['product_id'] = $groupProduct['id'];
                    $oneVone['id'] = $groupExtraValueOneVone['id'];
                    $groupExtraValueModel->adminUpdate($oneVone);
                }else{
                    $oneVone = $data['one_v_one'];
                    $oneVone['product_id'] = $groupProduct['id'];
                    unset($oneVone['id']);
                    $groupExtraValueModel->adminAdd($oneVone);
                }
            }
        }
        return $this;
    }
}
