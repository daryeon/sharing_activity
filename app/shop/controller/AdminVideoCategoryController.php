<?php
namespace app\shop\controller;

use cmf\controller\AdminBaseController;
use app\shop\model\VideoCategoryModel;
use app\shop\service\OssService;
use think\Db;

class AdminVideoCategoryController extends AdminBaseController
{
    public function index()
    {
        
        $keyword = $this->request->param('keyword', '');
        $videoCategoryModel = new VideoCategoryModel();
        $model = $videoCategoryModel->where('delete_time', 0);
        if(!empty($keyword)){
            $model->where('name', 'like', '%'.$keyword.'%');
        }
        $model->withCount(['videos'=>function($query){
            $query->where('status',1);
            $query->where('delete_time',0);
        }]);
        $data = $model->paginate(10);
        $this->assign('keyword', $keyword);
        $this->assign('video_categories', $data);
        $this->assign('page', $data->render());
        return $this->fetch();
    }

    public function add()
    {
        return $this->fetch();
    }

    public function edit()
    {
        $id = $this->request->param('id', 0, 'intval');

        $videoCategoryModel = new VideoCategoryModel();
        $post            = $videoCategoryModel->where('id', $id)->find();

        $this->assign('post', $post);

        return $this->fetch();
    }

    public function addPost()
    {
        if ($this->request->isPost()) {
            $data = $this->request->param();

            $post = $data['post'];

            $result = $this->validate($post, 'AdminVideoCategory.add');
            if ($result !== true) {
                $this->error($result);
            }

            $videoCategoryModel = new VideoCategoryModel();

            $videoCategoryModel->adminAddVideoCategory($data['post']);

            $this->success('添加成功!', url('AdminVideoCategory/index'));
        }
    }

    public function editPost()
    {

        if ($this->request->isPost()) {
            $data = $this->request->param();

            $post   = $data['post'];
            $result = $this->validate($post, 'AdminVideoCategory.edit');
            if ($result !== true) {
                $this->error($result);
            }

            $videoCategoryModel = new VideoCategoryModel();

            $videoCategoryModel->adminEditVideoCategory($data['post']);

            $this->success('保存成功!');
        }
    }

    public function delete()
    {
        $param           = $this->request->param();
        $videoCategoryModel = new VideoCategoryModel();

        if (isset($param['id'])) {
            $id           = $this->request->param('id', 0, 'intval');
            $videoCategoryModel
                ->where('id', $id)
                ->update(['delete_time' => time()]);
            $this->success("删除成功！", '');
        }
    }
}