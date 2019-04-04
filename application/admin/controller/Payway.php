<?php
namespace app\admin\controller;

use think\Page;
use think\Db;
use think\Loader;
use think\Verify;

class Payway extends Base{
    
    public function pay_list()
    {
        $pay_list = M('user_pay_way');
        $p = $this->request->param('p');
        $res = $pay_list->order('id')->page($p . ',10')->select();
        if($res){
            foreach($res as $val){
                $list[] = $val;
            }
        }
        $this->assign('list',$list);
        $Page = new Page($count, 10);
        $show = $Page->show();
        $this->assign('page', $show);
        return $this->fetch();
    }

    public function add()
    {
        $act = I('act','add');
        $this->assign('act',$act);
        $id = I('id');
        $flag=0;
        if($id){
            $list_info = D('user_pay_way')->where('id',$id)->find();
            $this->assign('info',$list_info);
        }else{
            $flag=1;   
        }
        $this->assign('flag',$flag);
        return $this->fetch();
    }

    /**
     * 支付方式添加编辑删除
     */
    public function payHandle()
    {
        $data = I('post.');
        $data['add_time'] = time();
        $userLevelValidate = Loader::validate('Payway');
        $return = ['status' => 0, 'msg' => '参数错误', 'result' => ''];//初始化返回信息
        if ($data['act'] == 'add') {
            if (!$userLevelValidate->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '添加失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_pay_way')->add($data);
                if ($r !== false) {
                    $return = ['status' => 1, 'msg' => '添加成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '添加失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'edit') {
            if (!$userLevelValidate->scene('edit')->batch()->check($data)) {
                $return = ['status' => 0, 'msg' => '编辑失败', 'result' => $userLevelValidate->getError()];
            } else {
                $r = D('user_pay_way')->where('id=' . $data['id'])->save($data);
                if ($r !== false) {
                    $return = ['status' => 1, 'msg' => '编辑成功', 'result' => $userLevelValidate->getError()];
                } else {
                    $return = ['status' => 0, 'msg' => '编辑失败，数据库未响应', 'result' => ''];
                }
            }
        }
        if ($data['act'] == 'del') {
            $r = D('user_pay_way')->where('id=' . $data['id'])->delete();
            if ($r !== false) {
                $return = ['status' => 1, 'msg' => '删除成功', 'result' => ''];
            } else {
                $return = ['status' => 0, 'msg' => '删除失败，数据库未响应', 'result' => ''];
            }
        }
        $this->ajaxReturn($return);
    }
}