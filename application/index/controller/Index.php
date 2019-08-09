<?php
namespace app\index\controller;

use app\index\model\Answer;
use app\index\model\Question;
use think\Controller;
use think\View;
use think\Db;
use think\Session;
use think\Model;

class Index extends Controller
{
    public function index()
    {
        return $this->fetch('index');
    }

    public function question()
    {
        if(request()->isPost()){
            if(is_array($_POST['qs'])){
                $qsa = implode(',', $_POST['qs']);
            }else{
                $qsa = trim($_POST['qs']);
            }
            $qt_id = $_POST['qt_id'];
            db('question_tmp')->where('qt_id',$qt_id)->update(['qt_result' => $qsa]);
        }
        if(!Session::has('dati_session')){
            $qas = Db::query("select * from question order by rand() limit 10");
            Session::set('dati_session','cur_dati_'.date('Y-m-d H:i:s'));
            foreach($qas as $item){
                $q_id = $item['q_id'];
                $qt_created = date('Y-m-d H:i:s');
                $session_id = Session::get('dati_session');
                Db::query("insert into question_tmp values (null,'$q_id','$session_id',null,'$qt_created')");
            }
        }
        $session_id_cur = Session::get('dati_session');
        $qas_cur = Db::query("select * from question_tmp t join question q on t.q_id=q.q_id where qt_session = '$session_id_cur' and qt_result is null order by qt_id asc limit 1;");

        if(!empty($qas_cur)){
            $q_id = $qas_cur[0]['q_id'];
            $answer = Db::query("select * from answer where q_id=$q_id order by a_id");
            $this->view->assign('answer',$answer);
            $data = $qas_cur[0];
        }else{
            $data = array('q_type'=>0,'qt_id'=>0,'q_result'=>0);
        }

        $qas_cnt = Db::query("select count(*) cnt from question_tmp where qt_session = '$session_id_cur' and qt_result is null ;");
        $this->view->assign('cnt',$qas_cnt[0]['cnt']);
        $this->view->assign('data',$data);
        $this->view->engine->layout(false);

        return $this->fetch('question');
    }

    public function manager()
    {
        $qas = Db::table('question')->select();
        $this->view->engine->layout(false);
        return $this->fetch('manager');
    }

    public function add_question()
    {
        // q_detail: q_detail, //用来存储问题内容
        // a: a //用来存储所有问题的数组
        $data['q_detail'] = input('post.q_detail');
        $data['q_type'] = (int)input('post.q_type');
        $data['q_created'] = date("Y-m-d H:i:s");
        $answer = input('post.a/a');
        if (empty($answer)) {
            echo json_encode(array('code'=>0, 'msg'=>'添加失败', 'data'=>''));
        }
        //这里我们要将数组输出为字符串 然后对字符串判断是否为空字符串
        //这里a为数组
        if($data['q_type'] == 3){
            //查询题目 是否重复
            $chk_content = db('question')->where('q_detail', $data['q_detail'])->find($data);
            if (empty($chk_content)) {
                //插入数据库
                $data['q_result'] = $answer[0];
                $insert = Db::name('question')->insert($data);
                if($insert){
                    echo json_encode(array('code'=>1, 'msg'=>'添加成功', 'data'=>''));
                }else{
                    echo json_encode(array('code'=>0, 'msg'=>'添加失败', 'data'=>''));
                }
            }
        }
         else {
            //查询题目 是否重复
            $chk_content = db('question')->where('q_detail', $data['q_detail'])->find($data);
            if (empty($chk_content)) {
                //插入数据库
                $insert = Db::name('question')->insert($data);
                $q_id = Db::name('question')->getLastInsID();
                $data_a['q_id'] = $q_id;
                $data_a['a_created'] = date('Y-m-d H:i:s');

                $qs_type = input('post.q_result');
                $qs_types = explode(',',$qs_type);
                $index = 0;
                $q_result = array();
                foreach($answer as $item){
                    $index++;
                    $data_a['a_detail'] = $item;
                    Db::name('answer')->insert($data_a);
                    if(in_array($index,$qs_types)){
                        $a_id = Db::name('answer')->getLastInsID();
                        array_push($q_result, $a_id);
                    }
                }
                $q_result = implode(',', $q_result);
                Question::where('q_id', $q_id)->update(['q_result' => $q_result]);
                echo json_encode(array('code'=>1, 'msg'=>'添加成功', 'data'=>''));
            } else {
                echo json_encode(array('code'=>3, 'msg'=>'该问题已存在', 'data'=>''));
            }
        }
        exit;
    }

    public function get_question()
    {
        $page = isset($_GET['page']) ? $_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? $_GET['limit'] : 10;
        $to = ($page-1)*$limit;
        // 查询出当前页数显示的数据
        $qas = Db::query("select q_id,q_detail,q_type from question limit $to,$limit");

        //$qas = Db::name('question')->where("id",">=","$to")->limit("$limit")->select()->toArray();
        foreach($qas as $k => $item){
            if($item['q_type'] == 1){
                $item['q_type'] = "单选题";
            }elseif($item['q_type'] == 2){
                $item['q_type'] = "多选题";
            }elseif($item['q_type'] == 3){
                $item['q_type'] = "填空题";
            }
            $qas[$k] = $item;
        }
        $list = Question::all();
        $count = count($list);
        $data = array('code'=>0,'msg'=>'','count'=>$count,'data'=>$qas);
        echo json_encode($data);
        exit;
    }

    public function del_question()
    {
        $q_id = $_POST['q_id'];
        $qs = Question::get($q_id);
        $qs->delete();
        Answer::where('q_id','=',$q_id)->delete();
        echo json_encode(array('code'=>1, 'msg'=>'删除成功', 'data'=>''));
        exit;
    }

    public function clear_session(){
        Session::clear();
        exit;
    }

}
