<?php

namespace app\shop\model;
use app\shop\service\OssService;

use think\Model;

class VideoModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $type = [
        'category_id'    =>  'integer',
        'status'    =>  'integer',
    ];

    public function statusText() {
        return [0 => '隐藏', 1 => '显示'];
    }

    public function category() {
        return $this->belongsTo('VideoCategoryModel', 'category_id'); 
    }

    public function adminAddVideo($data)
    {
        $ossClient = OssService::getInstance();
        $data['cover_src']         = $ossClient->upload($data['cover_src']);
        $data['src']         = $ossClient->upload($data['src']);
        $data['duration'] = $data['duration_min'].':'.$data['duration_sec'];
        $this->allowField(true)->data($data, true)->isUpdate(false)->save();

        return $this;

    }

    public function adminEditVideo($data)
    {
        $ossClient = OssService::getInstance();
        $data['cover_src']         = $ossClient->upload($data['cover_src']);
        $data['src']         = $ossClient->upload($data['src']);
        $data['duration'] = $data['duration_min'].':'.$data['duration_sec'];
        $this->allowField(true)->data($data, true)->isUpdate(true)->save();

        return $this;

    }
}
