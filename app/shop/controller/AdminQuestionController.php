<?php
namespace app\shop\controller;

use cmf\controller\AdminBaseController;
use app\shop\model\QuestionModel;

class AdminQuestionController extends AdminBaseController
{
    public function index()
    {
        $keyword = $this->request->param('keyword', '');
        $questionModel = new QuestionModel();
        $model = $questionModel->where('delete_time', 0);
        if(!empty($keyword)){
            $model->where('title', 'like', '%'.$keyword.'%');
        }
        $data = $model->paginate(10);
        $this->assign('keyword', $keyword);
        $this->assign('questions', $data);
        $this->assign('status_text', $questionModel->statusText());
        $this->assign('page', $data->render());
        return $this->fetch();
    }

    public function add()
    {
        $questionModel = new QuestionModel();
        $this->assign('statuses', $questionModel->statusText());
        $this->assign('post', ['src' => '']);
        return $this->fetch();
    }

    public function edit()
    {
        $id = $this->request->param('id', 0, 'intval');

        $questionModel = new QuestionModel();
        $post            = $questionModel->where('id', $id)->find();
        $this->assign('statuses', $questionModel->statusText());
        $this->assign('post', $post);

        return $this->fetch();
    }

    public function addPost()
    {
        if ($this->request->isPost()) {
            $data = $this->request->param();

            $post = $data['post'];

            $result = $this->validate($post, 'AdminQuestion.add');
            if ($result !== true) {
                $this->error($result);
            }

            $questionModel = new QuestionModel();

            $questionModel->adminAddQuestion($data['post']);

            $this->success('添加成功!', url('AdminQuestion/index'));
        }
    }

    public function editPost()
    {

        if ($this->request->isPost()) {
            $data = $this->request->param();

            $post   = $data['post'];
            $result = $this->validate($post, 'AdminQuestion.edit');
            if ($result !== true) {
                $this->error($result);
            }

            $questionModel = new QuestionModel();

            $questionModel->adminEditQuestion($data['post']);

            $this->success('保存成功!');
        }
    }

    public function delete()
    {
        $param           = $this->request->param();
        $questionModel = new QuestionModel();

        if (isset($param['id'])) {
            $id           = $this->request->param('id', 0, 'intval');
            $questionModel
                ->where('id', $id)
                ->update(['delete_time' => time()]);
            $this->success("删除成功！", '');
        }
    }
}