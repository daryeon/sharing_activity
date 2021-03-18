<?php

namespace app\common\enum;

/**
 * 拼团状态枚举类
 * Class SharingStatus
 * @package app\common\enum
 */
class SharingStatus
{
    const WAIT_SHARING = 0;
    const SHARING_ING = 10;
    const SHARINGSUCC = 20;
    const SHARINGFAIL = 30;
    public static function getSharingMap(){
        return [
            self::WAIT_SHARING => '全部', self::SHARING_ING => '拼单中', self::SHARINGSUCC => '拼单成功', self::SHARINGFAIL => '拼单失败'
        ];
    }
}