<?php
/**
 * Created by PhpStorm.
 * User: joy
 * Date: 18-5-22
 * Time: 下午3:02
 */

namespace app\common\model\shopee;


use think\Model;

class ShopeeSite extends Model
{
    public const  SITES=[
        'th'=>1,
        'my'=>2,
        'cn'=>3,
        'id'=>4,
        'ph'=>5,
        'sg'=>6,
    ];
    protected function initialize()
    {
        parent::initialize(); // TODO: Change the autogenerated stub
    }
}