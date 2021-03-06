<?php
namespace  app\report\queue;

use app\common\cache\Cache;
use app\common\service\SwooleQueueJob;
use app\report\service\PerformanceService;


class PerformanceExportQueue extends SwooleQueueJob
{
    public function getName(): string
    {
        return "销售利润汇总导出队列";
    }

    public function getDesc(): string
    {
        return "销售利润汇总导出队列";
    }

    public function getAuthor(): string
    {
        return "phill";
    }

    public static function swooleTaskMaxNumber():int
    {
        return 10;
    }

    public function execute()
    {
        try {
            $serv = new PerformanceService();
            $serv->export($this->params);
        }catch (\Exception $ex){
            Cache::handler()->hset(
                'hash:report_export',
                'error_'.time(),
                $ex->getMessage());
        }
    }
}