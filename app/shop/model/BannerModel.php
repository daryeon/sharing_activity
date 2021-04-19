<?php

namespace app\shop\model;
use app\shop\service\OssService;

use think\Model;

class BannerModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $type = [
        'status'    =>  'integer',
        'jump_type'    =>  'integer',
        'list_order'      =>  'float',
    ];

    public function adminAddBanner($data)
    {
        $ossClient = OssService::getInstance();
        $data['src']         = $ossClient->upload($data['src']);
        $this->allowField(true)->data($data, true)->isUpdate(false)->save();

        return $this;

    }

    public function adminEditBanner($data)
    {
        $ossClient = OssService::getInstance();
        $data['src']         = $ossClient->upload($data['src']);
        $this->allowField(true)->data($data, true)->isUpdate(true)->save();

        return $this;

    }

    public function statusText() {
        return [0 => '隐藏', 1 => '显示'];
    }

    public function jumpTypeText() {
        return [0 => '不跳转', 1 => '跳转指定链接'];
    }
}
