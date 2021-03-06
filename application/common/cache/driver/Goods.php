<?php

namespace app\common\cache\driver;

use app\common\cache\Cache;
use app\common\model\Goods as goodsModel;
use app\common\model\GoodsSku;
use app\purchase\service\PurchaseOrder;
use app\goods\service\GoodsImage;
use app\common\model\GoodsSkuAliasNew;
use app\common\cache\driver\AttributeValue;
use app\common\model\GoodsAttribute as GoodsAttributeModel;

/**
 * Created by PhpStorm.
 * User: PHILL
 * Date: 2016/12/7
 * Time: 17:45
 */
class Goods extends Cache
{
    private static $attributeValue = null;
    private static $goodsAttribute = null;

    /** 获取商品标签
     * @return array
     */
    public function getGoodsTag()
    {
        if ($this->redis->exists('cache:GoodsTag')) {
            $result = json_decode($this->redis->get('cache:GoodsTag'), true);
            return $result;
        }
        //查表
        $result = goodsModel::field('tags')->select();
        foreach ($result as $k => $v) {
            if (!$v['tags']) {
                continue;
            }
            $arr_tag = explode(",", $v['tags']);
            foreach ($arr_tag as $k2 => $v2) {
                $arr[] = $v2;
            }
        }
        $result = array_unique($arr);
        $this->redis->set('cache:GoodsTag', json_encode($result));
        return $result;
    }

    /**
     * 获取产品sku详情
     * @param int sku_id
     */
    public function getSkuInfo($sku_id, $width = 60, $height = 60)
    {
        if ($this->redis->hexists('cache:Sku', $sku_id)) {
            $result = json_decode($this->redis->hget('cache:Sku', $sku_id), true);
            $result['thumb'] = $result['thumb'] ? GoodsImage::getThumbPath($result['thumb'], $width, $height) : '';
            return $result;
        }

        $result = GoodsSku::field(true)->find($sku_id);
        if ($result) {
            $attrValueArr = json_decode($result['sku_attributes'], true);
            $result['name'] = '';
            foreach ($attrValueArr as $attr => $valueId) {
                $attributeId = str_replace('attr_', '', $attr);
                $value = $this->getAttributeValue($result['goods_id'], $attributeId, $valueId);
                $result['name'] .= ($result['name'] ? ' ' : '') . $value;
            }
            $result['spu_name'] = ($result['name'] ? '[' . $result['name'] . '] ' : '') . $result['spu_name'];
            $this->redis->hset('cache:Sku', $sku_id, json_encode($result->toArray()));
            $result['thumb'] = $result['thumb'] ? GoodsImage::getThumbPath($result['thumb']) : '';
        }
        return $result ? $result->toArray() : [];
    }

    /**
     *
     * @param int $goodsId
     * @param int $attributeId
     * @param int $valueId
     * @return string
     */
    private function getAttributeValue($goodsId, $attributeId, $valueId)
    {
        if (is_null(self::$attributeValue)) {
            self::$attributeValue = new AttributeValue();
        }
        if ($attributeId == 11 || $attributeId == 15) {
            $info = GoodsAttributeModel::where(['goods_id' => $goodsId, 'attribute_id' => $attributeId, 'value_id' => $valueId])->field('alias')->find();
            return $info ? $info['alias'] : '';
        } else {
            $info = self::$attributeValue->getAttributeValue($valueId);
            return $info ? $info['value'] : '';
        }
    }

    /**
     * 删除sku缓存接口
     * @param int $sku_id
     * @return boolean
     */
    public function delSkuInfo($sku_id)
    {
        $key = 'cache:Sku';
        return $this->redis->hDel($key, $sku_id);
    }

    /**
     * 获取产品详情
     * @param int goods_id 产品id
     * @return array
     */
    public function getGoodsInfo($goods_id, $width = 60, $height = 60)
    {
        if ($this->redis->hexists('cache:Goods', $goods_id)) {
            $result = json_decode($this->redis->hget('cache:Goods', $goods_id), true);
            $result['thumb'] = $result['thumb'] ? GoodsImage::getThumbPath($result['thumb']) : '';
            return $result;
        }
        $result = goodsModel::where(['id' => $goods_id])->field(true)->find();
        if ($result) {
            $this->redis->hset('cache:Goods', $goods_id, json_encode($result->toArray()));
            $result['thumb'] = $result['thumb'] ? GoodsImage::getThumbPath($result['thumb'], $width, $height) : '';
        }
        return $result ? $result->toArray() : [];
    }

    /**
     * 删除goods缓存接口
     * @param int $goods_id 产品id
     * @return boolean
     */
    public function delGoodsInfo($goods_id)
    {
        $key = 'cache:Goods';
        return $this->redis->hDel($key, $goods_id);
    }

    /**
     * 获取产品上次的采购信息
     * @author yangweiquan
     * @param int sku_id
     */
    public function getSkuLastPurchaseInfo($sku_id, $purchase_order_id = 0, $is_use_cache = true)
    {
        if (!$is_use_cache) {
            return PurchaseOrder::getSkuLastPurchaseInfo($sku_id);
        }
        if ($this->redis->hexists('cache:getSkuLastPurchaseInfo', $sku_id)) {
            $result = json_decode($this->redis->hget('cache:getSkuLastPurchaseInfo', $sku_id), true);
            return $result;
        }
        $result = PurchaseOrder::getSkuLastPurchaseInfo($sku_id, $purchase_order_id);
        if ($result) {
            $this->redis->hset('cache:getSkuLastPurchaseInfo', $sku_id, json_encode($result));
        }
        return $result ? $result : [];
    }


    /** 获取分类映射数据
     * @param int $goods_id
     * @return array|mixed
     */
    public function getGoodsCategoryMap($goods_id = 0)
    {
        if ($this->redis->exists('cache:goodsCategoryMap')) {
            $map = json_decode($this->redis->get('cache:goodsCategoryMap'), true);
            if (!empty($goods_id)) {
                return isset($map[$goods_id]) ? $map[$goods_id] : [];
            }
            return $map;
        }
        //查表
        $categoryMap = new \app\common\model\CategoryMap();
        $result = $categoryMap->select();
        $new_array = [];
        foreach ($result as $k => $v) {
            $new_array[$v['category_id']][$k] = $v->toArray();
        }
        $this->redis->set('cache:categoryMap', json_encode($new_array));
        if (!empty($category)) {
            return isset($new_array[$category]) ? $new_array[$category] : [];
        }
        return $new_array;
    }

    public function getSkuIdBySkuAndAlias($code)
    {
        $key = 'GoodsSku:sku2id';
        $data = $this->redis->hGet($key, $code);
        if (!$data) {
            $aData = GoodsSkuAliasNew::where('sku', $code)->field('sku_id')->find();
            if ($aData) {
                $this->redis->hSet($key, $code, $aData['sku_id']);
                return $aData['sku_id'];
            }
        }
        return $data;
    }

    public function createPhash($phash, $row)
    {
        $key = "goods:phash:" . $phash;
        return $this->redis->hSet($key, $row['id'], json_encode($row));
    }

    public function delCachePhash($phash, $id)
    {
        $key = "goods:phash:" . $phash;
        return $this->redis->hDel($key, $id);
    }

}
