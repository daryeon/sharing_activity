<?php

namespace app\shop\model;
use app\shop\service\OssService;

use think\Model;

class VideoCategoryModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $type = [
        'status'    =>  'integer',
    ];

    public function videos() 
    {
        return $this->hasMany('VideoModel', 'category_id'); 
    }

    public function adminAddVideoCategory($data)
    {
        $this->allowField(true)->data($data, true)->isUpdate(false)->save();

        return $this;

    }

    public function adminEditVideoCategory($data)
    {
        $this->allowField(true)->data($data, true)->isUpdate(true)->save();

        return $this;

    }
}
