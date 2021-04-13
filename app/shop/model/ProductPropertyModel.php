<?php

namespace app\shop\model;
use app\shop\model\PropertyValueModel;
use app\shop\model\ProductSkuModel;
use app\shop\service\OssService;

use think\Model;

class ProductPropertyModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $type = [
        'product_id'    =>  'integer',
    ];

    public function product() {
        return $this->belongsTo('ProductModel', 'product_id'); 
    }

    public function values() {
        return $this->hasMany('PropertyValueModel', 'property_id'); 
    }

    public function adminAddProductProperty($id, $datas)
    {
        $shouldDeleteSkus = false;
        foreach($datas as $data) {
            $_data = [];
            $ossClient = OssService::getInstance();
            $_data['meaning_src']         = $ossClient->upload($data['meaning_src']);
            $_data['product_id'] = $id;
            $_data['name'] = $data['property'];
            $propertyModel = new ProductPropertyModel();
            $propertyInst = empty($data['id']) ? $propertyModel->create($_data) : $propertyModel->where('id', $data['id'])->update($_data);
            if(empty($data['id'])){
                $shouldDeleteSkus = true;
            }
            foreach($data['property_value'] as $value) {
                $propertyValueModel = new PropertyValueModel();
                if(!empty($value['id']) && !empty($value['del'])){
                    $propertyValueModel->where('id', $value['id'])->update(['delete_time' => time()]);
                }else{
                    $valueBody = [];
                    $valueBody['property_id'] = empty($data['id']) ? $propertyInst->id : $data['id'];
                    $valueBody['name'] = $value['name'];
                    empty($value['id']) ? $propertyValueModel->create($valueBody) : $propertyValueModel->where('id', $value['id'])->update($valueBody);
                    if(empty($value['id'])){
                        $shouldDeleteSkus = true;
                    }
                }
            }
        }
        if($shouldDeleteSkus){
            $productSkuModel = new ProductSkuModel();
            $skus = $productSkuModel->where('product_id', $id)->select();
            if(!empty($skus)){
                foreach($skus as $sku){
                    $sku['delete_time'] = time();
                    $sku->save();
                }
            }
        }

        return true;

    }
}
