<?php

namespace app\shop\model;
use app\shop\model\ProductPropertyModel;
use app\shop\model\GroupProductSkuModel;

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

    public function adminEditProductSku($productId, $datas)
    {
        $thisModel = new ProductSkuModel();
        foreach($datas as $data){
            if(empty($data['id'])){
                $data['product_id'] = $productId;
                $thisModel->allowField(true)->create($data);
            }else{
                $thisModel->allowField(true)->where('id', $data['id'])->update($data);
                // 更新拼团商品对应的sku
                $groupProductSkuModel = new GroupProductSkuModel();
                unset($data['id']);
                unset($data['product_id']);
                $groupProductSkuModel->allowField(true)->where('path', $data['path'])->update($data);
            }
        }

        return true;

    }

    public function getSkuByPath($product_id, $sku_path){
        $productSkuModel = new ProductSkuModel();
        return $productSkuModel->where('product_id', '=', $product_id)->where('delete_time', 0)->where('path', '=', $sku_path)->find();
    }

    public function getGroupSkuByPath($product_id, $sku_path){
        $productSkuModel = new GroupProductSkuModel();
        return $productSkuModel->where('product_id', '=', $product_id)->where('delete_time', 0)->where('path', '=', $sku_path)->find();
    }

    public function skus($id) {
        $skus = [];
        $productPropertyModel = new ProductPropertyModel();
        $productSkuModel = new ProductSkuModel();
        $properties = $productPropertyModel->where('delete_time', 0)->where('product_id', $id)->select();
        $propertiesCount = count($properties);
        foreach($properties as $key => $property){
            $property['values'] = $property->values()->where('delete_time', 0)->order('id', 'asc')->select();
            if($key == 0){
                foreach($property['values'] as $value){
                    $skus[] = ['path' => $value['id'], 'text' => $value['name']];
                    foreach($skus as &$sku){
                        if($key == ($propertiesCount - 1)){
                            $skuInst = $productSkuModel
                                ->where('delete_time', 0)
                                ->where('product_id', $id)
                                ->where('path', $sku['path'])
                                ->find();
                            if(empty($skuInst)){
                                $sku['id'] = null;
                                $sku['price'] = 0;
                                $sku['org_price'] = 0;
                                $sku['goods_id'] = null;
                            }else{
                                $sku['id'] = $skuInst['id'];
                                $sku['price'] = $skuInst['price'];
                                $sku['org_price'] = $skuInst['org_price'];
                                $sku['goods_id'] = $skuInst['goods_id'];
                            }
                        }
                    }
                }
            }else{
                $copies = [];
                $count = count($property['values']) - 1;
                for ($i=1; $i<=$count; $i++)
                {
                    $copies[] = json_decode(json_encode($skus), true);
                }
                foreach($property['values'] as $vkey => $value){
                    if($vkey == 0){
                        foreach($skus as &$sku){
                            $sku['path'] .= (','.$value['id']);
                            $sku['text'] .= (','.$value['name']);
                            if($key == ($propertiesCount - 1)){
                                $skuInst = $productSkuModel
                                    ->where('delete_time', 0)
                                    ->where('product_id', $id)
                                    ->where('path', $sku['path'])
                                    ->find();
                                if(empty($skuInst)){
                                    $sku['id'] = null;
                                    $sku['price'] = 0;
                                    $sku['org_price'] = 0;
                                    $sku['goods_id'] = null;
                                }else{
                                    $sku['id'] = $skuInst['id'];
                                    $sku['price'] = $skuInst['price'];
                                    $sku['org_price'] = $skuInst['org_price'];
                                    $sku['goods_id'] = $skuInst['goods_id'];
                                }
                            }
                        }
                    }else{
                        foreach($copies[$vkey-1] as &$sku){
                            $sku['path'] .= (','.$value['id']);
                            $sku['text'] .= (','.$value['name']);
                            if($key == ($propertiesCount - 1)){
                                $skuInst = $productSkuModel
                                    ->where('delete_time', 0)
                                    ->where('product_id', $id)
                                    ->where('path', $sku['path'])
                                    ->find();
                                if(empty($skuInst)){
                                    $sku['id'] = null;
                                    $sku['price'] = 0;
                                    $sku['org_price'] = 0;
                                    $sku['goods_id'] = null;
                                }else{
                                    $sku['id'] = $skuInst['id'];
                                    $sku['price'] = $skuInst['price'];
                                    $sku['org_price'] = $skuInst['org_price'];
                                    $sku['goods_id'] = $skuInst['goods_id'];
                                }
                            }
                        }
                    }
                }
                for ($i=1; $i<=$count; $i++)
                {
                    $skus = array_merge($skus, $copies[$i-1]);
                }
            }
        }
        return $skus;
    }
}
