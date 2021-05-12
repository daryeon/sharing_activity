<?php

namespace app\shop\model;

use think\Model;

class PropertyValueModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $type = [
        'property_id'    =>  'integer',
    ];

    public function productProperty() {
        return $this->belongsTo('ProductPropertyModel', 'property_id'); 
    }

    public function adminAddPropertyValue($data)
    {
        $this->allowField(true)->data($data, true)->isUpdate(false)->save();

        return $this;

    }

    public function adminEditPropertyValue($data)
    {
        $this->allowField(true)->data($data, true)->isUpdate(true)->save();

        return $this;

    }
}
