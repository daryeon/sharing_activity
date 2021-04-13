<?php


namespace app\shop\model;


use think\Model;

class ProductLogModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $hidden = [
        'update_time'
    ];

    protected $type = [
    ];

}
