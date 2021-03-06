<?php

/**
 * Description of Common
 * @datetime 2017-6-14  17:23:19
 * @author joy
 */

namespace app\publish\controller;
use app\common\controller\Base;
use app\common\model\aliexpress\AliexpressApiException;
use app\common\model\Warehouse;
use app\publish\service\CommonService;
use app\publish\service\GoodsImage as GoodsImageService;
use org\Curl;
use QL\QueryList;
use think\Request;
use app\common\cache\Cache;
/**
 * @title 刊登公用控制器
 * @module 刊登系统
 */
class Common extends Base{
    private $service;
    protected function init()
    {
        $this->service = new \app\publish\service\CommonService();
    }

    /**
     * @title 生成sku
     * @url /create-sku-code
     * @method post
     * @return string
     */
    public  function createSku()
    {
//        $sku = $this->request->param('sku');
//        if(empty($sku))
//        {
//            return json(['message'=>'sku不能为空']);
//        }
//        
//        $len = $this->request->param('length',20);  //长度
//        
//        $separator = $this->request->param('separator','|');  //连接符
//        
//        $charlist = $this->request->param('charlist','0-9');//字符集
        
        $sku_code  = $this->service->create_random_sku_code();
        
        if($sku_code)
        {
            return json(['data'=>$sku_code]);
        }else{
            return json(['data'=>''],500);
        }
        
    }
    /**
     * @title 生成捆绑sku
     * @url /create-bind-sku
     * @method post 
     * @param string $sku_code sku_code='NAXX00013-WT-S*3|MYNV0092-WTS*4';
     * @return string
     */
    public  function createBindSku()
    {
        $str = $this->request->param('sku_code');
        if(empty($str))
        {
            return json(['message'=>'捆绑商品组合sku必填'],500);
        }
        
        $data= $this->service->create_sku_code_with_quantity($str);
        return json(['data'=>$data]);
    }

    /**
     * @title 上传网络图片
     * @url /upload-net-images
     * @param Request $request
     * @method post
     * @author joy
     * @return array
     */
    public function uploadNetImage(Request $request)
    {
        $post = $request->instance()->param();

        $images = explode('|', $post['images']);

        if (empty($images)) {
            return json(['message' => '缺少图片链接地址'], 400);
        }
        if (empty($images)) {
            return json(['message' => '缺少图片详情'], 400);
        }
        try {

            $data = (new CommonService())->saveNetImages($images);
            return json([
                'message' => '上传成功',
                'data' => $data,
                'base_url'=>CommonService::getUploadPath()
            ], 200);
        } catch (Exception $ex) {
            return json(['message' => '上传失败' . $ex->getMessage()], 400);
        }


    }

    /**
     * @title 上传本地图片
     * @url /upload-local-images
     * @param Request $request
     * @method post
     * @author joy
     * @return array
     */
    public function uploadLocalImages(Request $request)
    {
        $images = json_decode($request->param('images'), true);

        if (empty($images))
        {
            return json(['message' => '缺少图片详情'], 400);
        }
        try {

            $data = (new CommonService())->saveLocalImages($images);
            return json([
                'message' => '上传成功',
                'data' => $data,
                'base_url'=>CommonService::getUploadPath()
            ], 200);
        } catch (Exception $ex) {
            return json(['message' => '上传失败' . $ex->getMessage()], 400);
        }
    }
    public function test(){
        $url="https://developers.aliexpress.com/handler/document/getDocument.json?docType=2&docId=30168&_tb_token_=";
        $json = Curl::curlGet($url);
        $array = json_decode($json,true);
        $errors = $array['data']['errorCodes'];
        foreach ($errors as $error){
            $data=[
                'code'=>(int)$error['errorCode'],
                'description'=>$error['errorMsg'],
                'solution'=>$error['solution'],
            ];
            $model = new AliexpressApiException();
            $where['code']=['=',$data['code']];
            if($has = $model->where($where)->find()){
                $model->allowField(true)->save($data,$where);
            }else{
                $model->allowField(true)->save($data);
            }
            dump($model->code);
        }
    }

    /**
     * @title 获取本地仓库列表
     * @url /local-warehouse
     * @method get
     */
    public function getLocalWareHouse()
    {
        try {
            $field = 'id,name,code,type';
            $wh['type'] = 1;
            $warehouse = Warehouse::field($field)->where($wh)->select();
            return json(['result'=>true, 'data'=>$warehouse], 200);
        } catch (Exception $e) {
            return json(['result'=>false,'message'=>$e->getMessage()], 500);
        }
    }

}
