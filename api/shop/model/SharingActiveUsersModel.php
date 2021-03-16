<?php

namespace api\shop\model;

use app\common\enum\SharingStatus;
use think\Model;
use think\Exception;

class SharingActiveUsersModel extends Model
{
    protected $autoWriteTimestamp = true;

    public function getSharingActiveByUserId($user_id){
        return $this->alias('sau')->where('sau.user_id', '=', $user_id)->where('sa.status', '<>', 30)->leftJoin('SharingActive sa', 'sa.id = sau.active_id')->find();
    }

    public function sharingActive(){
        return $this->belongsTo('SharingActiveModel', 'active_id')->where('status', '<>', SharingStatus::SHARINGFAIL);
    }

    public function sharingActiveSucc(){
        return $this->belongsTo('SharingActiveModel', 'active_id')->where('status', SharingStatus::SHARINGSUCC);
    }

    public function getSharingActiveUsers($active_id){
        return $this->alias('sau')->where('sau.active_id', '=', $active_id)->leftJoin('wx_user u', 'sau.user_id = u.id')->order('is_creator', 'DESC')->select();
    }

    public function sharingActiveIng(){
        return $this->belongsTo('SharingActiveModel', 'active_id')->where('status', SharingStatus::SHARING_ING);
    }
}
