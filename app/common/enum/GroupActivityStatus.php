<?php

namespace app\common\enum;
class GroupActivityStatus
{
    const CLOSED = 0;
    const ON_GOING = 1;
    public static function getGroupActivityStatusMap(){
        return [
            self::CLOSED => '已关闭', self::ON_GOING => '未开始'
        ];
    }
}