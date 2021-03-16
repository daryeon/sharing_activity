<?php

namespace api\shop\model;
use api\shop\service\OssService;

use think\Model;

class ProductModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $type = [
        'list_order'    =>  'integer',
        'type'    =>  'integer',
        'price'    =>  'integer',
        'manbipei'    =>  'integer',
        'require_info_type'    =>  'integer',
        'status'    =>  'integer',
    ];

    public function typeText() {
        return [1 => '学车'];
    }

    public function statusText() {
        return [1 => '上架', 2 => '下架-可购买', 0 => '下架-不可购买'];
    }

    public function manbipeiText() {
        return [0 => '不支持', 1 => '支持'];
    }

    public function requireInfoTypeText() {
        return [1 => '登录+姓名+身份证'];
    }

    public function groupProduct() {
        return $this->hasOne('GroupProductModel', 'org_id')->where('delete_time', 0);
    }

    public function processingActivity() {
        $thisTime = time();
        return $this->hasOne('GroupActivityModel', 'product_id')
                ->where('delete_time', 0)
                ->where('status', 1)
                ->where('start_time', '<', $thisTime)
                ->where('end_time', '>', $thisTime)
                ->order('id DESC');
    }

    public function cheapestSku() {
        return $this->hasOne('ProductSkuModel', 'product_id')
                    ->where('delete_time', 0)
                    ->order("price ASC")->order("org_price ASC"); 
    }
}
