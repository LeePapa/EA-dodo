<?php

namespace app\report\service;

use app\common\cache\Cache;
use app\common\model\FbaOrder;
use app\common\model\Order;
use app\common\model\OrderDetail;
use app\common\model\OrderPackage;
use app\common\model\report\ReportStatisticByDeeps;
use app\common\service\Report;
use app\common\service\UniqueQueuer;
use app\report\queue\WriteBackFbaOrderDeeps;
use app\report\queue\WriteBackOrderDeeps;
use app\warehouse\service\Warehouse;
use think\Db;
use think\Exception;

/** 销售额统计
 * Created by PhpStorm.
 * User: phill
 * Date: 2017/8/1
 * Time: 19:16
 */
class StatisticDeeps
{
    protected $reportStatisticByDeepsModel = null;

    public function __construct()
    {
        if (is_null($this->reportStatisticByDeepsModel)) {
            $this->reportStatisticByDeepsModel = new ReportStatisticByDeeps();
        }
    }

    /**
     * 列表数据
     * @param $data
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function lists($data)
    {
        $where = [];
        $this->where($data, $where);
        $lists = $this->reportStatisticByDeepsModel->field(true)->where($where)->select();
        return $lists;
    }

    /** 搜索条件
     * @param $data
     * @param $where
     * @return \think\response\Json
     */
    private function where($data, &$where)
    {
        if (isset($data['channel_id']) && !empty($data['channel_id'])) {
            $where['channel_id'] = ['eq', $data['channel_id']];
        }
        if (isset($data['site_code']) && !empty($data['site_code'])) {
            $where['site_code'] = ['eq', $data['site_code']];
        }
        if (isset($data['warehouse_id']) && !empty($data['warehouse_id'])) {
            $where['warehouse_id'] = ['eq', $data['warehouse_id']];
        }
        if (isset($data['warehouse_type']) && !empty($data['warehouse_type'])) {
            $where['warehouse_type'] = ['eq', $data['warehouse_type']];
        }
        if (isset($data['account_id']) && !empty($data['account_id'])) {
            $where['account_id'] = ['eq', $data['account_id']];
        }
        if (isset($data['user_id']) && !empty($data['user_id'])) {
            $where['user_id'] = ['eq', $data['user_id']];
        }
        $data['date_b'] = isset($data['date_b']) ? $data['date_b'] : 0;
        $data['date_e'] = isset($data['date_e']) ? $data['date_e'] : 0;
        $condition = timeCondition($data['date_b'], $data['date_e']);
        if (!is_array($condition)) {
            return json(['message' => '日期格式错误'], 400);
        }
        if (!empty($condition)) {
            $where['dateline'] = $condition;
        }
    }

    /**
     * 回写月度销售额目标数据
     * @param int $begin_time
     * @param int $end_time
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function writeBackMonthAccount($begin_time = 0, $end_time = 0)
    {
        try {
            $reportStatisticByDeepsModel = new ReportStatisticByDeeps();
            if (!empty($begin_time) && !empty($end_time)) {
                $where['dateline'] = ['between', [$begin_time, $end_time]];
            } else if (!empty($begin_time)) {
                $where['dateline'] = ['eq', $begin_time];
            }
            $dataAmount = [];
            //统计调整
            $dataList = $reportStatisticByDeepsModel->field('user_id,sum(sale_amount / rate) as total_sale_amount,sum(delivery_quantity) as total_delivery_quantity')->where($where)->group('user_id')->select();
            foreach ($dataList as $k => $value) {
                $value = $value->toArray();
                $dataAmount[$value['user_id']] = $value;
            }
            //具体每个分类仓库的操作
            $dataWarehouseList = $reportStatisticByDeepsModel->field('user_id,warehouse_id,sum(sale_amount / rate) as total_sale_amount,sum(delivery_quantity) as total_delivery_quantity')->where($where)->group('warehouse_id,user_id')->select();
            $warehouseService = new Warehouse();
            foreach ($dataWarehouseList as $k => $value) {
                $value = $value->toArray();
                if (isset($dataAmount[$value['user_id']]) && !empty($value['warehouse_id'])) {
                    if (!isset($dataAmount[$value['user_id']]['distribution'])) {
                        $dataAmount[$value['user_id']]['distribution'] = [
                            'local_warehouse_amount' => 0, //本地仓金额
                            'oversea_warehouse_amount' => 0,//海外仓金额
                            'fba_warehouse_amount' => 0, //fba金额
                            'fba_warehouse_orders' => 0,//fba订单数
                        ];
                    }
                    $warehouse_type = $warehouseService->getTypeById($value['warehouse_id']);
                    switch ($warehouse_type) {
                        case 1:   //本地仓库
                            $dataAmount[$value['user_id']]['distribution']['local_warehouse_amount'] += $value['total_sale_amount'];
                            break;
                        case 3:
                            $dataAmount[$value['user_id']]['distribution']['oversea_warehouse_amount'] += $value['total_sale_amount'];
                            break;
                        case 4:
                            $dataAmount[$value['user_id']]['distribution']['fba_warehouse_amount'] += $value['total_sale_amount'];
                            $dataAmount[$value['user_id']]['distribution']['fba_warehouse_orders'] += $value['total_delivery_quantity'];
                            break;
                    }
                }
            }
            //回写记录
            foreach ($dataAmount as $k => $info) {
                $monthlyTargetAmountService = new MonthlyTargetAmountService();
                $monthlyTargetAmountService->addAmount($k, $info['total_sale_amount'], $info['total_delivery_quantity'], $info['distribution']);
            }
        } catch (Exception $e) {
            throw $e;
        }
    }


    /**
     * 统计会写
     * @param $begin_time
     * @param $end_time
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function writeBackPackage($begin_time, $end_time)
    {
        $where['shipping_time'] = ['between', [$begin_time, $end_time]];
//        $packageList = (new OrderPackage())->field('id')->where($where)->select();
//        foreach ($packageList as $k => $packageInfo){
//            //$this->updateReportByDelivery($packageInfo);
//            (new UniqueQueuer(WriteBackOrderDeeps::class))->push($packageInfo['id']);
//        }
        Db::table('order_package')->field('id')->where($where)->chunk(10000, function ($packageList) {
            foreach ($packageList as $packageInfo) {
                (new UniqueQueuer(WriteBackOrderDeeps::class))->push($packageInfo['id']);
            }
        });
    }

    /**
     * 发货之后更新统计信息
     * @param $packageInfo
     */
    public function updateReportByDelivery($package_id)
    {
        try {
            //包裹信息
            $packageInfo = (new OrderPackage())->field(true)->where(['id' => $package_id])->find();
            $time = strtotime(date('Y-m-d', $packageInfo['shipping_time']));
            //订单信息
            $orderInfo = (new Order())->field(true)->where(['id' => $packageInfo['order_id']])->find();
            //详情
            $orderDetailModel = new OrderDetail();
            $packageInfoCost = $orderDetailModel->field('sum(sku_cost * sku_quantity) as cost')->where(['package_id' => $packageInfo['id']])->find();
            $detailCost = $orderDetailModel->field('sum(sku_cost * sku_quantity) as cost')->where(['order_id' => $orderInfo['id']])->find();
            $totalCost = $detailCost['cost'] ?? 0;
            $packageCost = $packageInfoCost['cost'] ?? 0;
            if($totalCost > 0){  //通过 成本比例算钱
                $totalAmount = $packageCost / $totalCost * $orderInfo['goods_amount'] * $orderInfo['rate'];
            }
            $payPal_fee = 0;
            $channel_cost = 0;
            if (!empty(floatval($orderInfo['pay_fee']))) {
                $payPal_fee = ($totalAmount / ($orderInfo['pay_fee'] * $orderInfo['rate'])) * ($orderInfo['paypal_fee'] * $orderInfo['rate']);
                $channel_cost = ($totalAmount / ($orderInfo['pay_fee'] * $orderInfo['rate'])) * $orderInfo['channel_cost'] * $orderInfo['rate'];
            }
            if($channel_cost > $totalAmount){
                Cache::handler()->hSet('hash:statistic:deeps:exception:' . date('Y-m-d', time()), date('Ymd H:i:s'),
                    json_encode(['order_number' => $orderInfo['order_number'],'number' => $packageInfo['number'],'p_cost' => $packageCost,'t_cost' => $totalCost, 'total' => $totalAmount,'channel' => $channel_cost],JSON_UNESCAPED_UNICODE));
            }
            $p_fee = 0;
            if ($packageInfo['channel_id'] == \app\common\service\ChannelAccountConst::channel_amazon) {
                $p_fee = sprintf("%.4f", ($totalAmount - $channel_cost) * 0.006);
            }
            $profits = $totalAmount - $packageInfo['shipping_fee'] - $packageInfo['package_fee'] - $orderInfo['first_fee'] - $orderInfo['tariff'] - $payPal_fee - $channel_cost;
            //平台账号统计销售业绩
            Report::saleByDeeps($packageInfo['channel_id'], $orderInfo['site_code'], $packageInfo['warehouse_id'],
                $orderInfo['channel_account_id'], [
                    'payPal' => $payPal_fee,  //paypal费用
                    'channel' => $channel_cost,  //平台手续费
                    'sale' => $totalAmount,  //销售额
                    'shipping_fee' => $packageInfo['shipping_fee'], //运费
                    'package' => $packageInfo['package_fee'],  //包装费
                    'first' => $orderInfo['first_fee'],  //头程费
                    'tariff' => $orderInfo['tariff'],  //头程报关税
                    'profits' => $profits,  //利润
                    'delivery' => $orderInfo['delivery_type'] == 1 ? 1 : 0,  //渠道账号的订单发货总数
                    'cost' => $orderInfo['delivery_type'] == 1 ? $orderInfo['cost'] : 0,//订单成本
                    'p_fee' => $p_fee //p_fee
                ], $time);
        } catch (Exception $e) {
            Cache::handler()->hSet('hash:statistic:deeps:error:' . date('Y-m-d', time()), date('Ymd H:i:s'), json_encode(['package_id' => $package_id, 'message' => $e->getMessage()],JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 发货fba之后更新统计信息
     * @param $packageInfo
     */
    public function updateReportByFba($order_id)
    {
        try {
            $orderInfo = (new FbaOrder())->field(true)->where(['id' => $order_id])->find();
            $time = strtotime(date('Y-m-d', $orderInfo['create_time']));
            Report::saleByDeeps($orderInfo['channel_id'], $orderInfo['site'], $orderInfo['warehouse_id'], $orderInfo['channel_account_id'], [
                'sale' => $orderInfo['pay_fee'] * $orderInfo['rate'],
                'delivery' => 1
            ], $time);
        } catch (Exception $e) {
            Cache::handler()->hSet('hash:statistic:fba:error:' . date('Y-m-d', time()), date('Ymd H:i:s'), json_encode(['order_id' => $order_id, 'message' => $e->getMessage()],JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 回写fba数据
     * @param $begin_time
     * @param $end_time
     */
    public function writeBackFbaOrder($begin_time, $end_time)
    {
        try {
            //订单信息
            $where['create_time'] = ['between', [$begin_time, $end_time]];
            Db::table('fba_order')->field('id')->where($where)->chunk(10000, function ($orderList) {
                foreach ($orderList as $orderInfo) {
                    (new UniqueQueuer(WriteBackFbaOrderDeeps::class))->push($orderInfo['id']);
                }
            });
        } catch (Exception $e) {

        }
    }
}