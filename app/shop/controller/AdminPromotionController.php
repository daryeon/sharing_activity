<?php


namespace app\shop\controller;

use api\shop\model\ProductCouponModel;
use app\common\model\CouponModel;
use app\common\model\ProductModel;
use app\shop\model\PromotionModel;
use app\shop\service\OssService;
use cmf\controller\AdminBaseController;
use think\Db;
use think\facade\Cache;

class AdminPromotionController extends AdminBaseController
{
    protected $type;
    protected $overlay_type;
    protected $overlay_type_arr = [

    ];

    public function __construct()
    {
        parent::__construct();
        $this->type = $this->request->param('type');      //1-学车优惠券,a类, 2-成交优惠券,3-c类优惠券

        if ($this->type == 1) {
            $this->overlay_type = [1, 3];
        } else {
            $this->overlay_type = [2, 3];
        }

    }

    public function index()
    {
        $mod = new PromotionModel();
        $keyword = $this->request->param('keyword');
        $status = $this->request->param('status', '');
        $overlay_type = $this->request->param('overlay_type');
        $start_time = $this->request->param('start_time');
        $end_time = $this->request->param('end_time');
        $is_public = ($this->type == 2) ? 0 : 1;
        $where = [];
        if (!empty($keyword)) {
            $mod = $mod->where('name', 'like', '%' . $keyword . '%');
        }
        if ($status !== '') {
            $where['status'] = $status;
        }

        if (!empty($overlay_type)) {
            $mod = $mod->where('overlay_type', '=', $overlay_type);
        } else {
            $mod = $mod->whereIn('overlay_type', $this->overlay_type);
        }
        if (!empty($start_time) && !empty($end_time)) {
            $start_time = strtotime($start_time);
            $end_time = strtotime($end_time);
            $mod = $mod->whereBetween('create_time', [$start_time, $end_time]);
        } else {
            $start_time = null;
            $end_time = null;
        }

        if (!empty($where)) {
            $data = $mod->
            where($where)
                ->where('delete_time', 0)
                ->where('is_public', $is_public)
                ->order('id', 'desc')
                ->paginate(20);
        } else {
            $data = $mod
                ->where('delete_time', 0)
                ->where('is_public', $is_public)
                ->order('id', 'desc')
                ->paginate(20);
        }
        $this->assign('data', $data);

        $pro_mod = new PromotionModel();


        $coupon_mod = new CouponModel();
        $coupons = $coupon_mod->get_id_name();
        $prod_mod = new ProductModel();
        $products = $prod_mod->get_id_name_price();


        if ($this->type == 2) {
            $department = $this->department();
            foreach ($department as $k => $v) {
                $ads[$v['id']] = $v;
            }
        }


        foreach ($data as &$v) {
            $quan = '';
            $quan_data = CouponModel::where('promotion_id', $v['id'])->column('distinct id,amount');
            foreach ($quan_data as $key=>$item) {
                @$quan .= $coupons[$key] ."-".$item. "元<br>";
            }
            $v['quan'] = $quan;
            $shangp = '';
            $shangp_data = ProductCouponModel::where('promotion_id', $v['id'])->column('distinct product_id');
            foreach ($shangp_data as $item) {
                $shangp .= $item . "  " . $products[$item]['name'] . "  {$products[$item]['price']}" . "<br>";
            }
            $dept = '';
            if ($this->type == 2 && !empty($v['department']) && !empty($ads)) {
                $dep = explode(",", $v['department']);
                foreach ($dep as $item) {
                    @$dept .= $ads[$item]['name'] . "<br>";
                }
            }
            $v['shangp'] = $shangp;
            $v['department'] = $dept;
            $v['status'] = ($v['status'] == 0) ? "草稿" : (($v['status'] == 1) ? "启用" : (($v['status'] == 3) ? "过期" : "停用"));
            $v['overlay_type'] = ($v['overlay_type'] == 3) ? "不可叠加" : "可叠加";
        }

        $this->assign('status', $status);
        $this->assign('keyword', $keyword);
        $this->assign('start_time', $start_time);
        $this->assign('end_time', $end_time);
        $this->assign('overlay_type', $overlay_type);
        $this->assign('type', $this->type);
        $this->assign('page', $data->render());
        return $this->fetch('shop@admin_promotion/index');
    }

    public function index2()
    {
        return $this->index();
    }

    /**
     * 添加文章分类
     * @adminMenu(
     *     'name'   => '添加文章分类',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '添加文章分类',
     *     'param'  => ''
     * )
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function add()
    {
        $department = $this->department();
        $arr = $this->GetTree($department);
        $this->assign('department', $arr);
        $this->assign('type', $this->type);

        return $this->fetch();
    }

    /**
     * 添加文章分类提交
     * @adminMenu(
     *     'name'   => '添加文章分类提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '添加文章分类提交',
     *     'param'  => ''
     * )
     */
    public function addPost()
    {
        $mod = new PromotionModel();

        $data = $this->request->param();

//        $result = $this->validate($data, 'PortalCategory');

//        if ($result !== true) {
//            $this->error($result);
//        }
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time'])+86399;
//        if ($this->type == 2){
//            $data['is_public'] = 0;
//        }else{
//            $data['is_public'] = 1;
//        }
        if (isset($data['type'])) {
            unset($data['type']);
        }
        if (!empty($data['more']['thumbnail'])) {
            $oss = new OssService();
            $data['img_src'] = $oss->upload($data["more"]['thumbnail']);
            unset($data['more']);
        }
        $result = $this->validate($data, 'AdminPromotion.add');
        if ($result !== true) {
            $this->error($result);
        }
        $data['manager'] = session('name');

        if (isset($data['department'])) {
            $data['department'] = implode(",", array_unique($data['department']));
        }
        $result = $mod->addPromotion($data);

        if ($result === false) {
            $this->error('添加失败!');
        }

        $this->success('添加成功!', url('AdminPromotion/index', "type={$this->type}"));
    }

    /**
     * 编辑文章分类
     * @adminMenu(
     *     'name'   => '编辑文章分类',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '编辑文章分类',
     *     'param'  => ''
     * )
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function edit()
    {
        $id = $this->request->param('id', 0, 'intval');
        $mod = new PromotionModel();
        $post = $mod->where('id', $id)->find();
        if ($this->type == 2) {
            $department = $this->department();
            $department = $this->GetTree($department);
            foreach ($department as $k => $v) {
                $ads[$v['id']] = $v;
            }
            $this->assign('department', $department);
            $act_department = [];
            if (!empty($post['department'])) {
                $ad = explode(",", $post['department']);
                foreach ($ad as $k => $v) {
                    @$act_department[$ads[$v]['id']] = $ads[$v];
                }
            }
            $this->assign('act_department', $act_department);
        }

        $this->assign('post', $post);
        $this->assign('id', $id);
        $this->assign('type', $this->type);
        return $this->fetch();

    }

    /**
     * 编辑文章分类提交
     * @adminMenu(
     *     'name'   => '编辑文章分类提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '编辑文章分类提交',
     *     'param'  => ''
     * )
     */
    public function editPost()
    {
        $data = $this->request->param();
//        if (isset($data['']))
//        $result = $this->validate($data, 'PortalCategory');

//        if ($result !== true) {
//            $this->error($result);
//        }
        $data['start_time'] = strtotime($data['start_time']);
        $data['end_time'] = strtotime($data['end_time'])+86399;

        $mod = new PromotionModel();
        $old = $mod->where('id', $data['id'])->find();
        if ($old['status'] != 0 && $data['status'] == 0) {
            $this->error("不能改回草稿");
        }

        if ($old['overlay_type'] != $data['overlay_type'] && $old['status'] != 0 ) {
            $this->error("不能编辑券叠加类型");
        }
//        if ($this->type == 2){
//            $data['is_public'] = 0;
//        }else{
//            $data['is_public'] = 1;
//        }
        if (isset($data['type'])) {
            unset($data['type']);
        }
        $oss = new OssService();
        if (!empty($data['more']['thumbnail'])) {
            $data['img_src'] = $oss->upload($data["more"]['thumbnail']);
        }
        $result = $this->validate($data, 'AdminPromotion.add');
        if ($result !== true) {
            $this->error($result);
        }

        if (isset($data['department'])) {
            $data['department'] = implode(",", array_unique($data['department']));
        }

        $result = $mod->editPromotion($data);
        if ($result !== true) {
            $this->error($result);
        }
        $this->success('保存成功!', url('AdminPromotion/index', "type={$this->type}"));
    }

    /**
     * 文章分类选择对话框
     * @adminMenu(
     *     'name'   => '文章分类选择对话框',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '文章分类选择对话框',
     *     'param'  => ''
     * )
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function select()
    {
        $ids = $this->request->param('ids');
        $selectedIds = explode(',', $ids);
        $mod = new PromotionModel();

        $tpl = <<<tpl
<tr class='data-item-tr'>
    <td>
        <input type='checkbox' class='js-check' data-yid='js-check-y' data-xid='js-check-x' name='ids[]'
               value='\$id' data-name='\$name' \$checked>
    </td>
    <td>\$id</td>
    <td>\$spacer <a href='\$url' target='_blank'>\$name</a></td>
</tr>
tpl;

        $categoryTree = $mod->AdminPromotionTableTree($selectedIds, $tpl);

        $data = $mod->where('delete_time', 0)->select();

        $this->assign('data', $data);
        $this->assign('selectedIds', $selectedIds);
        $this->assign('data_tree', $categoryTree);
        return $this->fetch();
    }

    /**
     * 文章分类排序
     * @adminMenu(
     *     'name'   => '文章分类排序',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '文章分类排序',
     *     'param'  => ''
     * )
     */
    public function listOrder()
    {
        parent::listOrders(Db::name('portal_category'));
        $this->success("排序更新成功！", '');
    }

    /**
     * 文章分类显示隐藏
     * @adminMenu(
     *     'name'   => '文章分类显示隐藏',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '文章分类显示隐藏',
     *     'param'  => ''
     * )
     */
    public function toggle()
    {
        $data = $this->request->param();
        $mod = new PromotionModel();
        $ids = $this->request->param('ids/a');

        if (isset($data['ids']) && !empty($data["display"])) {
            $mod->where('id', 'in', $ids)->update(['status' => 1]);
            $this->success("更新成功！");
        }

        if (isset($data['ids']) && !empty($data["hide"])) {
            $mod->where('id', 'in', $ids)->update(['status' => 0]);
            $this->success("更新成功！");
        }

    }

    /**
     * 删除文章分类
     * @adminMenu(
     *     'name'   => '删除文章分类',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '删除文章分类',
     *     'param'  => ''
     * )
     */
    public function delete()
    {
        $mod = new PromotionModel();
        $id = $this->request->param('id');
        //获取删除的内容
        $findCategory = $mod->where('id', $id)->find();

        if (empty($findCategory)) {
            $this->error('活动不存在!');
        }
        //判断此分类有无子分类（不算被删除的子分类）
        $categoryChildrenCount = Db::table("shop_coupon")->where('promotion_id', $id)->where('delete_time', "<>", 0)->count();

        if ($categoryChildrenCount > 0) {
            $this->error('此活动有优惠券无法删除!');
        }

        if ($findCategory['status'] != 0) {
            $this->error('仅草稿状态可以删除!');
        }

        $result = $mod
            ->where('id', $id)
            ->update(['delete_time' => time()]);
        if ($result) {
            $this->success('删除成功!');
        } else {
            $this->error('删除失败');
        }
    }

    public function department()
    {
        $url =  config('crm.app_url')."/api/open/companyStructure/addList";
        $result = curl($url);
        $result = json_decode($result, true);
        if (isset($result['data'])) {
            return $result['data'];
        }
        return [];
    }

    public function GetTree($arr, $pid=0, $step=0)
    {
        global $tree;
        foreach ($arr as $key => $val) {
            if ($val['pid'] == $pid) {
                if ($pid == 1){
                    $flg = str_repeat('&nbsp', $step);
                }else{
                    $flg = str_repeat('&nbsp', $step * 6)."└―";
                }
                $val['name'] = $flg . $val['name'];
                $tree[] = $val;
                $this->GetTree($arr, $val['id'], $step + 1);
            }
        }
        return $tree;
    }
}