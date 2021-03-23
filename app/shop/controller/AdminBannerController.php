<?php
namespace app\shop\controller;

use cmf\controller\AdminBaseController;
use app\shop\model\BannerModel;
use app\shop\service\OssService;
use think\Db;

class AdminBannerController extends AdminBaseController
{
    public function index()
    {
        $keyword = $this->request->param('keyword', '');
        $shopBannerModel = new BannerModel();
        $model = $shopBannerModel->where('delete_time', 0);
        if(!empty($keyword)){
            $model->where('title', 'like', '%'.$keyword.'%');
        }
        $model->order('list_order', 'asc');
        $model->order('id', 'desc');
        $data = $model->paginate(10);
        $this->assign('banners', $data);
        $this->assign('keyword', $keyword);
        $this->assign('statusText', $shopBannerModel->statusText());
        $this->assign('jumpTypeText', $shopBannerModel->jumpTypeText());
        $this->assign('page', $data->render());
        return $this->fetch();
    }

    public function add()
    {
        $shopBannerModel = new BannerModel();
        $model = $shopBannerModel->where('delete_time', 0);
        $model->order('list_order', 'asc');
        $data = $model->find();
        $listOrder = 10000;
        if(!empty($data) && !empty($data->list_order)){
            $listOrder = $data->list_order > 1 ? $data->list_order - 1 : 1;
        }
        $this->assign('listOrder', $listOrder);
        return $this->fetch();
    }

    public function edit()
    {
        $id = $this->request->param('id', 0, 'intval');

        $bannerModel = new BannerModel();
        $post            = $bannerModel->where('id', $id)->find();

        $this->assign('post', $post);

        return $this->fetch();
    }

    public function addPost()
    {
        if ($this->request->isPost()) {
            $data = $this->request->param();

            $post = $data['post'];

            $result = $this->validate($post, 'AdminBanner.add');
            if ($result !== true) {
                $this->error($result);
            }

            $shopBannerModel = new BannerModel();

            $shopBannerModel->adminAddBanner($data['post']);

            $this->success('添加成功!', url('AdminBanner/index'));
        }
    }

    public function editPost()
    {

        if ($this->request->isPost()) {
            $data = $this->request->param();

            $post   = $data['post'];
            $result = $this->validate($post, 'AdminBanner.edit');
            if ($result !== true) {
                $this->error($result);
            }

            $bannerModel = new BannerModel();

            $bannerModel->adminEditBanner($data['post']);

            $this->success('保存成功!');
        }
    }

    public function delete()
    {
        $param           = $this->request->param();
        $bannerModel = new BannerModel();

        if (isset($param['id'])) {
            $id           = $this->request->param('id', 0, 'intval');
            $resultPortal = $bannerModel
                ->where('id', $id)
                ->update(['delete_time' => time()]);
            $this->success("删除成功！", '');
        }
    }
}