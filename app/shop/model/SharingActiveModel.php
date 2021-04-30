<?php

namespace app\shop\model;

use app\common\enum\OrderStatus;
use app\common\enum\SharingStatus;
use think\db\Where;
use think\Model;
use think\Exception;

class SharingActiveModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $type = [
        'status'    =>  'integer',
    ];

    public function getVaildSharingActiveCount(){
        $at = time();
        // `sa`.*,gp.name goods_name,ga.status activity_status,ga.end_time activity_end_time
        $sql = "SELECT 
count(*) as `num`
FROM `shop_sharing_active` `sa` 
LEFT JOIN `shop_group_product` `gp` ON `sa`.`goods_id`=`gp`.`id` 
LEFT JOIN `shop_group_activity` `ga` ON `gp`.`activity_id`=`ga`.`id` 
WHERE  `sa`.`status` = ".SharingStatus::SHARING_ING." AND (`sa`.`end_time` < ".$at." OR `ga`.`end_time` < ".$at.")";
        $num = $this->execute($sql);
        return empty($num) ? 0 : $num;
        return $this->field('sa.*,gp.name goods_name,ga.status activity_status,ga.end_time activity_end_time')->alias('sa')
            ->leftJoin('group_product gp', 'sa.goods_id = gp.id')
            ->leftJoin('group_activity ga', 'gp.activity_id = ga.id')
            ->where('sa.status', '=', SharingStatus::SHARING_ING)
            #->whereXor()
            #->whereOr('ga.end_time', '<', time())
            #->whereOr('sa.end_time', '<', time())
            ->count();
    }

    public function getVaildSharingActive($page, $limit = 50){
        $at = time();
        $sql = "SELECT 
`sa`.*,gp.name goods_name,ga.status activity_status,ga.end_time activity_end_time
FROM `shop_sharing_active` `sa` 
LEFT JOIN `shop_group_product` `gp` ON `sa`.`goods_id`=`gp`.`id` 
LEFT JOIN `shop_group_activity` `ga` ON `gp`.`activity_id`=`ga`.`id` 
WHERE  `sa`.`status` = ".SharingStatus::SHARING_ING." AND (`sa`.`end_time` < ".$at." OR `ga`.`end_time` < ".$at.")
LIMIT {$page},{$limit}";
        $res = $this->query($sql);
        return empty($res) ? [] : $res;
        return $this->field('sa.*,gp.name goods_name,ga.status activity_status,ga.end_time activity_end_time')->alias('sa')
            ->leftJoin('group_product gp', 'sa.goods_id = gp.id')
            ->leftJoin('group_activity ga', 'gp.activity_id = ga.id')
            ->where('sa.status', '=', SharingStatus::SHARING_ING)
            ->limit($page, $limit);
    }

    public function getSharingActive15MinutesCount(){
        return $this->where('status', '=', SharingStatus::SHARING_ING)
            ->where('create_time', '>', time() - 900)
            ->count();
    }

    public function getSharingActive15Minutes($page, $limit = 50){
        return $this->where('status', '=', SharingStatus::SHARING_ING)
            ->where('create_time', '>', time() - 900)
            ->limit($page, $limit);
    }

    public function getSharingActiveByActiveId($active_id){
        return self::get($active_id);
    }
}
