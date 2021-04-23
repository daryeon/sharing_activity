<?php
namespace app\shop\controller;

use app\shop\model\VideoBlockModel;
use app\shop\model\VideoCategoryModel;
use cmf\controller\AdminBaseController;
use think\facade\Config;

class AdminVideoBlockController extends AdminBaseController
{
    public function index()
    {
        $keyword = $this->request->param('keyword', '');
        $videoBlockModel = new VideoBlockModel();
        $model = $videoBlockModel->where('delete_time', 0);
        if(!empty($keyword)){
            $model->where('show_name', 'like', '%'.$keyword.'%');
        }
        $data = $model->paginate(10);
        $url = Config::load('cms');
        $url = $url['cms'].'/api/portal/Categories/subCategories?category_id=26';

        $option = [];
        $cms_data = curl($url);
        if (!empty($cms_data)) {
            $cms_data = json_decode($cms_data,true);
            foreach ($cms_data['data']['categories'] as $key => $value) {
                $option[$value['id']]=$value['name'];
            }
        }
        
        $video_ca = [];
        $videoCategoryModel = new VideoCategoryModel();
        $categories = $videoCategoryModel->where(['delete_time' => 0, 'status' => 1])->select();
        foreach ($categories as $key => $value) {
            $video_ca[$value['id']] = $value['name'];
        }

        foreach ($data as $key => &$value) {
            if ($value['content_type'] == 2) {
                if (isset($option[$value['category_id']])){
                    $data[$key]['category_name'] = $option[$value['category_id']];
                }else{
                    $data[$key]['category_name'] = "不存在的文章集";
                }
            }else{
                $data[$key]['category_name'] = $video_ca[$value['category_id']];
            }
        }
        $this->assign('keyword', $keyword);
        $this->assign('video_blocks', $data);
        $this->assign('display_types', $videoBlockModel->displayTypes());
        $this->assign('content_types', $videoBlockModel->contentTypes());
        $this->assign('status_text', $videoBlockModel->statusText());
        $this->assign('page', $data->render());
        return $this->fetch();
    }

    public function add()
    {
        $videoBlockModel = new VideoBlockModel();
        $videoCategoryModel = new VideoCategoryModel();
        $categories = $videoCategoryModel->where(['delete_time' => 0, 'status' => 1])->select();
        

        $this->assign('display_types', $videoBlockModel->displayTypes());
        $this->assign('categories', $categories);
        return $this->fetch();
    }

    public function edit()
    {
        $id = $this->request->param('id', 0, 'intval');

        $videoBlockModel = new VideoBlockModel();
        $post            = $videoBlockModel->where('id', $id)->find();
        $option = [] ;
        if ($post['content_type'] == 2) {
            $url = Config::load('cms');
            $url = $url['cms'].'/api/portal/Categories/subCategories?category_id=26';

            $data = curl($url);
            if (!empty($data)) {
                $data = json_decode($data,true);
                foreach ($data['data']['categories'] as $key => $value) {
                    $option[$value['id']]=$value['name'];
                }
            }
        }
        $videoCategoryModel = new VideoCategoryModel();
        $categories = $videoCategoryModel->where(['delete_time' => 0, 'status' => 1])->select();
        $this->assign('categories', $categories);
        $this->assign('display_types', $videoBlockModel->displayTypes());
        $this->assign('post', $post);
        $this->assign('option', $option);

        return $this->fetch();
    }

    public function addPost()
    {
        if ($this->request->isPost()) {
            $data = $this->request->param();

            $post = $data['post'];

            $result = $this->validate($post, 'AdminVideoBlock.add');
            if ($result !== true) {
                $this->error($result);
            }

            $videoBlockModel = new VideoBlockModel();

            $videoBlockModel->adminAddVideoBlock($data['post']);

            $this->success('添加成功!', url('AdminVideoBlock/index'));
        }
    }

    public function editPost()
    {

        if ($this->request->isPost()) {
            $data = $this->request->param();

            $post   = $data['post'];
            $result = $this->validate($post, 'AdminVideoBlock.edit');
            if ($result !== true) {
                $this->error($result);
            }

            $videoBlockModel = new VideoBlockModel();

            $videoBlockModel->adminEditVideoBlock($data['post']);

            $this->success('保存成功!');
        }
    }

    public function delete()
    {
        $param           = $this->request->param();
        $videoBlockModel = new VideoBlockModel();

        if (isset($param['id'])) {
            $id           = $this->request->param('id', 0, 'intval');
            $videoBlockModel
                ->where('id', $id)
                ->update(['delete_time' => time()]);
            $this->success("删除成功！", '');
        }
    }

    public function get_post_category_option()
    {
        $param           = $this->request->param();
        $option = [];

        if ($param['content_type'] == 2) {
            $url = Config::load('cms');
            $url = $url['cms'].'/api/portal/Categories/subCategories?category_id=26';

            $data = curl($url);
            if (!empty($data)) {
                $data = json_decode($data,true);
                foreach ($data['data']['categories'] as $key => $value) {
                    $option[$value['id']]=$value['name'];
                }
            }
             
        }

        if ($param['content_type'] == 1) {
           $videoCategoryModel = new VideoCategoryModel();
           $categories = $videoCategoryModel->where(['delete_time' => 0, 'status' => 1])->select();
           foreach ($categories as $key => $value) {
                    $option[$value['id']]=$value['name'];
           }
        }

         $this->success("ok", '',$option);       
    }
}