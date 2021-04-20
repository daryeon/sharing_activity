<?php
namespace app\shop\controller;

use cmf\controller\AdminBaseController;
use app\shop\model\VideoModel;
use app\shop\model\VideoCategoryModel;

class AdminVideoController extends AdminBaseController
{
    public function index()
    {
        
        $keyword = $this->request->param('keyword', '');
        $videoModel = new VideoModel();
        $model = $videoModel->where('delete_time', 0);
        if(!empty($keyword)){
            $model->where('title', 'like', '%'.$keyword.'%');
        }
        $model->order('list_order', 'asc');
        $model->order('id', 'desc');
        $data = $model->paginate(10);
        $this->assign('keyword', $keyword);
        $this->assign('videos', $data);
        $this->assign('status_text', $videoModel->statusText());
        $this->assign('page', $data->render());
        return $this->fetch();
    }

    public function add()
    {
        $videoCategoryModel = new VideoCategoryModel();
        $categories = $videoCategoryModel->where(['delete_time' => 0, 'status' => 1])->select();
        $this->assign('categories', $categories);
        $this->assign('post', ['src' => '']);
        return $this->fetch();
    }

    public function edit()
    {
        $id = $this->request->param('id', 0, 'intval');

        $videoModel = new VideoModel();
        $post            = $videoModel->where('id', $id)->find();
        $videoCategoryModel = new VideoCategoryModel();
        $categories = $videoCategoryModel->where(['delete_time' => 0, 'status' => 1])->select();
        $durationMin = 0;
        $durationSec = 0;
        $segs = explode(':', $post['duration']);
        if(count($segs) == 2){
            $durationMin = intval($segs[0]);
            $durationSec = intval($segs[1]);
        }
        $this->assign('categories', $categories);
        $this->assign('post', $post);
        $this->assign('duration_min', $durationMin);
        $this->assign('duration_sec', $durationSec);

        return $this->fetch();
    }

    public function addPost()
    {
        if ($this->request->isPost()) {
            $data = $this->request->param();

            $post = $data['post'];

            $result = $this->validate($post, 'AdminVideo.add');
            if ($result !== true) {
                $this->error($result);
            }

            $videoModel = new VideoModel();

            $videoModel->adminAddVideo($data['post']);

            $this->success('添加成功!', url('AdminVideo/index'));
        }
    }

    public function editPost()
    {

        if ($this->request->isPost()) {
            $data = $this->request->param();

            $post   = $data['post'];
            $result = $this->validate($post, 'AdminVideo.edit');
            if ($result !== true) {
                $this->error($result);
            }

            $videoModel = new VideoModel();

            $videoModel->adminEditVideo($data['post']);

            $this->success('保存成功!');
        }
    }

    public function delete()
    {
        $param           = $this->request->param();
        $videoModel = new VideoModel();

        if (isset($param['id'])) {
            $id           = $this->request->param('id', 0, 'intval');
            $videoModel
                ->where('id', $id)
                ->update(['delete_time' => time()]);
            $this->success("删除成功！", '');
        }
    }
}