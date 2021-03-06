<?php

namespace app\index\controller;

use app\common\controller\Base;
use app\index\service\BasicAccountService;
use think\Exception;
use think\Request;
use app\common\service\Common as CommonService;

/**
 * @module 基础设置
 * @title 账号基础信息
 * @author phill
 * @url account-basics
 * Created by PhpStorm.
 * User: phill
 * Date: 2017/8/22
 * Time: 17:45
 */
class BasicAccount extends Base
{
    protected $basicAccountService;

    protected function init()
    {
        if (is_null($this->basicAccountService)) {
            $this->basicAccountService = new BasicAccountService();
        }
    }

    /**
     * @title 显示资源列表
     * @param Request $request
     * @return \think\response\Json
     * @apiRelate app\index\controller\User::staffs
     * @apiRelate app\order\controller\Order::account
     * @apiRelate app\order\controller\Order::channel
     */
    public function index(Request $request)
    {
        $page = $request->get('page', 1);
        $pageSize = $request->get('pageSize', 10);
        $params = $request->param();
        $where = [];
        if (isset($params['status']) && $params['status'] != '') {
            $where['a.status'] = ['eq', $params['status']];
        }
        if (isset($params['channel_id']) && !empty($params['channel_id'])) {
            $where['a.channel_id'] = ['eq', $params['channel_id']];
        }
        if (isset($params['site_code']) && !empty($params['site_code'])) {
            $where['a.site_code'] = ['like', '%' . $params['site_code'] . '%'];
        }
        if (isset($params['server_name']) && !empty($params['server_name'])) {
            $where['s.name'] = ['like', '%' . $params['server_name'] . '%'];
        }
        if (isset($params['creator_id']) && !empty($params['creator_id'])) {
            $where['a.account_creator'] = ['eq', $params['creator_id']];
        }
        if (isset($params['company_id']) && $params['company_id'] != '') {
            $where['a.company_id'] = ['eq', $params['company_id']];
        }
        if (isset($params['snType']) && isset($params['snText']) && !empty($params['snText'])) {
            switch ($params['snType']) {
                case 'name':
                    $where['a.account_name'] = ['like', '%' . $params['snText'] . '%'];
                    break;
                case 'code':
                    $where['a.account_code'] = ['like', '%' . $params['snText'] . '%'];
                    break;
                case 'shop_name':
                    $where['a.shop_name'] = ['like', $params['snText'] . '%'];
                    break;
                default:
                    break;
            }
        }
        if (isset($params['s_type']) && isset($params['s_value']) && !empty($params['s_value'])) {
            switch ($params['s_type']) {
                case '1':
                    $where['a.phone'] = ['like', '%' . $params['s_value'] . '%'];
                    break;
                case '2':
                    $where['a.email'] = ['like', '%' . $params['s_value'] . '%'];
                    break;
                default:
                    break;
            }
        }
        if (isset($params['snDate'])) {
            $params['date_b'] = isset($params['date_b']) ? $params['date_b'] : 0;
            $params['date_e'] = isset($params['date_e']) ? $params['date_e'] : 0;
            switch ($params['snDate']) {
                case 'create_time':
                    $condition = timeCondition($params['date_b'], $params['date_e']);
                    if (!is_array($condition)) {
                        return json(['message' => '日期格式错误'], 400);
                    }
                    if (!empty($condition)) {
                        $where['a.account_create_time'] = $condition;
                    }
                    break;
                case 'transfer_time':
                    $condition = timeCondition($params['date_b'], $params['date_e']);
                    if (!is_array($condition)) {
                        return json(['message' => '日期格式错误'], 400);
                    }
                    if (!empty($condition)) {
                        $where['a.fulfill_time'] = $condition;
                    }
                    break;
                default:
                    break;
            }
        }
        $orderBy = fieldSort($params);
        $orderBy .= 'a.account_create_time desc';
        $accountList = $this->basicAccountService->accountList($where, $page, $pageSize, $orderBy);
        return json($accountList);
    }

    /**
     * @title 保存新建的资源
     * @param  \think\Request $request
     * @return \think\Response
     * @apiRelate app\index\controller\User::staffs
     */
    public function save(Request $request)
    {
        $params = $request->post();
        $data = $params;
        $validateAccount = validate('Account');
        if (!$validateAccount->check($data)) {
            return json(['message' => $validateAccount->getError()], 400);
        }
        //获取操作人信息
        $user = CommonService::getUserInfo($request);
        if (!empty($user)) {
            $data['creator_id'] = $user['user_id'];
        }
        try {
            $account_id = $this->basicAccountService->save($data);
            $result = $this->basicAccountService->accountList(['a.id' => $account_id])['data'];
            return json(['message' => '新增成功', 'data' => $result]);
        } catch (Exception $ex) {
            $err = [
                'message' => $ex->getMessage(),
                'file' => $ex->getFile(),
                'line' => $ex->getLine()
            ];
            return json($err,400);
        }

    }

    /**
     * @title 显示指定的资源
     * @param  int $id
     * @return \think\Response
     */
    public function read($id)
    {
        $result = $this->basicAccountService->read($id);
        return json($result, 200);
    }

    /**
     * @title 显示编辑资源表单页.
     * @param  int $id
     * @return \think\Response
     */
    public function edit($id)
    {
        $result = $this->basicAccountService->read($id);
        return json($result, 200);
    }

    /**
     * @title 保存更新的资源
     * @param  \think\Request $request
     * @param  int $id
     * @return \think\Response
     * @apiRelate app\index\controller\User::staffs
     */
    public function update(Request $request, $id)
    {
        $params = $request->param();
        $data = $params;
        //获取操作人信息
        $user = CommonService::getUserInfo($request);
        if (!empty($user)) {
            $data['updater_id'] = $user['user_id'];
        }

        if ($data['account_name'] && !$data['password']) {
            json(['message' => '主账号密码不能为空'], 500);
        }

        if ($data['account_name_minor'] && !$data['password_minor']) {
            json(['message' => '主账号密码不能为空'], 500);
        }
        try{
            $this->basicAccountService->update($id, $data);
            $result = $this->basicAccountService->accountList(['a.id' => $id])['data'];
            return json(['message' => '更改成功', 'data' => $result]);
        }catch (Exception $ex){
            $err = [
                'message' => $ex->getMessage(),
                'file' => $ex->getFile(),
                'line' => $ex->getLine()
            ];
            return json($err,400);
        }

    }

    /**
     * @title 获取状态列表信息
     * @url status/:info
     * @return \think\response\Json
     */
    public function info()
    {
        $result = $this->basicAccountService->statusInfo();
        return json($result);
    }

    /**
     * @title 更改账号状态
     * @url batch/:type
     * @method post
     * @param Request $request
     * @return \think\response\Json
     */
    public function batch(Request $request)
    {
        $ids = $request->post('ids', '');
        $status = $request->post('status', -1);
        if (empty($ids) || $status < 0) {
            return json(['message' => '参数值不能为空']);
        }
        $params = $request->param();
        $type = $params['type'];
        $data['status'] = $status;
        //获取操作人信息
        $user = CommonService::getUserInfo($request);
        if (!empty($user)) {
            $data['updater_id'] = $user['user_id'];
        }
        $ids = json_decode($ids, true);
        $result = $this->basicAccountService->status($ids, $data, $type);
        return json(['message' => '更改成功', 'data' => $result]);
    }

    /**
     * @title 显示密码
     * @url password
     * @param  \think\Request $request
     * @return \think\Response
     */
    public function show(Request $request)
    {
        $account_id = $request->get('account_id', 0);
        $password = $request->get('password', 0);
        $type = $request->get('type', 0);
        if (empty($account_id) || empty($password) || empty($type)) {
            return json(['message' => '参数值不能为空'], 400);
        }
        $enablePassword = $this->basicAccountService->viewPassword($password, $account_id, $type);
        return json(['password' => $enablePassword]);
    }

    /**
     * @title 服务器已绑定的账号列表
     * @url already-bind
     * @param Request $request
     * @return \think\response\Json
     */
    public function alreadyBind(Request $request)
    {
        $channel_id = $request->get('channel_id', 0);
        $server_id = $request->get('server_id', 0);
        if (empty($channel_id) || empty($server_id)) {
            return json(['message' => '参数不正确']);
        }
        $result = (new BasicAccountService())->alreadyBind($channel_id, $server_id);
        return json($result);
    }

    /**
     * @title 自动识别图片
     * @url automatic
     * @param Request $request
     * @return \think\response\Json
     */
    public function automatic(Request $request)
    {
        $type = $request->get('type', 0); //类型 1为身份证，2为营业执照
        $image = $request->get('image', 0);
        if (empty($channel_id) || empty($server_id)) {
            return json(['message' => '参数不正确']);
        }
        $result = (new BasicAccountService())->automatic($type, $image, $request);
        return json($result);
    }

    /**
     * @title 资料日志
     * @url :account_id/log
     * @method get
     * @param $account_id
     * @return \think\response\Json
     */
    public function log($account_id)
    {
        $log = (new BasicAccountService())->getLog($account_id);
        return json($log);
    }

    /**
     * @title 读取运营负责人
     * @url user
     * @method get
     * @param Request $request
     * @return \think\response\Json
     */
    public function getUser(Request $request)
    {
        $user = new User();
        return $user->index($request);
    }

    /**
     * @title 获取状态列表信息
     * @url changes
     * @method get
     * @param Request $request
     * @return \think\response\Json
     */
    public function changes()
    {
        $result = $this->basicAccountService->statusInfo();
        return json($result);
    }

    /**
     * @title 资料旧手机日志
     * @url :account_id/phone-log
     * @method get
     * @param $account_id
     * @return \think\response\Json
     */
    public function phoneLog($account_id)
    {
        $log = (new BasicAccountService())->getPhoneLog($account_id);
        return json(['data' => $log]);
    }

}