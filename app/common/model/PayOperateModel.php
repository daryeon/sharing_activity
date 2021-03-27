<?php

namespace app\common\model;

use think\Model;

class PayOperateModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $hidden = [
        'update_time'
    ];

    protected $type = [
    ];

    public function addLog($user_id, $data = [], $operator = 'system', $action = ''){
        try{
            $tmp = [];
            $tmp['user_id'] = intval($user_id);
            $tmp['data'] = is_string($data) ? $data : json_encode($data);
            $tmp['act'] = $action;
            $tmp['operator'] = $operator;
            $tmp['create_time'] = time();
            $this->insert($tmp);
        }catch (\Exception $e){
            echo $e->getMessage();die;
        }

    }
}
