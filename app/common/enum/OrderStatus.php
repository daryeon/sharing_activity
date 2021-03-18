<?php

namespace app\common\enum;

/**
 * 订单状态枚举类
 * Class OrderStatus
 * @package app\common\enum
 */
class OrderStatus
{
    const WAIT_BUYER_PAY = 'WAIT_BUYER_PAY';
    const BUYER_IS_PAY = 'BUYER_IS_PAY';
    const ORDER_REFUND = 'ORDER_REFUND';
    const ORDER_CANCEL = 'ORDER_CANCEL';
    public static function getPayStatusMap(){
        return [
            self::WAIT_BUYER_PAY => '待付款', self::BUYER_IS_PAY => '已支付', self::ORDER_REFUND => '已退款',self::ORDER_CANCEL => '已取消',
        ];
    }

    const REFUND_SUCCESS = 'REFUND_SUCCESS';
    const REFUND_FAIL = 'REFUND_FAIL';
    public static function getRefundStatusMap(){
        return [
            self::REFUND_SUCCESS => '退款成功', self::REFUND_FAIL => '退款失败'
        ];
    }
    public static function getCenterPayStatusMap(){
        return [
            self::WAIT_BUYER_PAY => '待支付', self::BUYER_IS_PAY => '已支付，待签署协议', self::ORDER_REFUND => '已退款'
        ];
    }

    const INIT = 0;
    const DONE = 1; // 已签署协议
    const CANCEL = 2;
    public static function getStatusMap(){
        return [self::INIT => '正常', self::DONE => '完成', self::CANCEL => '取消'];
    }

    const NUTSET = '';
    const HUILAIMI = 'huilaimi';
    const WX = 'wx';
    public static function getPayTypeMap(){
        return [self::NUTSET => '未设置', self::HUILAIMI => '汇来米', self::WX => '微信'];
    }

    const MASTER = 10;
    const SHARING = 20;
    // @todo modelTYpe master sharing
    public static function getOrderType(){
        return [self::MASTER => '普通', self::SHARING => '拼团'];
    }


    const MANBIPEI = 'manbipei';
    const ONE_V_ONE = 'one_v_one';
    public static function getServiceNameMap(){
        return [self::MANBIPEI => '升级"慢必赔"', self::ONE_V_ONE => '升级"1人1车"'];
    }
    public static function getServiceNameBackendMap(){
        return [self::MANBIPEI => '慢必赔', self::ONE_V_ONE => '1人1车'];
    }
}