<?php

namespace api\shop\model;

use think\Model;

class ProductPropertyModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $type = [
        'product_id'    =>  'integer',
    ];

    public function groupProduct() {
        return $this->belongsTo('GroupProductModel', 'product_id'); 
    }

    public function product() {
        return $this->belongsTo('ProductModel', 'product_id'); 
    }

    public function values() {
        return $this->hasMany('PropertyValueModel', 'property_id'); 
    }
}
