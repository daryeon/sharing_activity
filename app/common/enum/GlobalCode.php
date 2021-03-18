<?php

namespace app\common\enum;

class GlobalCode
{
    const ERR_UNKNOWN = 40000; // 未知错误
    const ERR_MISS_MOBILE = 40001; // 缺少手机号，去授权
    const ERR_NOT_REGISTER = 40002; // 未报名
    const ERR_NOT_CHANGECOURSE = 40003; // 不允许转班;
    const ERR_PLEASE_SIGN = 40004; // 请先签名
    const ERR_FILE_UPDATE_FAIL = 40005; // 文件上传失败

}