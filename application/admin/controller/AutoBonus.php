<?php

namespace app\admin\controller;
use think\Db;
use app\mobile\logic\RangeLogic;

/**
 * 根据订单信息发放极差奖励
 *
 */
class AutoBonus
{
	/**
	*每周日最后一分钟执行分发本周奖金
	* 1、奖金包含极差奖励、推荐升级奖金和直推合伙人购物百分比奖金
	* 2、奖金会发放到用户的提现金额中
	* 3、插入日志记录
	*
	*/
    public function get_week_bonus(){
        $rangeLogic=new RangeLogic();
        $rangeLogic->weekly_settlement();
        echo '周奖金处理发放成功\n';
    }

//    public function test(){
//        $a=new RangeLogic();
//        $a->cost_shop();
//    }

    public function get_year_bonus(){
        $rangeLogic=new RangeLogic();
        $rangeLogic->partner_team_dividend();
        echo '年分红处理成功\n';
        $rangeLogic->cost_shop();
        echo '年店补处理发放成功\n';
    }
}


