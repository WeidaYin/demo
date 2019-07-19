<?php
namespace Fend;

/**
 * 异步任务投递
 * //$data = array('test'=>"this is ok","time"=>time());
 * Fend_Task::Factory()->add("api/index/pushtask",$data);
 */
class Task extends Fend
{
    public static function Factory()
    {
        return new self();
    }
    /**
     * 任务投递
     *
     * @param  string $url      投递地址
     * @param  array() $data    投递内容
     * @param  string $baseurl  基础Url
     * @param  int    $debug    是否直接显示，0不显示，1直接抛出
     * @return void
     **/
    public function add($url, $data=array(), $baseurl='')
    {
        $data = array('url'=>$url,'baseurl'=>$baseurl,'data'=>$data);
        $swoole = \Fend\Di::factory()->get('swoole_server');
        if (!empty($swoole)) {
            return $swoole->task($data);
        }
        return false;
    }

    /**
     * 返回当前server的任务状态
     */
    public function getTaskStatus()
    {
        $list = array();
        $swoole = \Fend\Di::factory()->get('swoole_server');

        if (!empty($swoole)) {
            $list =  $swoole->stats();
        }
        return $list;
    }
}
