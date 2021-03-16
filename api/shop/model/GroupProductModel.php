<?php

namespace api\shop\model;

use app\common\enum\SharingStatus;
use think\Model;

class GroupProductModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $type = [
        'list_order'    =>  'integer',
        'type'    =>  'integer',
        'status'    =>  'integer',
    ];

    public function typeText() {
        return [1 => ''];
    }

    public function statusText() {
        return [1 => '上架', 2 => '下架-可购买', 0 => '下架-不可购买'];
    }

    public function processingActivity() {
        $thisTime = time();
        return $this->hasOne('GroupActivityModel', 'product_id', 'org_id')
                ->where('delete_time', 0)
                ->where('status', '<>', 0)
                ->where('start_time', '<', $thisTime)
                ->where('end_time', '>', $thisTime)
                ->order('id DESC');
    }

    public function checkGroupProductProcessing() {
        $activity = $this->processingActivity()->find();
        if(empty($activity) || ($activity['id'] != $this->activity_id)){
            return false;
        }
        return true;
    }

    public function checkoutHuodongjieshu($activity_id, $org_id){
        $groupActivityModel = new GroupActivityModel();
        $thisTime = time();
//        $newGroupActivity = $groupActivityModel->where('product_id', '=', $org_id)
//            ->where('delete_time', 0)
//            ->where('status', 1)
//            ->where('start_time', '<', $thisTime)
//            ->where('end_time', '>', $thisTime)
//            ->order('id DESC')
//            ->find();
//        if(empty($newGroupActivity) || ($newGroupActivity['id'] != $activity_id)){
//            return false;
//        }
        $activity_info = $groupActivityModel->where('id',$activity_id)
                                            ->where('delete_time',0)
                                            ->where('status', 1)
                                            ->order('id DESC')
                                            ->find();
        if (empty($activity_info)){
            return false;
        }
        if ($thisTime > $activity_info['end_time']){
            return false;
        }
//        dump($activity_info);
        $productid_arr = explode(',',$activity_info['product_id']);
//        dump($productid_arr);
        if (!in_array($org_id,$productid_arr)){
            return false;
        }
        return true;
    }

    public function manbipeiText() {
        return [0 => '不支持', 1 => '支持'];
    }

    public function requireInfoTypeText() {
        return [1 => '登录+姓名+身份证'];
    }

    public function groupProperties() {
        return $this->hasMany('GroupExtraValueModel', 'product_id')
        ->where('name', 'manbipei')
        ->whereor('name', 'one_v_one')
        ->limit(2);    
    }

    public function getDetailNameById($goods_id){
        return $this->field('`org_id`,`activity_id`,`name`, `main_cover_src`')->where('id', '=', $goods_id)->find();
    }

    public function detail($goods_id){
        return $this->where('status', '<>', 0)->find($goods_id);
    }

    public function getDetailSrcAttr($value){
        return explode(',', $value);
    }

    public function groupActivity() {
        return $this->belongsTo('GroupActivityModel', 'activity_id')->where('delete_time', 0);
    }

    static function extraValueInputs() {
        return [
            ['title' => '增值服务：慢必赔', 'required' => true, 'name' => 'manbipei', 'src_title' => '慢必赔说明弹窗'],
            ['title' => '增值服务：1人1车', 'required' => true, 'name' => 'one_v_one', 'src_title' => '1人1车说明弹窗'],
        ];
    }

    public function getSkuByGoodIdAndSkuPath($gid, $sku_path){
        $model = new \app\shop\model\ProductSkuModel();
        return $model->getGroupSkuByPath($gid, $sku_path);
    }
    
    public function orgProduct() {
        return $this->belongsTo('ProductModel', 'org_id')->where('delete_time', 0);
    }

    public function getMore($rows, $filter_id){
        return $this->where('id', '<>', $filter_id)->where('status', '<>', 0)->where('delete_time', '=', 0)->paginate($rows, false, ['query' => \request()->request()]);
    }

    public function getSkusByGoodId($gid, $org_id){
        $model = new \api\shop\model\GroupProductSkuModel();
        return $model->skus($gid, $org_id);
    }

    public function getExtra($gid){
        $model = new \api\shop\model\GroupExtraValueModel();
        return $model->where('product_id', '=', $gid)->where('support', '=', 1)->select()->toArray();
    }

    public function groupExtraValues() {
        return $this->hasMany('GroupExtraValueModel', 'product_id')
                    ->where('name', 'manbipei')
                    ->whereor('name', 'one_v_one')
                    ->limit(2); 
    }

    public function cheapestSku() {
        return $this->hasOne('GroupProductSkuModel', 'product_id')
                    ->where('delete_time', 0)
                    ->order("group_price ASC")->order("org_price ASC"); 
    }

    public function properties() {
        return $this->hasMany('ProductPropertyModel', 'org_id')
                    ->where('delete_time', 0); 
    }

    public function sharingActive() {
        return $this->hasMany('SharingActiveModel', 'goods_id')
                    ->where('end_time', '>', time());
    }

    public function sharingActiveSucc(){
        return $this->hasMany('SharingActiveModel', 'goods_id')
                    ->where('status', SharingStatus::SHARINGSUCC);
                    //->where('end_time', '>', time());
    }

}
