<?php


namespace app\shop\controller;


use app\shop\model\SiteModel;
use app\shop\service\CoachService;
use app\shop\service\OssService;
use cmf\controller\AdminBaseController;
use think\facade\Cache;

class AdminSiteController extends AdminBaseController
{
    public function index()
    {
        $name = $this->request->param('name', '');
        $district = $this->request->param('district', '');
        $site_nature = $this->request->param('site_nature', '');
        $cooperate_nature = $this->request->param('cooperate_nature', '');
        $status = $this->request->param('status', '');
        $model = new SiteModel();

        $model = $model->order("create_time DESC")->where('delete_time', 0);
        if (!empty($name)) {
            $model->where('name', 'like', '%' . $name . '%');
        }
        if ($district!='') {
            $model->where('district', '=', $district);
        }
        if (!empty($site_nature)) {
            $model->where('site_nature', '=', $site_nature);
        }
        if (!empty($cooperate_nature)) {
            $model->where('cooperate_nature', '=', $cooperate_nature);
        }
        if ($status!='') {
            $model->where('status', '=', $status);
        }

        $data = $model->paginate(20);
        $this->assign('name', $name);
        $this->assign('district', $district);
        $this->assign('district_list', SiteModel::District);
        $this->assign('site_nature', $site_nature);
        $this->assign('site_nature_list', SiteModel::SiteNature);
        $this->assign('cooperate_nature', $cooperate_nature);
        $this->assign('cooperate_nature_list', SiteModel::CooperateNature);
        $this->assign('status', $status);
        $this->assign('status_list', SiteModel::Status);
        $this->assign('opening_status_list', SiteModel::OpeningStatus);
        $this->assign('data', $data);
        $this->assign('page', $data->render());
        return $this->fetch();
    }

    public function add()
    {
        $this->assign('district_list', SiteModel::District);
        $this->assign('site_nature_list', SiteModel::SiteNature);
        $this->assign('cooperate_nature_list', SiteModel::CooperateNature);
        $this->assign('status_list', SiteModel::Status);
        return $this->fetch();
    }

    public function addPost()
    {
        $data = $this->request->param();
        if ($this->request->isPost()) {
            if (!empty($data['post']['bind_id'])){
                CoachService::get_coach_lists($data['post']['bind_id']);
            }

            $ossClient = OssService::getInstance();
            if (!empty($data['photo_names']) && !empty($data['detail_src'])) {
                $data['post']['details'] = [];
                foreach ($data['detail_src'] as $key => $url) {
                    if ($key < 9) {
                        $url = $ossClient->upload($url);
                        array_push($data['post']['details'], $url);
                    }
                }
            }else{
                $this->error("请上传至少一张场地图片 ！");
            }
            $data['post']['detail_src'] = implode(',', empty($data['post']['details']) ? [] : $data['post']['details']);
            unset($data['post']['details']);

            if (!empty($data['post']['guide_src'])) {
                $url = $ossClient->upload($data['post']['guide_src']);
                $data['post']['guide_src'] = $url;
            }

            if (!empty($data['post']['share_poster'])) {
                $url = $ossClient->upload($data['post']['share_poster']);
                $data['post']['share_poster'] = $url;
            }
            //营业时间
            $data['post']['opening_times'] = date('H:i', strtotime($data['post']['start_time'])) . "~" . date('H:i', strtotime($data['post']['end_time']));
            unset($data['post']['start_time']);
            unset($data['post']['end_time']);
            //经纬度坐标
            if (empty($data['lat'])||empty($data['lng'])){
                $this->error("请填写地址坐标!");
            }
            $data['post']['coordinate'] = json_encode(['lat'=>$data['lat'],'lng'=>$data['lng']]);

            //设施条件
            if (!empty($data['post']['facility'])){
                $data['post']['facility'] = implode(',', $data['post']['facility']);
            }

            $result = $this->validate($data['post'], 'AdminSite.add');
            if ($result !== true) {
                $this->error($result);
            }

            if (in_array($data['post']['site_nature'],['2','3']) && empty($data['post']['site_label'])){
                $this->error("模拟考场标签不能为空!");
            }

            $data['post']['create_time'] = time();
            $data['post']['update_time'] = time();
            $model = new SiteModel();

            $inst = $model->create($data['post']);
            $id = $inst->id;

            $this->success('保存成功!', '', compact('id'));

        }
    }

    public function edit()
    {
        $id = $this->request->param('id', 0, 'intval');
        $model = new SiteModel();
        $post = $model->where('id', $id)->find();
        $detail_src = [];
        if (!empty($post['detail_src'])) {
            $detail_src = explode(',', $post['detail_src']);
        }

        if (!empty($post['coordinate'])) {
            $coordinate = json_decode($post['coordinate'], true);
        }
        $opening_times = explode('~', $post['opening_times']);

        $this->assign('id', $id);
        $this->assign('facility_list', SiteModel::Facility);
        $this->assign('district_list', SiteModel::District);
        $this->assign('site_nature_list', SiteModel::SiteNature);
        $this->assign('cooperate_nature_list', SiteModel::CooperateNature);
        $this->assign('status_list', SiteModel::Status);
        $this->assign('photos', $detail_src);
        $this->assign('coordinate', $coordinate);
        $this->assign('opening_times', $opening_times);
        $this->assign('post', $post);
        return $this->fetch();
    }

    function editPost()
    {
        if ($this->request->isPost()) {
            $data = $this->request->param();
            if (!empty($data['post']['bind_id'])){
                CoachService::get_coach_lists($data['post']['bind_id']);
            }
            $id = $data['post']['id'];
            $ossClient = OssService::getInstance();
            if (!empty($data['detail_src'])) {
                $data['post']['details'] = [];
                foreach ($data['detail_src'] as $key => $url) {
                    if ($key < 9) {
                        $url = $ossClient->upload($url);
                        array_push($data['post']['details'], $url);
                    }
                }
            }else{
                $this->error("请上传至少一张场地图片 ！");
            }
            $data['post']['detail_src'] = implode(',', empty($data['post']['details']) ? [] : $data['post']['details']);
            unset($data['post']['details']);

            if (!empty($data['post']['guide_src'])) {
                $url = $ossClient->upload($data['post']['guide_src']);
                $data['post']['guide_src'] = $url;
            }

            if (!empty($data['post']['share_poster'])) {
                $url = $ossClient->upload($data['post']['share_poster']);
                $data['post']['share_poster'] = $url;
            }
            //营业时间
            $data['post']['opening_times'] = date('H:i', strtotime($data['post']['start_time'])) . "~" . date('H:i', strtotime($data['post']['end_time']));
            unset($data['post']['start_time']);
            unset($data['post']['end_time']);
            //经纬度坐标
            if (empty($data['lat'])||empty($data['lng'])){
                $this->error("请填写地址坐标!");
            }

            $data['post']['coordinate'] = json_encode(['lat'=>$data['lat'],'lng'=>$data['lng']]);
            //设施条件
            if (!empty($data['post']['facility'])){
                $data['post']['facility'] = implode(',', $data['post']['facility']);
            }

            $result = $this->validate($data['post'], 'AdminSite.add');
            if ($result !== true) {
                $this->error($result);
            }

            if (in_array($data['post']['site_nature'],['2','3']) && empty($data['post']['site_label'])){
                $this->error("模拟考场标签不能为空!");
            }
            $data['post']['create_time'] = time();
            $data['post']['update_time'] = time();
            $model = new SiteModel();
            $inst = $model->where('id', $id)->update($data['post']);

            $this->success('保存成功!', '', compact('id'));
        }
    }
    public function delete()
    {
        $param           = $this->request->param();
        $mod = new SiteModel();

        if (isset($param['id'])) {
            $id           = $this->request->param('id', 0, 'intval');
            $mod
                ->where('id', $id)
                ->delete();
            $this->success("删除成功！", '');
        }
    }

    public function address_list(){
        $param           = $this->request->param();
        $url = "https://apis.map.qq.com/ws/place/v1/search?keyword={$param['city']}&boundary=region(%E5%B9%BF%E5%B7%9E%E5%B8%82,0)&page_size=5&page_index=1&key=X7EBZ-7QUEW-BCRRO-OLDFU-EPHLK-TXB44&output=json&&callback=&_=1607310315243";
        $data = curl($url);
        $data = json_decode($data,true);
        $this->success('','',$data['data']);
    }

}