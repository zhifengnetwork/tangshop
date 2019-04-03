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
        $sql = "INSERT INTO `tp_user_level_time` (`user_id`, `user_level`, `level_time`) VALUES ('$user_id','$user_level'+1,'$time')";

        if($user_level == 1){
            if($nums >= 3){
                Db::name('users')->where('user_id',$user_id)->setInc('level');
                Db::query($sql);
            }
        }else{
            //如果用户等级等于最大等级，
            if($user_level != $max_level){
                while($nums['need_num'] >= $nums['goods_num']){
                    Db::name('users')->where('user_id',$user_id)->setInc('level');
                    Db::query($sql);
                    $nums = $this->condition($user_id);
                };
            }
        }
    }


    /**
     * 获取下级
     */
    public function get_down($user_id)
    {
        $d_info = Db::query("select `user_id` from `tp_users` where `first_leader` = $user_id");
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
    public function condition($user_id){
        $user_level = M('users')->where('user_id',$user_id)->value('level');
        
        if($user_level == 1){
            $count = count($this->get_down($user_id));
            return $count;
        }else{
            $carts = new CartLogic();
            $time = M('user_level_time')->where('user_id',$user_id)->value('level_time');
            $need_num = $carts->get_up_level_num($user_level+1);
            $goods_num = $carts->get_time_goods_num($user_id,$time ,1);
            $nums = [
                'need_num' => $need_num,
                'goods_num' => $goods_num
            ];
            return $nums;
        }
    }


}


?>