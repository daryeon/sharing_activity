<?php

namespace app\shop\model;

use app\common\enum\OrderStatus;
use think\Model;
use think\Exception;

class SharingActiveUsersModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $type = [
        'status'    =>  'integer',
    ];

    public function getSharingActiveUsersByActiveId($active_id){
        return $this->where('active_id', '=', $active_id)->select();
    }
}
