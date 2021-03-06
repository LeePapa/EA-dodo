<?php
/**
 * Created by PhpStorm.
 * User: rondaful_user
 * Date: 2018/11/5
 * Time: 18:10
 */

namespace app\publish\queue;


use app\common\model\Channel;
use app\common\model\Goods;
use app\common\model\pandao\PandaoProduct;
use app\common\service\CommonQueuer;
use app\common\service\SwooleQueueJob;
use app\internalletter\service\InternalLetterService;
use app\publish\service\PandaoService;

class PandaoInfringeEnd extends SwooleQueueJob
{
    public static function swooleTaskMaxNumber():int
    {
        return 4;
    }

    public function getName():string
    {
        return 'Pandao商品侵权下架';
    }

    public function getDesc():string
    {
        return 'Pandao商品侵权下架';
    }

    public function getAuthor():string
    {
        return 'wlw2533';
    }

    public  function execute()
    {
//        参数格式
//        $data  = [
//            'tort_id'=>$row['tort_id'],//侵权id
//            'goods_id'=>$row['goods_id'],//商品id
//            'ban_shop_id'=>explode(',',$row['ban_shop_id']),//不用下架的店铺id
//            'notice_channel'=>$row[''],//需要通知的渠道id
//            'reason'=>$row['reason']//原因
//        ];
        $params = $this->params;
        if ($params['channel_id'] != 8 && !in_array(8,$params['notice_channel'])) {
            return false;
        }
        $wh['goods_id'] = $params['goods_id'];
        $wh['account_id'] = ['not in', $params['ban_shop_id']];
        $wh['product_id'] = ['neq',0];
        $wh['publish_status'] = 1;
        $listingItemIds = PandaoProduct::where($wh)->column('id,create_id','product_id');
        if (empty($listingItemIds)) {
            return false;
        }

        //判断是否需要下架
        if ($params['channel_id'] == 8) {//需要下架
            $itemIds = array_keys($listingItemIds);
            //先设置下架类型
            PandaoProduct::update(['end_type'=>2],['product_id'=>['in',$itemIds]]);
            //推入下架队列
            $backWriteData = [
                'goods_id' => $params['goods_id'],
                'goods_tort_id' => $params['tort_id'],
                'channel_id' => 8,
                'status' => 0,
            ];
            foreach ($listingItemIds as $itemId => $listing) {
                //写更新日志
                $log=[
                    'product_id'=>$itemId,
                    'type'=>PandaoService::TYPE['disableProduct'],
                    'create_id'=>$listing['create_id'],
                    'new_data'=>$params['tort_id'],
                    'old_data'=>'',
                    'create_time'=>time(),
                ];

                (new PandaoService())->ActionLog($log);

                $backWriteData['listing_id'] = $listing['id'];
                $backWriteData['item_id'] = $itemId;
                (new CommonQueuer(\app\goods\queue\GoodsTortListingQueue::class))->push($backWriteData);//回写
            }
        }

        $userIds = [];
        foreach ($listingItemIds as $itemId => $listing) {
            $userIds[] = $listing['create_id'];//记录创建者
        }
        $userIds = array_unique($userIds);
        $userIds = array_filter($userIds,function ($a) {
            return $a>0;
        });
        $userIds = array_values($userIds);
        if (empty($userIds)) {
            return false;
        }
        //发送钉钉消息
        $spu = Goods::where('id',$params['goods_id'])->value('spu');
        $channel = Channel::column('name','id');
        $internalLetter = [
            'receive_ids' => $userIds,
            'title' => '侵权下架',
            'content' => 'SPU:'.$spu.'因'.$params['reason'].'原因已在'.$channel[$params['channel_id']].'平台已下架，请及时处理对应平台。',
            'type' => 13,
            'dingtalk' => 1
        ];
        InternalLetterService::sendLetter($internalLetter);
    }

}