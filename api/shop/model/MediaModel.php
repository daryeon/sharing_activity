<?php

namespace api\shop\model;

use think\Model;

class ShopMediaModel extends Model
{
    protected $autoWriteTimestamp = true;

    /**
     * 关联媒体表
     * @return \think\model\relation\belongsTo
     */
    public function medias()
    {
        return $this->belongsTo('ShopBlockModel', 'block_id');
    }
}
