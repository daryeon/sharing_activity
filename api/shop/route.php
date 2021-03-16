<?php

use think\facade\Route;


Route::get('shop/product/list', 'shop/Product/index');
Route::post('shop/user/enter', 'shop/WxUser/wxEnter');
Route::get('shop-group/sharing/active/roll', 'shop/GroupGoods/sharingActiveRoll');
Route::get('shop/group/goods/detail', 'shop/GroupGoods/detail');
Route::get('shop/group/goods', 'shop/GroupGoods/list');
Route::get('shop/group/share/get_group_products', 'shop/Sharing/get_group_products');
Route::get('shop/goods/can_join_active', 'shop/Goods/can_join_active');
Route::get('shop/user/wxcode', 'shop/WxUser/getWxCode');
Route::get('shop/open/wxtoken', 'shop/OpenApi/getWxAppAccessToken');

