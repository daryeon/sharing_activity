<?php
namespace api\shop\controller;

use api\shop\model\WxUserModel;
use api\shop\service\WxAppService;
use api\shop\controller\RestBaseController;

class WxUserController extends RestBaseController
{
    public function wxEnter() {
        if($this->request->isPost()){
            $code = $this->request->param('code', '');
            $shareId = $this->request->param('shareId', '');
            $getId = $this->request->param('getId', '');
            $sourceType = $this->request->param('sourceType', '');

            $wxApp = WxAppService::getInstance();
            $wxUserModel = new WxUserModel();
            $session = $wxApp->getSessionByCode($code);
            if(!empty($session->openid)){
                $wxUser = $wxUserModel->where('delete_time', 0)->where('openid', $session->openid)->find();
                if(!empty($wxUser)){ // 账号已存在
                    //$wxUser['api_token'] = WxUserModel::getRandString();
                    $wxUser['last_visit_time'] = time();
                    if(empty($wxUser['mobile']) && !empty($getId)){
                        $wxUser['get_id'] = $getId;
                    }
                    $wxUser->save();
                    $employee = $wxUserModel->employee($wxUser['mobile']);
                    $this->success('ok', [
                        'user_id' => $wxUser['id'],
                        'mobile' => $wxUser['mobile'],
                        'nick_name' => $wxUser['nick_name'],
                        'openid' => $wxUser['openid'],
                        'avatar' => $wxUser['avatar'],
                        'api_token' => $wxUser['api_token'],
                        'unionid' => $wxUser['unionid'],
                        'get_id' => !empty($employee) ? $wxUser['id'] : (!empty($wxUser['get_id']) ? $wxUser['get_id'] : ''),
                        'marketing_code' => !empty($employee) ? $employee['marketing_code'] : '',
                    ]);
                }else{  //  创建账号
                    $info = [];
                    $info['openid'] = $session->openid;
                    $info['unionid'] = !empty($session->unionid) ? $session->unionid : '';
                    $info['last_visit_time'] = time();
                    $info['source_type'] = !empty($sourceType) ? trim($sourceType) : '';
                    if(!empty($shareId)){
                        $info['share_id'] = $shareId;
                    }
                    if(!empty($getId)){ // 记录获客id
                        $info['get_id'] = $getId;
                        $share_user = $wxUserModel->where('delete_time', 0)->where('id', $getId)->find();
                        if (!empty($share_user)){
                            $employee = $wxUserModel->employee($share_user['mobile']);
                        }
                    }
//                    error_log(json_encode($info), 3, '/tmp/shoplogtest.log');
                    $inst = $wxUserModel->addUser($info);
//                    error_log(json_encode($inst), 3, '/tmp/shoplogtest.log');
                    $this->success('ok', [
                        'user_id' => $inst['id'],
                        'mobile' => '',
                        'nick_name' => '',
                        'openid' => $info['openid'],
                        'avatar' => '',
                        'api_token' => $inst['api_token'],
                        'unionid' => $info['unionid'],
                        'get_id' => !empty($inst['get_id']) ? $inst['get_id'] : '',
                        'marketing_code' =>  !empty($employee) ? $employee['marketing_code'] : '',
                        'source_type' => !empty($inst['source_type']) ? $inst['source_type'] : '',
                    ]);
                }
            }else{
                $this->error('登录凭证校验失败！');
            }
            $this->success('ok', $session);
        }
    }

    public function updateWxUserInfo() {
        if($this->request->isPut()){
            $code = $this->request->param('code', '');
            $encryptedData = $this->request->param('encryptedData', '');
            $iv = $this->request->param('iv', '');
    
            $wxApp = WxAppService::getInstance();
            $wxUserModel = new WxUserModel();
            $session = $wxApp->getSessionByCode($code);
            if(!empty($session->openid)){
                $wxUser = $wxUserModel->where('delete_time', 0)->where('openid', $session->openid)->find();
                if(!empty($wxUser)){
                    $wxUser['unionid'] = !empty($session->unionid) ? $session->unionid : '';
                    $wxUser['last_visit_time'] = time();
                    if(!empty($session->session_key) && !empty($encryptedData)){
                        $sessionKey = $session->session_key;
                        $data = $wxApp->wxDataCrypt($encryptedData, $sessionKey, $iv);
                        if(!empty($data->avatarUrl)){
                            $wxUser['avatar'] = !empty($data->avatarUrl) ? $data->avatarUrl : '';
                            $wxUser['unionid'] = !empty($data->unionId) ? $data->unionId : '';
                            $wxUser['nick_name'] = !empty($data->nickName) ? $data->nickName : '';
                        }
                    }
                    $wxUser->save();
                    $employee = $wxUserModel->employee($wxUser['mobile']);
                    return $this->success('ok', [
                        'user_id' => $wxUser['id'],
                        'mobile' => $wxUser['mobile'],
                        'nick_name' => $wxUser['nick_name'],
                        'openid' => $wxUser['openid'],
                        'avatar' => $wxUser['avatar'],
                        'unionid' => $wxUser['unionid'],
                        'api_token' => $wxUser['api_token'],
                        'get_id' => !empty($employee) ? $wxUser['id'] : $wxUser['get_id'],
                    ]);
                }else{
                    return $this->error('该账号已被禁用！');
                }
            }else{
                return $this->error('登录凭证校验失败！');
            }
        }
    }

    public function updateWxUserMobile() {
        if($this->request->isPut()){
            $userId = $this->getUserId();
            $get_id = $this->request->param('getId', 0, 'intval');
            $code = $this->request->param('code', '');
            $encryptedData = $this->request->param('encryptedData', '');
            $iv = $this->request->param('iv', '');
    
            $wxApp = WxAppService::getInstance();
            $wxUserModel = new WxUserModel();
            $session = $wxApp->getSessionByCode($code);
            if(!empty($session->openid)){
                $wxUser = $this->user;
                if(!empty($session->session_key) && !empty($encryptedData)){
                    $sessionKey = $session->session_key;
                    $data = $wxApp->wxDataCrypt($encryptedData, $sessionKey, $iv);
                    if(!empty($data->purePhoneNumber)){
                        $wxUser['get_id'] = $get_id;
                        $wxUser['mobile'] = !empty($data->purePhoneNumber) ? $data->purePhoneNumber : '';
                    }else{
                        return $this->error('解密失败！', '', [], 100);
                    }
                }else{
                    return $this->error('解密参数有误！', '', [], 100);
                }
                $wxUser->save();
                
                $employee = $wxUserModel->employee($wxUser['mobile']);
                // 同步crm客资
                $getUser = $wxUserModel->get($get_id);
                // 员工获客人是自己
                $getEmployeeMobile = !empty($employee) ? $employee['mobile'] : (!empty($getUser) ? $getUser['mobile'] : null);
                $data = [];
                $data['get_employee_mobile'] = $getEmployeeMobile;
                $data['union_id'] = $wxUser['unionid'];
                $data['customer_mobile'] = $wxUser['mobile'];
                $data['nick_name'] = $wxUser['nick_name'];
                \app\common\service\MQProducer::getSingle('crm')->run($data, 'shopCreate');
                
                return $this->success('ok', [
                    'user_id' => $wxUser['id'],
                    'mobile' => $wxUser['mobile'],
                    'nick_name' => $wxUser['nick_name'],
                    'openid' => $wxUser['openid'],
                    'avatar' => $wxUser['avatar'],
                    'unionid' => $wxUser['unionid'],
                    'api_token' => $wxUser['api_token'],
                    'get_id' => !empty($employee) ? $wxUser['id'] : $wxUser['get_id'],
                ]);
            }else{
                return $this->error('登录凭证校验失败！');
            }
        }
    }

    public function getWxCode() {
        $page = $this->request->param('page', '');
        $scene = $this->request->param('scene', '');
        $width = $this->request->param('width', 430, 'intval');
        $wxApp = WxAppService::getInstance();
        $img = $wxApp->getWxCodeUnlimit($page, $scene, $width);
        $documentRoot = $_SERVER['DOCUMENT_ROOT'];
        $lastChar = substr($documentRoot, -1);
        if($lastChar != '/'){
            $documentRoot = $documentRoot.'/';
        }
        $imgPath = $documentRoot.'upload/shop/code_'.time().'_'.rand(1000000, 9999999).'.jpg';
        file_put_contents($imgPath, $img);
        $data = 'data:image/jpg;base64,'.base64_encode($img);
        unlink($imgPath);
        return $this->success('ok', $data);
    }
}
