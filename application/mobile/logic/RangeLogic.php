<?php

/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Date: 2015-09-14
 */


namespace app\mobile\logic;

use app\common\logic\CartLogic;
use think\Db;

class RangeLogic
{
    public function get_range($order_id){
        //查询订单信息
        $order_info=$this->get_order_info($order_id);
//        var_dump($order_info);
        if(!empty($order_info)){
            //有没有购买指定商品
            $cartLogic=new CartLogic();
            //获取用户当前等级（购买商品之前）
            $user_info=$this->get_user_info($order_info['user_id']);
//            var_dump($user_info);
            //获取升级指定商品数量
            $upgrade_num=$this->get_order_level_num($order_id);
            //获取在这个等级买过的指定升级产品
            $vip_upgrade_num=$cartLogic->get_vip_upgrade_num($order_info['user_id']);
            if($user_info['level']==3){
                //获取等级为3升级的商品数
                $level_num=$cartLogic->get_up_level_num(3);
                $buy_upgrade_num=$vip_upgrade_num-$level_num;
            }elseif($user_info['level']==4){
                $level_num=$cartLogic->get_up_level_num(3);
                $level_up_num=$cartLogic->get_up_level_num(4);
                $buy_upgrade_num=$vip_upgrade_num-$level_num-$level_up_num;
            }else{
                $buy_upgrade_num=$vip_upgrade_num;
            }
//            $level_time=$cartLogic->get_up_level_time($order_info['user_id'],$user_info['level']);
//            $buy_upgrade_num=$cartLogic->get_time_goods_num($order_info['user_id'],$level_time,1);
            //获取当前用户的折扣
            $user_discount=$cartLogic->get_level_discount($user_info['level']);
            //对比的等级
            $compare_level=$user_info['level'];
            //看有没有上级
//            dump($user_info['parents']);echo "````";
            $user_info['parents']=trim(substr($user_info['parents'],2),',');
//            var_dump($user_info['parents']);die;
             if($user_info['parents']=='') return 0;
            if(in_array($user_info['level'],array(1,2,3))){
                if($upgrade_num==0 || $user_info['level']==1){
                    //没有指定商品正常处理
//                    $user_info['parents']=strrev($user_info['parents']);
                    $parents=explode(',',$user_info['parents']);
                    $parents=array_reverse($parents);
//                    var_dump($parents);
                    foreach($parents as $key=>$value){
                        //获取等级进行对比
                        $user_level=$cartLogic->get_user_level($value);
                        if($user_level>$compare_level){
                            //分情况讨论 处理前一个比自己等级高的会员极差奖励
                            //获取这个会员的折扣
                            $up_user_discount=$cartLogic->get_level_discount($user_level);
                            //获取compare_level的折扣
                            $compare_user_discount=$cartLogic->get_level_discount($compare_level);
                            //算极差奖励
                            $bonus=$order_info['order_amount']/$user_discount*($compare_user_discount-$up_user_discount);
                            $data=array('user_id'=>$value,'bonus'=>$bonus,'order_id'=>$order_id,'buy_discount'=>$compare_user_discount,'reward_discount'=>$up_user_discount,'add_time'=>time());
//                            var_dump($data);die;
                            M('range_log')->insert($data);
                            $this->set_bonus_log($value,$bonus,'极差奖金',$order_id,$order_info['order_sn']);
                            if($user_level==2){
                                //提高比较等级
                                $compare_level=$user_level;
                            }elseif($user_level==3){
                                //提高比较等级
                                $compare_level=$user_level;
                            }else{
                                //找到了最大的了  可以结束了
                                break;
                            }
                        }
                    }
                }else{
                    //指定商品的单价
                    $upgrade_price=$cartLogic->get_upgrade_goods_price();
                    //指定商品会使得用户达到升级条件
                    $level_num=$cartLogic->get_up_level_num($user_info['level']+1);
//                    return $buy_upgrade_num."```".$upgrade_num."``````".$level_num;die;
                    if($upgrade_num>=$level_num){
                        //超过了升一级的数量   再看看是否超过了下一级
                        //升一级之后的折扣
                        $up1_discount=$cartLogic->get_level_discount($user_info['level']+1);
                        //优惠顾客一次性购买指定商品数量等于升级数量的折扣
                        $other_discount=$cartLogic->get_other_discount(2);
                        if($user_info['level']==2){
                            //已经升3级了看有没有升4级
                            $up_level_num=$cartLogic->get_up_level_num($user_info['level']+2);
                            if($upgrade_num==$level_num){
                                //第二部分   买的商品不够升级
                                $second_amount=$upgrade_num*$upgrade_price*$other_discount/100;
                                $this->extreme_dividend($user_info['parents'],$user_info['level']+1,$second_amount,$other_discount,$order_id);
                            }elseif($upgrade_num>$level_num){
                                //连升两级的情况处理
                                //升两级之后的折扣
                                if($upgrade_num>=$level_num+$up_level_num){
                                    $up2_discount=$cartLogic->get_level_discount($user_info['level']+2);
                                    //第三部分  第二次升级后购买的商品   这时候已经升到最大级了  只看看自己的上级是不是可以获得奖励
                                    $last_amount=$upgrade_num*$upgrade_price*$up2_discount/100;
                                    //先看看是不是合伙人
                                    $is_partner=$this->get_is_partner($user_info['first_leader'],$user_info['reg_time']);
                                    //如果是在合伙人等级直推了 增加2000奖金  并奖励5%升级到合伙人之后购买的商品金额
                                    if($is_partner){
                                        $this->partner_bonus(2,$user_info['first_leader'],2000,$order_id,$up2_discount,$up2_discount);
                                        $this->partner_bonus(3,$user_info['first_leader'],$last_amount*5/100,$order_id,$up2_discount,$up2_discount);
                                    }
                                }else{
                                    //第一部分   享有指定折扣的数据
                                    $second_amount=$level_num*$upgrade_price*$other_discount/100;
                                    $this->extreme_dividend($user_info['parents'],$user_info['level']+1,$second_amount,$other_discount,$order_id);
                                    //第二部分   超过升级数量的商品
                                    $second_amount=($upgrade_num-$level_num)*$upgrade_price*$up1_discount/100;
                                    $this->extreme_dividend($user_info['parents'],$user_info['level']+1,$second_amount,$up1_discount,$order_id);
                                }
                            }else{
                                //第二部分   买的商品不够升级
                                $second_amount=$upgrade_num*$upgrade_price*$up1_discount/100;
                                $this->extreme_dividend($user_info['parents'],$user_info['level']+1,$second_amount,$up1_discount,$order_id);
                            }
                        }else{
                            //已经升到满级了
                            //第一部分  从销售员升到合伙人
                            $first_amount=($level_num-$buy_upgrade_num)*$upgrade_price*$user_discount/100;
                            $this->extreme_dividend($user_info['panren'],$user_info['level'],$first_amount,$user_discount,$order_id);
                            //第二部分   已经满级了   只看要不要给上级奖励
                            $last_amount=($buy_upgrade_num+$upgrade_num-$level_num)*$upgrade_price*$up1_discount/100;
                            //先看看是不是合伙人
                            $is_partner=$this->get_is_partner($user_info['first_leader'],$user_info['reg_time']);
                            //如果是在合伙人等级直推了 增加2000奖金  并奖励5%升级到合伙人之后购买的商品金额
                            if($is_partner){
                                $this->partner_bonus(2,$user_info['first_leader'],2000,$order_id,$up1_discount,$up1_discount);
                                $this->partner_bonus(3,$user_info['first_leader'],$last_amount*5/100,$order_id,$up1_discount,$up1_discount);
                            }
                        }
                    }elseif($buy_upgrade_num+$upgrade_num>$level_num){
                        //超过了升一级的数量   再看看是否超过了下一级
                        //升一级之后的折扣
                        $up1_discount=$cartLogic->get_level_discount($user_info['level']+1);
                        if($user_info['level']==2){
                            //已经升3级了看有没有升4级
                            $up_level_num=$cartLogic->get_up_level_num($user_info['level']+2);
                            //第一部分
                            $first_amount=($level_num-$buy_upgrade_num)*$upgrade_price*$user_discount/100;
                            $this->extreme_dividend($user_info['parents'],$user_info['level'],$first_amount,$user_discount,$order_id);
                            if($buy_upgrade_num+$upgrade_num>$level_num+$up_level_num){
                                //连升两级的情况处理
                                //升两级之后的折扣
                                $up2_discount=$cartLogic->get_level_discount($user_info['level']+2);
                                //第二部分
                                $second_amount=$up_level_num*$upgrade_price*$up1_discount/100;
                                $this->extreme_dividend($user_info['parents'],$user_info['level']+1,$second_amount,$up1_discount,$order_id);
                                //第三部分  第二次升级后购买的商品   这时候已经升到最大级了  只看看自己的上级是不是可以获得奖励
                                $last_amount=($buy_upgrade_num+$upgrade_num-$level_num-$up_level_num)*$upgrade_price*$up2_discount/100;
                                //先看看是不是合伙人
                                $is_partner=$this->get_is_partner($user_info['first_leader'],$user_info['reg_time']);
                                //如果是在合伙人等级直推了 增加2000奖金  并奖励5%升级到合伙人之后购买的商品金额
                                if($is_partner){
                                    $this->partner_bonus(2,$user_info['first_leader'],2000,$order_id,$up2_discount,$up2_discount);
                                    $this->partner_bonus(3,$user_info['first_leader'],$last_amount*5/100,$order_id,$up2_discount,$up2_discount);
                                }
                            }else{
                                //第二部分   买的商品不够升级
                                $second_amount=($buy_upgrade_num+$upgrade_num-$level_num)*$upgrade_price*$up1_discount/100;
                                $this->extreme_dividend($user_info['parents'],$user_info['level']+1,$second_amount,$up1_discount,$order_id);
                            }
                        }else{
                            //已经升到满级了
                            //第一部分  从销售员升到合伙人
                            $first_amount=($level_num-$buy_upgrade_num)*$upgrade_price*$user_discount/100;
                            $this->extreme_dividend($user_info['panren'],$user_info['level'],$first_amount,$user_discount,$order_id);
                            //第二部分   已经满级了   只看要不要给上级奖励
                            $last_amount=($buy_upgrade_num+$upgrade_num-$level_num)*$upgrade_price*$up1_discount/100;
                            //先看看是不是合伙人
                            $is_partner=$this->get_is_partner($user_info['first_leader'],$user_info['reg_time']);
                            //如果是在合伙人等级直推了 增加2000奖金  并奖励5%升级到合伙人之后购买的商品金额
                            if($is_partner){
                                $this->partner_bonus(2,$user_info['first_leader'],2000,$order_id,$up1_discount,$up1_discount);
                                $this->partner_bonus(3,$user_info['first_leader'],$last_amount*5/100,$order_id,$up1_discount,$up1_discount);
                            }
                        }
                    }else{
                        //没有升级  正常处理
//                        echo $user_info['parents'];
                        $res=$this->extreme_dividend($user_info['parents'],$user_info['level'],$order_info['order_amount'],$user_discount,$order_id);
//                        var_dump($res);
                    }

                }
            }else{
                //合伙人购买商品处理   已经是合伙人了   不是刚刚升级上来的   判断用不用给上级奖励
                //先看看是不是合伙人
                $is_partner=$this->get_is_partner($user_info['first_leader'],$user_info['reg_time']);
                //如果是在合伙人等级直推了 增加2000奖金  并奖励5%升级到合伙人之后购买的商品金额
                if($is_partner){
                    $this->partner_bonus(3,$user_info['first_leader'],$order_info['order_amount']*5/100,$order_id,$user_discount,$user_discount);
                }
            }
        }
    }

    //奖金日志
    public function set_bonus_log($user_id,$user_money,$desc,$order_id,$order_sn){
        $data=['user_id'=>$user_id,'user_money'=>$user_money,'desc'=>$desc,'change_time'=>time(),'order_id'=>$order_id,'order_sn'=>$order_sn];
        return M('account_log')->insert($data);
    }




    /**
     *分红  年结算
     */
    public function partner_team_dividend($user_id){
        //先看看是不是合伙人
        $user_info=$this->get_user_info($user_id);
        if($user_info['level']!=4) exit('这个会员不是合伙人');
        //寻找团队成员
        $team=$this->get_my_team($user_id);
        if(empty($team)) exit('团队还在组建中');
        //团队总销售额
//        var_dump($team);
        $cost=0;
        //拼接起始时间
        $start_time=strtotime(date('Y-1-1 00:00:00',time()));
        foreach($team as $key=>$value){
//            echo $value."<hr />";
            $cost+=$this->get_sales_volume($value,$start_time);
        }
//        echo $start_time."```".$cost;die;
        $config = tpCache('basic');
        //那么这个人年分红金额为
        $cost=$cost*$config['dividend_ratio']/100;
        //看如何处理这个分红 存入用户余额
        M('users')->where('user_id',$user_id)->setInc('user_money',$cost);
        $data = ['user_id'=>$user_id,'user_money'=>$cost,'desc'=>'年分红','change_time'=>time()];
        M('account_log')->insert($data);

    }

    /**
     * 奖金周结算
     */
    public function weekly_settlement(){
        //先处理时间
        $monday=strtotime(date('Y-m-d 00:00:00', (time() - ((date('w') == 0 ? 7 : date('w')) - 1) * 24 * 3600)));//本周一起始时间
        $user_bonus=$this->get_user_bonus($monday);dump($user_bonus);//die;
        //接下来如何处理这些奖金 存入对应用户余额
        foreach($user_bonus as $k => $v){
            M('users')->where('user_id',$v['user_id'])->setInc('user_money',$v['bonus']);
            $data = ['user_id'=>$v['user_id'],'user_money'=>$v['bonus'],'desc'=>'周奖金','change_time'=>time()];
            M('account_log')->insert($data);
        }
    }

    /**
     * 查询所有获奖的用户从本周一到现在获得的奖金
     */
    public function get_user_bonus($monday){
        return M('range_log')->where('type','in',array(1,2,3))->where('add_time','>',$monday)->field('user_id,sum(bonus) bonus')->group('user_id')->select();
    }

    /**
     * 获取从一个时间至今的订单总价
     * @param $user_id
     * @param $start_time
     * @return int
     */
    public function get_sales_volume($user_id,$start_time){
        if(is_numeric($user_id) && $user_id>0 && $start_time>0){
            $total=M('order')->where(['user_id'=>$user_id,'pay_status'=>1])->where('pay_time','>',$start_time)->field('sum(order_amount) as total')->find();
            return $total['total'];
        }
        return 0;
    }








    //直推合伙人奖金2000  /   5%进货消费
    public function partner_bonus($type,$partner_id,$bonus,$order_id,$user_discount,$partner_discount){
        if(in_array($type,array(2,3))){
            $order_info=$this->get_order_info($order_id);
            $data=array('user_id'=>$partner_id,'bonus'=>$bonus,'order_id'=>$order_id,'buy_discount'=>$user_discount,'reward_discount'=>$partner_discount,'type'=>$type,'add_time'=>time());
            if(M('range_log')->insert($data)){
                if($type == 2){
                    $desc='一次性奖金';
                }else{
                    $desc='直推合伙人百分比奖金';
                }
                $this->set_bonus_log($partner_id,$bonus,$desc,$order_id,$order_info['order_sn']);
            }
        }
    }
    //查询上级是不是在合伙人等级的时候直推的
    public function get_is_partner($first_leader,$reg_time){
        //先看看上级是不是合伙人
        if($first_leader!=0){
            $leader_info=$this->get_user_info($first_leader);
            if($leader_info['level']==4){
                $is_need=M('user_level_time')->where(['user_level'=>4,'user_id'=>$first_leader])->where('level_time','>',$reg_time)->count();
                if($is_need){
                    return 1;
                }
            }
        }
        return 0;
    }

    //极差分红
    public function extreme_dividend($user_parents,$compare_level,$order_amount,$user_discount,$order_id){
        $order_info=$this->get_order_info($order_id);
        $cartLogic=new CartLogic();
//        $user_parents=strrev($user_parents);
        $parents=explode(',',$user_parents);
        $parents=array_reverse($parents);
//        return $parents;
        foreach($parents as $key=>$value){
            //获取等级进行对比
            $user_level=$cartLogic->get_user_level($value);
            if($user_level>$compare_level){
                //分情况讨论 处理前一个比自己等级高的会员极差奖励
                //获取这个会员的折扣
                if($user_level==$compare_level)
                $up_user_discount=$cartLogic->get_level_discount($user_level);
                //获取compare_level的折扣
//                $compare_user_discount=$cartLogic->get_level_discount($compare_level);
                //算极差奖励
                $bonus=$order_amount/$user_discount*($user_discount-$up_user_discount);
                $data=array('user_id'=>$value,'bonus'=>$bonus,'order_id'=>$order_id,'buy_discount'=>$user_discount,'reward_discount'=>$up_user_discount,'add_time'=>time());
                M('range_log')->insert($data);
                $this->set_bonus_log($value,$bonus,"极差奖金",$order_id,$order_info['order_sn']);
                if($user_level==2){
                    //提高比较等级
                    $compare_level=$user_level;
                }elseif($user_level==3){
                    //提高比较等级
                    $compare_level=$user_level;
                }else{
                    //找到了最大的了  可以结束了
                    break;
                }
            }
        }
    }
    //获取订单信息
    public function get_order_info($order_id){
        if(is_numeric($order_id) && $order_id>0){
            return M('order')->where(['order_id'=>$order_id])->find();
        }else{
            return array();
        }
    }
    //获取用户信息
    public function get_user_info($user_id){
        if(is_numeric($user_id) && $user_id>0){
            return M('users')->where(['user_id'=>$user_id])->find();
        }else{
            return array();
        }
    }
    //获取本次订单中有多少升级指定商品
    public function get_order_level_num($order_id){
        if(is_numeric($order_id) && $order_id>0){
            $goods_num=M('order_goods')->alias('og')->join('goods g','og.goods_id=g.goods_id')->where(['is_upgrade'=>1,'og.order_id'=>$order_id])->field('sum(og.goods_num) goods_num')->find();
            if(isset($goods_num)){
                return $goods_num['goods_num'];
            }else{
                return 0;
            }

        }else{
            return 0;
        }
    }
    //寻找自己的团队   直推的人组成了自己的团队
    public function get_my_team($user_id){
        if(is_numeric($user_id) && $user_id>0){
            return M('users')->where(['first_leader'=>$user_id])->column('user_id');
        }
        return array();
    }

    //店补计算
    public function shop_repair($user_id,$order_id)
    {
        //判断是否是合伙人
        $user_info=$this->get_user_info($user_id);
        if($user_info['level']!=4) return '这个会员不是合伙人';
        //判断是否拥有线下门店
        $is_offline = M('user_offline')->where('user_id',$user_id)->value('is_offline');
        if(empty($is_offline) || $is_offline!=1) return '这个合伙人没有线下门店';
        //购买时订单金额
        $order_amount = M('order')->where('order_id',$order_id)->value('order_amount');
        //应获店补金额
        $config = tpCache('basic');
        // $persent = M('config')->where('name' , 'shop_repair')->value('value');
        $user_repair = $order_amount*$config['shop_repair']/100;
        $user_discount = Db::name('user_level')->where('level_id',$user_info['level'])->value('discount'); //折扣
        //存进数据库
        $data=array('user_id'=>$user_id,'bonus'=>$user_repair,'order_id'=>$order_id,'buy_discount'=>$user_discount,'reward_discount'=>$user_discount,'type'=>4,'add_time'=>time());
        // dump($data);die;
        Db::name('range_log')->insert($data);
    }

    //店补年结算
    public function cost_shop()
    {
        //拼接起始时间
        $start_time=strtotime(date('Y-1-1 00:00:00',time()));
        // if(is_numeric($user_id) && $user_id>0 && $start_time>0){
        //先找需要结算店补的用户
        $users=$this->get_all_shop_users();
//        var_dump($users);die;
//        $users=implode(',',$users);
//        $config = tpCache('basic');
        foreach ($users as $key=>$value){
            $total=M('range_log')->where(['type'=>4,'user_id'=>$value])->where('add_time','>',$start_time)->field('sum(bonus) as total')->select();
//            var_dump($total);die;
            if(isset($total[0]['total'])){
                $cost=$total[0]['total'];
                //看如何处理 存入用户余额
                M('users')->where('user_id',$value)->setInc('user_money',$cost);
                $data = ['user_id'=>$value,'user_money'=>$cost,'desc'=>'年店补','change_time'=>time()];
                M('account_log')->insert($data);
            }
        }
//        $total=M('range_log')->where(['type'=>4])->where('user_id','in',$users)->where('add_time','>',$start_time)->field('sum(bonus) as total')->select();
//        var_dump($total);die;
        // }

        //那么这个人年店补金额为
//        foreach($total as $k=>$v){
//            $cost = $v['total'];
//        }

        // $cost=$cost*$config['shop_repair']/100;

    }
    //寻找所有的有店铺的用户
    public function get_all_shop_users(){
        return M('users')->alias('u')->join('user_offline uo','u.user_id=uo.user_id')->where(['uo.is_offline'=>1,'u.level'=>4])->column('u.user_id');
    }
}