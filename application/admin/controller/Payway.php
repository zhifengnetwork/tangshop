<?php
namespace app\admin\controller;

use think\Page;
use think\Db;

class Payway extends Base{
    
    public function pay_list()
    {
        $pay_list = M('user_pay_way')->select();
        $this->assign('pay_list',$pay_list);
        // dump($pay_list);die;
        return $this->fetch();
    }

    public function add()
    {
        return $this->fetch();
    }
}