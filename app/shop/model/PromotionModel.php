<?php


namespace app\shop\model;

use app\shop\service\OssService;
use think\Model;

class PromotionModel extends Model
{
    public function addPromotion($data){
        $result = true;
        self::startTrans();
        try {
            $data['create_time'] = time();
            $data['update_time'] = time();
            $this->allowField(true)->save($data);
            self::commit();
        } catch (\Exception $e) {
            self::rollback();
            $result = false;
        }
        return $result;
    }
    public function editPromotion($data){
        $result = true;
        self::startTrans();
        try {
            unset($data['more']);
            $data['update_time'] = time();

            $this->where('id',$data['id'])->update($data);

            $cou_mod = new CouponModel();
            $update = [
                'promotion_status' => $data['status'],
                'overlay_type' => $data['overlay_type'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
            ];
            if (isset($data['department'])){
                $update['department'] = $data['department'];
            }
            $cou_mod->where('promotion_id',$data['id'])->update($update);

            self::commit();
        } catch (\Exception $e) {
            self::rollback();
            return $e->getMessage();
        }
        return $result;
    }

    public function get_id_name(){
        $data = $this->field(['id','name'])->where('status',1)->where('is_public',1)->select();
        $re = [];
        foreach ($data as $v){
            $re[$v['id']] = $v['name'];
        }
        return $re;
    }
}