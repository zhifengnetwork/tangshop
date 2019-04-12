<?php
/**
 * 升级逻辑
 * @author wu
 * @date 2019-4-3
 */

namespace app\mobile\logic;

use app\common\logic\CartLogic;
use think\Db;

class LevelLogic
{
    /**
     * 升级
     */
    public function upgrade($user_id)
    {
        $nums = $this->condition($user_id);
        $user_level = M('users')->where('user_id',$user_id)->value('level');
        $max_level = Db::name('user_level')->max('level_id');//最大等级
        $time = time();

        if($user_level == 1){
            if($nums >= 3){
                Db::name('users')->where('user_id',$user_id)->setInc('level');
                Db::name('user_level_time')->data(['user_id'=>$user_id,'user_level'=>$user_level+1,'level_time'=>$time])->insert();
                $this->change_discount($user_id);
            }
        }else{
            //如果该等级已买的指定商品数大于升级需要的
            while($nums['goods_num'] >= $nums['need_num']){
                //如果用户等级等于最大等级
                if($user_level != $max_level){
                    Db::name('users')->where('user_id',$user_id)->setInc('level');
                    $user_level = M('users')->where('user_id',$user_id)->value('level');
                    Db::name('user_level_time')->data(['user_id'=>$user_id,'user_level'=>$user_level,'level_time'=>$time])->insert();
                    $this->change_discount($user_id);
                    $nums = $this->condition($user_id);
                    if(empty($nums['need_num'])){
                        break;
                    }
                }else{
                    break;
                }
            }
        }
    }


    /**
     * 获取下级
     */
    public function get_down($user_id)
    {
        // $d_info = Db::query("select `user_id` from `tp_users` where `first_leader` = $user_id");
        $d_info = Db::query("select `user_id` from `tp_users` where `first_leader` = $user_id and `is_audit` = 0");
        if($d_info){
            $id_array =[];
            foreach($d_info as $k=>$v){
                array_push($id_array ,$v['user_id']);
            }
        }
        return $id_array;
    }

    /**
     * 获取升级条件
     */
    // public function condition($user_id){
    //     $user_level = M('users')->where('user_id',$user_id)->value('level');
        
    //     if($user_level == 1){
    //         $count = count($this->get_down($user_id));
    //         return $count;
    //     }else{
    //         $carts = new CartLogic();
    //         $time = M('user_level_time')->where('user_id',$user_id)->value('level_time');
    //         $need_num = $carts->get_up_level_num($user_level+1);
    //         $goods_num = $carts->get_time_goods_num($user_id,$time ,1);
    //         $nums = [
    //             'need_num' => $need_num,
    //             'goods_num' => $goods_num
    //         ];
    //         return $nums;
    //     }
    // }
    public function condition($user_id)
    {
        $user_level = M('users')->where('user_id',$user_id)->value('level');
        
        if($user_level == 1){
            $count = count($this->get_down($user_id));
            return $count;
        }else{
            // $carts = new CartLogic();
            $time = M('user_level_time')->where('user_id',$user_id)->value('level_time');
            $need_num = M('user_level')->where('level_id',$user_level+1)->value('goods_num');//升级需要指定商品数
            $le_num = M('user_level')->where('level_id',$user_level)->value('goods_num');//该级别需要指定商品数
            $goods_num = M('bonus')->where('user_id',$user_id)->value('goods_num');//已经购买了的指定商品数
            $goods_nums = $goods_num - $le_num;
            $nums = [
                'need_num' => $need_num,
                'goods_num' => $goods_nums
            ];
            return $nums;
        }
    }

    //改变users表里的对应等级折扣
    public function change_discount($user_id)
    {
        $level = M('users')->where('user_id',$user_id)->value('level');
        $discount = M('user_level')->where('level_id',$level)->value('discount');
        M('users')->where('user_id',$user_id)->update(['discount'=>$discount/100]);
    }


}


?>