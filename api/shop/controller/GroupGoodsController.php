<?php
namespace api\shop\controller;

use api\shop\model\GroupProductModel;
use api\shop\model\ProductModel;
use api\shop\model\GroupProductSkuModel;
use api\shop\model\GroupActivityModel;
use api\shop\model\CouponModel;
use api\shop\controller\RestBaseController;
use api\shop\model\SharingActiveModel;
use app\common\enum\SharingStatus;
use api\shop\model\SharingActiveUsersModel;
use api\shop\model\WxUserModel;

class GroupGoodsController extends RestBaseController
{   
    public function detail() {
        // 商品详情
        $groupProductModel = new GroupProductModel();
        $groupProductSkuModel = new GroupProductSkuModel();
        $goodsId = $this->request->param('goods_id', 0, 'intval');
        $detail = $groupProductModel->get($goodsId);
        
        if (!$detail || !empty($detail['delete_time']) || $detail['status'] != 1) {
            $this->error('很抱歉，商品信息不存在或已下架');
        }
        // -- 检查拼团商品是否是在执行活动商品
        $activityProcessing = $detail->checkGroupProductProcessing();
        if(!$activityProcessing){
            $this->error('很抱歉，活动已结束');
        }
        if(!empty($detail['video_src']) || !empty($detail['detail_src'])){
            // video_src // detail_src
            $arr = [];
            $video_src = is_array($detail['video_src']) ? $detail['video_src'] : [$detail['video_src']];
            foreach($video_src as $video){
                if(!empty($video) && !empty($detail['cover_src'])){
                    $arr[] = ['type' => 'video', 'url' => $video, 'cover' => $detail['cover_src']];
                }
            }

            $tx_video_ids = is_array($detail['tx_video_id']) ? $detail['tx_video_id'] : [$detail['tx_video_id']];
            foreach($tx_video_ids as $video){
                if(!empty($video) && !empty($detail['cover_src'])){
                    $arr[] = ['type' => 'video', 'tx_video_id' => $video, 'cover' => $detail['cover_src']];
                }
            }

            $arr[] = ['type' => 'image', 'url' => $detail['main_cover_src']];
            $image_src = is_array($detail['detail_src']) ? $detail['detail_src'] : [$detail['detail_src']];
            foreach($image_src as $image){
                if(!empty($image)){
                    $arr[] = ['type' => 'image', 'url' => $image];
                }
            }
            $detail['somebox'] = $arr;
        }

        // 获取规格
        $properties = $groupProductModel->properties()->select();
        foreach($properties as &$property) {
            $property['values'] = $property->values()->select();
        }

        // 获取sku路径
        $skus = $groupProductSkuModel->skus($detail['id'], $detail['org_id']);
        
        // 获取产品扩展值
        $extra = $detail->groupExtraValues()->select();
        
        // 更多推荐
        $more = $groupProductModel->getMore(2, $detail['id']);
        foreach($more as &$product){
            $activity = $product->processingActivity()->find();
            $groupProduct = !empty($activity) ? $activity->groupProduct()->find() : null;
            if(!empty($groupProduct) && !empty($activity)){ // 拼团商品
                $product['group_product_id'] = $groupProduct['id'];
                $product['group_person_limit'] = $activity['person_limit'];
                $cheapestSku = $groupProduct->cheapestSku()->find();
                
                if(!empty($cheapestSku)){
                    $product['price'] = $cheapestSku['group_price'];
                    $product['org_price'] = $cheapestSku['org_price'];
                }else{
                    $this->error('商品有误，缺少sku');
                }
            }else{ // 普通商品
                $couponModel = new CouponModel();
                $coupon = $couponModel->biggestCoupon()->find();
                $cheapestSku = $product->cheapestSku()->find();
                if(!empty($cheapestSku)){
                    $product['price'] = $cheapestSku['price'] - (empty($coupon) ? 0 : $coupon['amount']);
                    $product['org_price'] = $cheapestSku['org_price'];
                }else{
                    $this->error('商品有误，缺少sku');
                }
            }
        }
        
        
        $detail = $detail->toArray() + $this->getPrice($skus);
        $this->success('ok', compact('detail', 'properties', 'extra', 'skus', 'more'));
    }

    private function getPrice($skus){
        $ret = ['min_price' => 0, 'max_price' => 0, 'org_min_price' => 0, 'org_max_price' => 0];
        if(empty($skus)){
            return $ret;
        }
        $ret['org_min_price'] = min(array_column($skus, 'org_price'));
        //$ret['org_max_price'] = max(array_column($skus, 'org_price'));
        $ret['min_price'] = min(array_column($skus, 'price'));
        //$ret['max_price'] = max(array_column($skus, 'price'));
        return $ret;
    }

    public function list() {
        $activity_id = $this->request->param('activity_id', 0, 'intval');

        $groupActivityModel = new GroupActivityModel();
        $groupProductModel = new GroupProductModel();
        $thisTime = time();
        $activity = $groupActivityModel
                    ->where('id',$activity_id)
                    ->where('status', 1)
                    ->where('delete_time', 0)
                    ->where('start_time', '<', $thisTime)
                    ->where('end_time', '>', $thisTime)
                    ->find();
        $product_list = [];
        $product_ids = explode(',',$activity['product_id']);
        foreach ($product_ids as $v){
            $groupProduct = $groupProductModel->where('org_id',$v)->where('activity_id',$activity['id'])->find();
            if(!empty($groupProduct)){
                $org_product = $groupProduct->orgProduct()->where('status', '<>', 0)->find();
                if(!empty($org_product) ){
                    $cheapestSku = $groupProduct->cheapestSku()->find();
                    $product_list[$v]['product_id'] = $groupProduct->id;
                    $product_list[$v]['org_id']  = $groupProduct->org_id;
                    $product_list[$v]['product_name'] = $groupProduct->name;
                    $product_list[$v]['product_cover_src'] = $groupProduct->main_cover_src;
                    $product_list[$v]['product_group_price'] = $cheapestSku->group_price;
                    $product_list[$v]['product_price'] = $cheapestSku->org_price;
                    $product_list[$v]['person_count'] = /*$groupProduct->sharingActiveSucc()->count() +*/ $activity['fake_count'];
                }
            }
        }
        $activity['product_list'] = array_values($product_list);

        $this->success('ok', [ 'activity' => $activity]);
    }

    public function sharingActiveRoll() {
        $fakeData = [
            ['nick_name' => 'Dave_王', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/Dave_%E7%8E%8B.jpg'],
            ['nick_name' => 'Dum胜佳', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/Dum%E8%83%9C%E4%BD%B3.jpg'],
            ['nick_name' => 'Eric Kwok', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/Eric%20Kwok.jpeg'],
            ['nick_name' => 'Jeff', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/Jeff.jpeg'],
            ['nick_name' => 'Jessica', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/Jessica.jpg'],
            ['nick_name' => 'Solky', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/Solky.jpg'],
            ['nick_name' => 'xuexue', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/xuexue.jpg'],
            ['nick_name' => '不惧未来', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/%E4%B8%8D%E6%83%A7%E6%9C%AA%E6%9D%A5.jpg'],
            ['nick_name' => '且行且乐', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/%E4%B8%94%E8%A1%8C%E4%B8%94%E4%B9%90.jpg'],
            ['nick_name' => '初冬の雪', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/%E5%88%9D%E5%86%AC%E3%81%AE%E9%9B%AA.jpg'],
            ['nick_name' => '初雪未央', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/%E5%88%9D%E9%9B%AA%E6%9C%AA%E5%A4%AE.jpg'],
            ['nick_name' => '喜剧芝王', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/%E5%96%9C%E5%89%A7%E8%8A%9D%E7%8E%8B.jpeg'],
            ['nick_name' => '废材本才', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/%E5%BA%9F%E6%9D%90%E6%9C%AC%E6%89%8D.jpg'],
            ['nick_name' => '恋空的人', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/%E6%81%8B%E7%A9%BA%E7%9A%84%E4%BA%BA.jpg'],
            ['nick_name' => '放风筝的人', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/%E6%94%BE%E9%A3%8E%E7%AD%9D%E7%9A%84%E4%BA%BA.jpg'],
            ['nick_name' => '毛毛有人爱', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/%E6%AF%9B%E6%AF%9B%E6%9C%89%E4%BA%BA%E7%88%B1.jpg'],
            ['nick_name' => '狼行天下', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/%E7%8B%BC%E8%A1%8C%E5%A4%A9%E4%B8%8B.jpg'],
            ['nick_name' => '王泽霖', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/%E7%8E%8B%E6%B3%BD%E9%9C%96.jpg'],
            ['nick_name' => '玛丽莲-Lyn', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/%E7%8E%9B%E4%B8%BD%E8%8E%B2-Lyn.jpg'],
            ['nick_name' => '琳琳', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/%E7%90%B3%E7%90%B3.jpeg'],
            ['nick_name' => '草是艹字头的草', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/%E8%8D%89%E6%98%AF%E8%89%B9%E5%AD%97%E5%A4%B4%E7%9A%84%E8%8D%89.jpg'],
            ['nick_name' => '莫妮卡', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/%E8%8E%AB%E5%A6%AE%E5%8D%A1.jpg'],
            ['nick_name' => '莫问前程', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/%E8%8E%AB%E9%97%AE%E5%89%8D%E7%A8%8B.jpg'],
            ['nick_name' => '行于脚下', 'avatar' => 'https://yydata.oss-cn-shenzhen.aliyuncs.com/website/yyshop/danmu/%E8%A1%8C%E4%BA%8E%E8%84%9A%E4%B8%8B.jpg'],
        ];
        $sharingActiveModel = new SharingActiveModel();
        $sharingActives = $sharingActiveModel->where('status', '=', SharingStatus::SHARING_ING)->order('create_time', 'DESC')->limit(20)->select();
        $list = [];
        foreach($sharingActives as $active) {
            $creator = $active->creator()->find();
            if(!empty($creator)){
                $list[] = ['nick_name' => $creator['nick_name'], 'avatar' => $creator['avatar']];
            }
        }
        $len = count($list);
        if($len < 20){
            $randomKeys = array_rand($fakeData, 20 - $len);
            foreach($randomKeys as $key){
                $list[] = $fakeData[$key];
            }
        }
        $this->success('ok', $list);
    }

    public function onGoing() {
        $mobile = $this->request->param('mobile', '');
        if(empty($mobile)){
            $this->error('手机号码为空。');
        }
        $grouping = false;
        $wxUserModel = new WxUserModel();
        $wxUser = $wxUserModel->where('mobile', $mobile)->find();
        if(!empty($wxUser)){
            $activeUsers = $wxUser->sharingActiveUsers()->select();
            foreach($activeUsers as $activeUser) {
                $active = $activeUser->sharingActiveIng()->find();
                if(!empty($active)){
                    $grouping = true;
                    break;
                }
            }
        }
        $this->success('ok', ['grouping' => $grouping]);
    }
}