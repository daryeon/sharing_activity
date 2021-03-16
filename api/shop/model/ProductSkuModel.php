<?php

namespace api\shop\model;

use think\Model;

class ProductSkuModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $type = [
        'property_value_id'    =>  'integer',
        // 'goods_id'    =>  'integer',
    ];

    public function product() {
        return $this->belongsTo('ProductModel', 'product_id'); 
    }

    public function getSkuByPath($product_id, $sku_path){
        $productSkuModel = new ProductSkuModel();
        return $productSkuModel->where('product_id', '=', $product_id)->where('delete_time', 0)->where('path', '=', $sku_path)->find();
    }

    public function getGroupSkuByPath($product_id, $sku_path){
        $productSkuModel = new GroupProductSkuModel();
        return $productSkuModel->where('product_id', '=', $product_id)->where('delete_time', 0)->where('path', '=', $sku_path)->find();
    }

}
