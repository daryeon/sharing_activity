<?php

namespace app\shop\model;

use think\Model;

class VideoBlockModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $type = [
        'status'    =>  'integer',
        'category_id'    =>  'integer',
        'display_type'    =>  'integer',
        'list_order'    =>  'integer',
    ];

    public function displayTypes() {
        return [
            1 => 'A',
            2 => 'B',
            // 3 => 'C',
            // 4 => 'D'
        ];
    }

    public function contentTypes() {
        return [
            1 => '视频集',
            2 => '文章集',
            // 3 => 'C',
            // 4 => 'D'
        ];
    }

    public function statusText() {
        return [0 => '隐藏', 1 => '显示'];
    }

    public function category() {
        return $this->belongsTo('VideoCategoryModel', 'category_id'); 
    }

    public function adminAddVideoBlock($data)
    {
        $this->allowField(true)->data($data, true)->isUpdate(false)->save();

        return $this;

    }

    public function adminEditVideoBlock($data)
    {
        $this->allowField(true)->data($data, true)->isUpdate(true)->save();

        return $this;

    }
}
