<?php
namespace app\index\controller;

use app\common\cache\Cache;
use app\common\service\ChannelAccountConst;
use app\index\service\WishAccountHealthService;
use think\Db;
use think\Exception;
use think\Request;
use app\common\controller\Base;
use app\common\service\Common as CommonService;
use app\index\service\WishAccountService;
use app\common\model\wish\WishAccount as WishAccountModel;

/**
 * @module 账号管理
 * @title wish账号管理
 * @author phill
 * @url /wish-account
 * Class Wish
 * @package app\goods\controller
 */
class WishAccount extends Base
{
    protected $wishAccountService;

    protected function init()
    {
        if (is_null($this->wishAccountService)) {
            $this->wishAccountService = new WishAccountService();
        }
    }

    /**
     * @title 显示资源列表
     * @return \think\Response
     * @apiRelate app\index\controller\MemberShip::memberInfo
     * @apiRelate app\index\controller\MemberShip::save
     * @apiRelate app\index\controller\MemberShip::update
     * @apiRelate app\index\controller\User::staffs
     */
    public function index()
    {
        $request = Request::instance();
        if (isset($request->header()['x-result-fields'])) {
            $field = $request->header()['x-result-fields'];
            $field = explode(',', $field);
        } else {
            $field = [];
        }
        $params = $request->param();
        $page = $request->get('page', 1);
        $pageSize = $request->get('pageSize', 10);
        $result = $this->wishAccountService->accountList($params, $field, $page, $pageSize);
        return json($result, 200);
    }

    /**
     * @title 保存新建的资源
     * @param  \think\Request $request
     * @return \think\Response
     */
    public function save(Request $request)
    {
        $params = $request->param();
        $data = $params;
        $validateAccount = validate('WishAccount');
        if (!$validateAccount->check($data)) {
            return json(['message' => $validateAccount->getError()], 400);
        }
        //获取操作人信息
        $user = CommonService::getUserInfo($request);
        if (!empty($user)) {
            $data['creator_id'] = $user['user_id'];
        }
        \app\index\service\BasicAccountService::isHasCode(ChannelAccountConst::channel_wish,$data['code']);
        $id = $this->wishAccountService->save($data);
        $result = $this->wishAccountService->accountList(['id' => $id]);
        return json(['message' => '新增成功', 'data' => $result['data']]);
    }

    /**
     * @title 显示指定的资源
     * @param  int $id
     * @return \think\Response
     */
    public function read($id)
    {
        $result = $this->wishAccountService->read($id);
        return json($result, 200);
    }

    /**
     * @title 显示编辑资源表单页.
     * @param  int $id
     * @return \think\Response
     */
    public function edit($id)
    {
        $result = $result = $this->wishAccountService->read($id);
        return json($result, 200);
    }

    /**
     * @title 保存更新的资源
     * @param  \think\Request $request
     * @param  int $id
     * @return \think\Response
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
        $this->wishAccountService->update($id, $data);
        $result = $this->wishAccountService->accountList(['id' => $id]);
        return json(['message' => '更改成功','data' => $result['data']]);
    }

    /**
     * @title 停用，启用账号
     * @url states
     * @method post
     */
    public function changeStates()
    {
        $request = Request::instance();
        $id = $request->post('id', 0);
        $state = $request->post('is_invalid', 0);
        $data['is_invalid'] = $state == 'true' ? 1 : 0;
        //获取操作人信息
        $user = CommonService::getUserInfo($request);
        if (!empty($user)) {
            $data['updater_d'] = $user['user_id'];
        }
        $this->wishAccountService->status($id, $data);
        return json(['message' => '操作成功']);
    }

    /**
     * @title 获取授权码
     * @url authorCode
     * @method post
     */
    public function authorCode()
    {
        $request = Request::instance();
        $client_id = $request->post('client_id', 0);
        if (empty($client_id)) {
            return json(['message' => '应用ID不能为空'], 400);
        }
        $url = 'https://merchant.wish.com/oauth/authorize?client_id=' . $client_id;
        return json(['url' => $url], 200);
    }

    /**
     * @title 查询wish账号
     * @noauth
     * @url query
     * @method get
     */
    public function query(Request $request, WishAccountService $service)
    {
        $param = $request->param();
        $keyword = param($param, 'keyword');
        $page = param($param, 'page', 1);
        $pageSize = param($param, 'pageSize', 20);
        $json = $service->query($keyword, $page, $pageSize);
        return json($json);
    }

    /**
     * @title 获取Token
     * @url token
     * @method post
     * @return \think\response\Json
     * @throws Exception
     */
    public function token()
    {
        set_time_limit(0);
        $request = Request::instance();
        $id = $request->post('id', 0);
        if (empty($id)) {
            return json(['message' => '参数错误'], 400);
        }
        $data['client_id'] = $request->post('client_id', 0);
        $data['client_secret'] = $request->post('client_secret', 0);
        $data['authorization_code'] = $request->post('authorization_code', 0);
        $data['redirect_uri'] = $request->post('redirect_url', 0);
        if (empty($data['client_id']) || empty($data['client_secret']) || empty($data['authorization_code'])) {
            return json(['message' => '参数信息错误'], 400);
        }
        //获取操作人信息
        $user = CommonService::getUserInfo($request);
        if (!empty($user)) {
            $data['updater_id'] = $user['user_id'];
        }
        $date = $this->wishAccountService->getToken($id, $data);
        return json(['message' => '获取成功', 'date' => $date]);
    }


    /**
     * @title 获取Token
     * @url refresh_token/:id
     * @method GET
     * @return \think\response\Json
     * @throws Exception
     */
    public function refresh_token($id = 0)
    {
        if ($id == 0) {
            return json_error('缺少参数ID');
        }
        return $this->wishAccountService->refresh_token($id);
    }

    /**
     * @title 授权页面
     * @url authorization
     * @method post
     * @return \think\response\Json
     */
    public function authorization()
    {
        $request = Request::instance();
        $id = $request->post('id', 0);
        if (empty($id)) {
            return json(['message' => '参数错误'], 400);
        }
        $result = $this->wishAccountService->authorization($id);
        return json($result, 200);
    }

    /**
     * @title wish 批量开启
     * @url batch-set
     * @method post
     * @param Request $request
     * @return \think\response\Json
     * @throws Exception
     */
    public function batchSet(Request $request)
    {
        $params = $request->post();
        $result = $this->validate($params, [
            'ids|帐号ID' => 'require|min:1',
            'is_invalid|系统状态' => 'require|number',
            'download_order|抓取wish订单功能' => 'require|number',
            'sync_delivery|同步发货状态到wish功能' => 'require|number',
            'download_listing|抓取Wish Listing数据' => 'require|number',
            'download_health|同步健康数据' => 'require|number',
        ]);
        if ($result != true) {
            throw new Exception($result);
        }
        $model = new WishAccountModel();

        if (isset($params['is_invalid']) && $params['is_invalid'] != '') {
            $data['is_invalid'] = (int)$params['is_invalid'];   //0-停用 1-启用
        }
        if (isset($params['download_order']) && $params['download_order'] != '') {
            $data['download_order'] = (int)$params['download_order'];
        }
        if (isset($params['sync_delivery']) && $params['sync_delivery'] != '') {
            $data['sync_delivery'] = (int)$params['sync_delivery'];
        }
        if (isset($params['download_listing']) && $params['download_listing'] != '') {
            $data['download_listing'] = (int)$params['download_listing'];
        }
        if (isset($params['download_health']) && $params['download_health'] != '') {
            $data['download_health'] = (int)$params['download_health'];
        }

        $idArr = array_merge(array_filter(array_unique(explode(',', $params['ids']))));
        //开启事务
        Db::startTrans();
        try {
            if (empty($data)) {
                return json(['message' => '数据参数不能为空'], 200);
            }
            $data['update_time'] = time();
            $model->allowField(true)->update($data, ['id' => ['in', $idArr]]);
            Db::commit();

            //更新缓存
            $cache = Cache::store('WishAccount');
            $healthServ = new WishAccountHealthService();
            foreach ($idArr as $id) {
                //开通wish服务时，新增一条list数据，如果存在，则不加
                if (isset($data['download_health'])) {
                    $healthServ->openWishHealth($id, (int)$data['download_health']);
                }
                foreach ($data as $k => $v) {
                    $cache->updateTableRecord($id, $k, $v);
                }
            }
            return json(['message' => '更新成功'], 200);
        } catch(Exception $ex) {
            Db::rollback();
            return json(['message' => '更新失败'], 400);
        }
    }
}
