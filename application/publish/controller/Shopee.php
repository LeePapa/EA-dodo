<?php
/**
 * Created by PhpStorm.
 * User: joy
 * Date: 18-5-28
 * Time: 下午5:40
 */

namespace app\publish\controller;


use app\common\cache\Cache;
use app\common\controller\Base;
use app\common\exception\JsonErrorException;
use app\publish\service\ExpressHelper;
use app\publish\service\ShopeeService;
use app\publish\service\WishHelper;
use think\Exception;
use think\Request;
use app\common\service\Common;
/**
 * @module 刊登系统
 * @title Shopee刊登控制器
 * Class Shopee
 * packing app\publish\controller
 */
class Shopee extends Base
{

    private $service;
    private $channel_id=9;
    private $uid;
    private $userId;
    public function __construct(Request $request)
    {
        parent::init(); // TODO: Change the autogenerated stub
        $userId = Common::getUserInfo($request) ? Common::getUserInfo($request)['user_id'] : 0;
        $this->uid = $userId;
        $this->userId = $userId;
        $this->service = new ShopeeService($userId);
    }
    /**
     * @title shopee批量修改
     * @url /shopee-batch-setting
     * @access public
     * @method post
     * @param array $request
     * @output think\Response
     */
    public function batchSetting(Request $request){
        $field = $request->param('field','');
        $data  = $request->param('data','');

        $data = json_decode($data,true);
        if(empty($field) || empty($data)){
            return json(['message'=>'批量修改的字段和数据都不能为空'],400);
        }

        $data['uid'] = $this->uid;
        $response = $this->service->batchSetting($field,$data);
        if($response){
            return json(['message'=>$response],400);
        }else{
            return json(['message'=>'更新成功']);
        }

    }
    /**
     * @title shopee编辑折扣折扣
     * @url /shopee-discount-edit
     * @access public
     * @method GET
     * @param array $request
     * @output think\Response
     */
    public function editDiscount(Request $request)
    {
        $discount_id = $request->param('discount_id');
        $response = $this->service->getDiscountDetail($discount_id);
        return json($response);
    }
    /**
     * @title shopee添加折扣
     * @url /shopee-discount-add
     * @access public
     * @method post
     * @param array $request
     * @output think\Response
     */
    public function addDiscount(Request $request)
    {
        $params = $request->param();
        $response = $this->service->addDiscount($params);
        return json($response);
    }
    /**
     * @title shopee折扣列表
     * @url /shopee-discount
     * @access public
     * @method get
     * @param array $request
     * @output think\Response
     */
    public function discount(Request $request)
    {
        $page = $request->param('page',1);
        $pageSize = $request->param('pageSize',50);
        $params = $request->param();
        $response = $this->service->getAllDiscount($params,$page,$pageSize);
        return json($response);
    }
    /**
     * @title shopee刊登记录提交刊登
     * @url /publish/shopee/push-queue
     * @access public
     * @method post
     * @param array $request
     * @output think\Response
     */
    public function pushQueue(Request $request){
        try{
            $ids = $request->param('id',0);
            if(empty($ids)){
                throw new JsonErrorException("请选择你要提交刊登的数据");
            }
            $response = $this->service->pushQueue($ids);
            return json($response);
        }catch (Exception $exp){
            throw new JsonErrorException($exp->getMessage());
        }
    }
    /**
     * @title shopee操作日志
     * @url /publish/shopee/logs
     * @access public
     * @method GET
     * @param array $request
     * @output think\Response
     */
    public function logs(Request $request){
        try {

            $page = $request->get('page', 1);
            $pageSize = $request->get('pageSize', 50);

            //搜索条件
            $param = $request->param();

            if(!isset($param['product_id']))
            {
                return json(['message'=>'缺少参数product_id'],400);
            }
            $fields = "*";
            $response = (new shopeeService())->getLogs($param, $page, $pageSize, $fields);
            return  json($response);
        }catch(Exception $exp){
            throw new JsonErrorException("File:{$exp->getFile()};Line:{$exp->getLine()};Message:{$exp->getMessage()}");
        }
    }
    /**
     * @title shopee同步listing
     * @url /publish/shopee/rsync-product
     * @access public
     * @method POST
     * @param array $request
     * @output think\Response
     */

    public function rsyncProduct(Request $request){
        $ids = $request->param('ids');
        if(empty($ids)){
            throw new JsonErrorException("请选择你要同步的产品");
        }
        $uid= Common::getUserInfo($request) ? Common::getUserInfo($request)['user_id'] : 0;
        $response = (new shopeeService())->batchAction($ids,$uid,'rsyncProduct','同步listing');
        return json($response);
    }
    /**
     * @title shopee批量下架
     * @url /shopee/del-item/batch
     * @access public
     * @method PUT
     * @param array $request
     * @output think\Response
     */
    public function delItem(Request $request){
        try {
            $data = json_decode($request->param('data'), true);
            if (empty($data)) {
                throw new Exception('传递的数据格式有误');
            }
            $this->service->delItem($data);
            return json(['result'=>true, 'message'=>'已加入队列，稍后自动执行'], 200);
        } catch (Exception $e) {
            return json(['result'=>false, 'message'=>$e->getMessage()], 500);
        }
    }

    /**
     * @title shopee批量上架
     * @url /publish/shopee/batch-enable
     * @access public
     * @method POST
     * @param array $request
     * @output think\Response
     */

    public function batchEnable(Request $request){
        $ids = $request->param('ids');
        if(empty($ids)){
            throw new JsonErrorException("请选择你要上架的产品");
        }
        $uid= Common::getUserInfo($request) ? Common::getUserInfo($request)['user_id'] : 0;
        $response = (new shopeeService())->batchAction($ids,$uid,'enableProduct','商品上架');
        return json($response);
    }

    /**
     * @title shopee删除刊登记录
     * @url /publish/shopee/delete
     * @access public
     * @method delete
     * @param array $request
     * @output think\Response
     */
    public function delete(Request $request)
    {
        try{
            $id = $request->param('ids','');
            if(empty($id)){
                throw new JsonErrorException("商品id不能为空");
            }
            $count = $this->service->delete($id);
            return json(['result'=>true, 'message'=>'成功删除'.$count.'条，自动过滤了不允许删除的'], 200);
        }catch (Exception $exp){
            throw new JsonErrorException($exp->getMessage());
        }
    }

    /**
     * @title shopee编辑修改获取数据
     * @url /shopee/:id(\d+)/:status(\w+)
     * @method get
     * @author joy
     * @access public
     * @return json
     */
    public function edit($id, $status)
    {
        try{
            if(empty($id))
            {
                return json(['message'=>'id不正确'],400);
            }
            $response = (new shopeeService())->getProductAndVariant($id, $status);
            return json($response);
        }catch (Exception $exp){
            throw new JsonErrorException("File:{$exp->getFile()};Line{$exp->getLine()};Message:{$exp->getMessage()}");
        }
    }

    /**
     * @title shopee更新修改了的数据
     * @url /publish/shopee/update
     * @method post
     * @author joy
     * @access public
     * @return json
     */
    public function update(Request $request)
    {
        try{
            //获取post过来的数据
            $post =$request->param();
            $this->service->updateProductAndVariant($post);
            return json(['result'=>true, 'message'=>'更新成功'], 200);
        }catch (Exception $exp){
            throw new JsonErrorException("File:{$exp->getFile()};Line:{$exp->getLine()};Message:{$exp->getMessage()}");
        }
    }

    /**
     * @title shopee获取商品数据
     * @url /publish/shopee/getdata
     * @method get
     * @author joy
     * @access public
     * @return json
     */
    public function getData(Request $request)
    {
        try{
            $goods_id = $request->param('goods_id',0);
            if(empty($goods_id))
            {
                return json(['message'=>'商品id不正确'],400);
            }

            $accountId = $request->param('account_id','');
            $response = $this->service->getGoodsData($goods_id, $accountId);
            return json($response);
        }catch (Exception $exp){
            throw new JsonErrorException("File:{$exp->getFile()};Line{$exp->getLine()};Message:{$exp->getMessage()}");
        }
    }

    /**
     * @title shopee新增刊登
     * @url /publish/shopee/add
     * @access public
     * @method post
     * @param array $request
     * @output think\Response
     */
    public function add(Request $request){
        try{
            $post = $request->param();
            $response = $this->service->create($post);
            if(is_numeric($response))
            {
                return json(['message'=>'成功提交['.$response.']条']);
            }else{
                return json(['message'=>$response],400);
            }

        }catch (Exception $exp){
            throw new JsonErrorException("File:{$exp->getFile()};Line{$exp->getLine()};Message:{$exp->getMessage()}");
        }
    }
    /**
     * @title shopee销售人员列表
     * @url /shopee-sellers
     * @access public
     * @method GET
     * @param array $request
     * @output think\Response
     */

    public function sellers(Request $request){
        try{
            $response = (new WishHelper())->getWishUsers($this->channel_id);
            return json($response);
        }catch (Exception $exp){
            throw new JsonErrorException($exp->getMessage());
        }
    }
    /**
     * @title shopee当前登录用户管理账号
     * @access public
     * @method GET
     * @url /publish/shopee/accounts
     * @return []
     */

    public function getAccounts(Request $request)
    {
        $spu = $request->param('spu','');
        $userInfo = Common::getUserInfo();
        $response = (new ExpressHelper())->getAccounts($userInfo['user_id'],$spu,$this->channel_id);
        return json($response);
    }
    /**
     * @title shopee在售listing
     * @url /shopee-on-selling
     * @access public
     * @method GET
     * @param array $request
     * @output think\Response
     */

    public function index(Request $request){
        try {
            $param = $request->param();
//            $page = $request->get('page', 1);
//            $pageSize = $request->get('pageSize', 50);
            $helper = new shopeeService();
//            $fields = "DISTINCT(p.id),p.*";
//            $param['pubish_status'] = 3;
//            unset($param['status']);
            $data = $helper->lists($param);
            return json($data);
        } catch (Exception $exp) {
            throw new JsonErrorException("File:{$exp->getFile()};Line:{$exp->getLine()};Message:{$exp->getMessage()}");
        }
    }
    /**
     * @title shopee停售listing
     * @url /shopee-stop-selling
     * @access public
     * @method GET
     * @param array $request
     * @output think\Response
     */

    public function stopSelling(Request $request){
        try {
            $param = $request->param();
            $page = $request->get('page', 1);
            $pageSize = $request->get('pageSize', 50);
            $helper = new shopeeService();
            $fields = "DISTINCT(p.id),p.*";
            $data = $helper->lists($param, $page, $pageSize, $fields);
            return json($data);
        } catch (Exception $exp) {
            throw new JsonErrorException("File:{$exp->getFile()};Line:{$exp->getMessage()};Message:{$exp->getMessage()}");
        }
    }
    /**
     * @title shopee停售listing
     * @url /shopee-sold-out
     * @access public
     * @method GET
     * @param array $request
     * @output think\Response
     */

    public function soldOut(Request $request){
        try {
            $param = $request->param();
            $page = $request->get('page', 1);
            $pageSize = $request->get('pageSize', 50);
            $helper = new shopeeService();
            $fields = "DISTINCT(p.id),p.*";
            $data = $helper->lists($param, $page, $pageSize, $fields);
            return json($data);
        } catch (Exception $exp) {
            throw new JsonErrorException("File:{$exp->getFile()};Line:{$exp->getMessage()};Message:{$exp->getMessage()}");
        }
    }
    /**
     * @title shopee刊登记录
     * @url /shopee-publish-record
     * @access public
     * @method GET
     * @param array $request
     * @output think\Response
     */

    public function records(Request $request){
        try {
            $param = $request->param();
            $page = $request->get('page', 1);
            $pageSize = $request->get('pageSize', 50);
            $helper = new shopeeService();
            $fields = "DISTINCT(p.id),p.*";
            $data = $helper->lists($param, $page, $pageSize, $fields);
            $data['base_url'] = Cache::store('configParams')->getConfig('innerPicUrl')['value'].DS;
            return json($data);
        } catch (Exception $exp) {
            throw new JsonErrorException("File:{$exp->getFile()};Line:{$exp->getMessage()};Message:{$exp->getMessage()}");
        }
    }

    /**
     * @title shopee待刊登商品列表
     * @url /publish/shopee/wait-upload
     * @access public
     * @method GET
     * @param array $request
     * @output think\Response
     */

    public function waitUpload(Request $request){
        try {
            $page = $request->get('page', 1);
            $pageSize = $request->get('pageSize', 50);
            $helper = new WishHelper();
            //搜索条件
            $param = $request->param();
            $fields = "*";
            $param['channel']=$this->channel_id;
            $data = $helper->waitPublishGoodsMap($param, $page, $pageSize, $fields);
            $data['base_url'] = Cache::store('configParams')->getConfig('innerPicUrl')['value'].DS;
            return json($data);
        } catch (Exception $exp) {
            throw new JsonErrorException($exp->getMessage());
        }
    }

    /**
     * @title shopee分类
     * @url /publish/shopee/category
     * @access public
     * @method GET
     * @param array $request
     * @output think\Response
     */
    public function category(Request $request){
        try{
            $site = $request->param('site',6);
            $category_id = $request->param('category_id',0);
            $category_name = $request->param('category_name','');
            $page = $request->param('page',1);
            $pageSize = $request->param('pageSize',30);
            $response = $this->service->category($category_id,$category_name,$site,$page,$pageSize);
            return json($response);
        }catch (Exception $exp){
            throw new JsonErrorException("{$exp->getFile()};{$exp->getLine()};{$exp->getMessage()}");
        }
    }
    /**
     * @title shopee分类属性
     * @url /publish/shopee/attribute
     * @access public
     * @method GET
     * @param array $request
     * @output think\Response
     */
    public function attribute(Request $request){
        $category_id = $request->param('category_id',0);
        if(empty($category_id)){
            return json(['message'=>'请选择分类，分类id值非法'],400);
        }
        $response = $this->service->attribute($category_id);
        return json($response);
    }
    /**
     * @title shopee物流信息
     * @url /publish/shopee/logistics
     * @access public
     * @method GET
     * @param array $request
     * @output think\Response
     */
    public function logistics(Request $request){
        $account_id = $request->param('account_id',0);
        if(empty($account_id)){
            return json(['message'=>'账号id值非法'],400);
        }
        $response = $this->service->logistics($account_id);
        return json($response);
    }

    /**
     * @title 同步账号物流设置
     * @url shopee/:account_id(\d+)/sync-logistics
     * @access public
     * @method PUT
     * @param $account_id
     * @return string
     */
    public function syncAccountLogistics($account_id)
    {
        try {
            if (empty($account_id)) {
                throw new Exception('账号id不能为空');
            }
            $this->service->syncAccountLogistics($account_id);
            return json(['result'=>true, 'message'=>'同步成功'], 200);
        } catch (Exception $e) {
            return json(['result'=>false, 'message'=>$e->getMessage()], 500);
        }
    }
}