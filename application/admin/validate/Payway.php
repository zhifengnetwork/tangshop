<?php
namespace app\admin\validate;
use think\Validate;
class Payway extends Validate
{
    // 验证规则
    protected $rule = [
        ['pay_name', 'require'],
        ['pay_card','number'],
    ];
    //错误信息
    protected $message  = [
        'pay_name.require'    => '名称必填',
        'pay_card.number'         => '卡号必须是数字',
    ];
    //验证场景
    protected $scene = [
        'edit'  =>  [
            'pay_name'    =>'require',
            'pay_card'        =>'number',
        ],
    ];
}