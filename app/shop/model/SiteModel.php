<?php


namespace app\shop\model;
use think\Model;


class SiteModel extends Model
{
    const SiteNature = [
        1 => '训练场',
        2 => '模拟场',
        3 => '训练场+模拟场',
    ];
    const CooperateNature = [
        1 => 'YY自营',
        2 => 'YA加盟',
        3 => 'YB挂靠',
    ];
    const Status = [
        0 => '下架',
        1 => '上架',
    ];

    const OpeningStatus = [
        1 => '使用中',
        2 => '建设中',
        3 => '撤场',
    ];
    //街区
    const District = [
        1 =>'越秀区',
        2 =>'海珠区',
        3 =>'天河区',
        4 =>'白云区',
        5 =>'黄埔区',
        6 =>'番禺区',
        7 =>'花都区',
        8 =>'南沙区',
        9 =>'增城区',
        10 =>'从化区',
        11 =>'荔湾区',
    ];

    //设施配置
    const Facility = [
        1 =>'WIFI',
        2 =>'晚上练车',
        3 =>'免费停车',
        4 =>'新车教学',
        5 =>'空调休息室',
    ];

    public function adminAddSite($data){

    }

    public function get_select_options(){
        $data = $this->field(['id','name'])->where('status','=',1)->select();
        return $data;
    }
}