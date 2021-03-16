<?php

namespace api\shop\model;

use think\Model;
use api\shop\model\EmployeeModel;

class WxUserModel extends Model
{
    protected $autoWriteTimestamp = true;

    protected $type = [
        'user_type'    =>  'integer',
        'share_id'    =>  'integer',
    ];

    static function getRandString($len=64){ 
        // 密码字符集，可任意添加你需要的字符 
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'; 
        $password = ''; 
        for ( $i = 0; $i < $len; $i++ ) 
        { 
            $password .= $chars[ mt_rand(0, strlen($chars) - 1) ]; 
        } 
        return $password; 
    }
    public function getUserNameById($user_id){
        return $this->field('`nick_name`')->where('id', '=', $user_id)->find();
    }

    public function addUser($data)
    {
        $thisModel = new WxUserModel();
        $data['api_token'] = WxUserModel::getRandString();
        $inst = $thisModel->allowField(true)->create($data);

        return $inst;
    }

    public function sharingActiveUsers() {
        return $this->hasMany('SharingActiveUsersModel', 'user_id');
    }

    public function employee($mobile) {
        if(!empty($mobile)){
            $employeeModel = new EmployeeModel();
            return $employeeModel->where('mobile', $mobile)->where('delete_time', 0)->where('status', 1)->find();
        }
        return null;
    }
}
