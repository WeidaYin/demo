<?php
namespace Fend\Dispatcher;

/**
 * swoole-server核心时间回调
 *
 * @property \Fend\Server\BaseServer _mainObj
 **/
abstract class BaseInterface
{
    protected $_currentServer = null;
    protected $_mainObj = null;

    protected $_config = null;

    /**
     * Fend_Dispatcher_BaseInterface constructor.
     * @param \Fend\Server\BaseServer $mainobj
     * @param \swoole_server $currentserver
     * @param array|null $config
     */
    public function __construct($mainobj, $currentserver=null, $config=null)
    {
        $this->_mainObj = $mainobj;
        $this->_config = $mainobj->getConfig();
        $this->_currentServer = $currentserver;
    }

    /**
     * Server启动在主进程的主线程回调此函数
     * @param \swoole_server $server
     */
    public function onStart(\swoole_server $server)
    {
        \Fend\CliFunc::setProcessName($this->_config["server"]["server_name"], "master");
    }

    /**
     * server结束时回调事件
     * @param \swoole_server $server
     */
    public function onShutdown(\swoole_server $server)
    {
        \Fend\LogAgent::flushChannel();
    }

    /**
     * 事件在Worker进程/Task进程启动时发生
     * @param \swoole_server $server
     * @param               $worker_id
     */
    public function onWorkerStart(\swoole_server $server, $worker_id)
    {
        \Fend\Di::factory()->set('swoole_server', $server);
        if (!$server->taskworker) {
            //worker
            \Fend\CliFunc::setProcessName($this->_config["server"]["server_name"], "worker");
        } else {
            //task
            \Fend\CliFunc::setProcessName($this->_config["server"]["server_name"], "task");
        }
    }

    /**
     * 当worker/task_worker进程发生异常后会在Manager进程内回调此函数。
     * @param \swoole_server $serv
     * @param               $worker_id
     * @param               $worker_pid
     * @param               $exit_code
     * @param               $signal
     */
    public function onWorkerError(\swoole_server $serv, $worker_id, $worker_pid, $exit_code, $signal)
    {
    }

    /**
     * 事件在Worker进程/Task进程终止时发生
     * @param \swoole_server $server
     * @param               $worker_id
     */
    public function onWorkerStop(\swoole_server $server, $worker_id)
    {
    }

    /**
     * 当管理进程启动时回调事件
     * @param \swoole_server $serv
     */
    public function onManagerStart(\swoole_server $serv)
    {
        \Fend\CliFunc::setProcessName($this->_config["server"]["server_name"], "manager");
    }

    /**
     * 当管理进程结束时回调函数
     * @param \swoole_server $serv
     */
    public function onManagerStop(\swoole_server $serv)
    {
    }

    /**
     * 新的连接回调事件--worker中
     * @param \swoole_server $server
     * @param               $fd
     * @param               $from_id
     */
    public function onConnect(\swoole_server $server, $fd, $from_id)
    {
    }

    /**
     * 收到数据时的回调,发生在worker中
     * @param \swoole_server $server
     * @param               $fd
     * @param               $reactor_id
     * @param               $data
     */
    public function onReceive(\swoole_server $server, $fd, $reactor_id, $data)
    {
    }

    /**
     * UDP数据回调
     * @param \swoole_server $server
     * @param               $data
     * @param               $client_info
     */
    public function onPacket(\swoole_server $server, $data, $client_info)
    {
    }

    /**
     * TCP客户端连接关闭后，在worker进程中回调此函数
     * @param \swoole_server $server
     * @param               $fd
     * @param               $reactorId
     */
    public function onClose(\swoole_server $server, $fd, $reactorId)
    {
    }

    /**
     * work中投递任务时发生的回调事件
     * @param \swoole_server $serv
     * @param               $task_id
     * @param               $src_worker_id
     * @param               $data
     * @return mixed
     */
    public function onTask(\swoole_server $serv, $task_id, $src_worker_id, $data)
    {
        if (!empty($data['url'])) {
            $arr        = explode('/', $data['url']);
            $module     = !empty($arr[0]) ? $arr[0] : 'default';
            $controller = !empty($arr[1]) ? $arr[1] : 'index';
            $action     = !empty($arr[2]) ? $arr[2] : 'index';
            $msg        = !empty($data['data']) ? $data['data'] : array();
            \Fend\Acl::Factory()->runTask(array($controller, $action, array('data' => $msg)), $module);
        }
        return $data;
    }

    /**
     * 当worker进程投递的任务在task_worker中完成时回调此函数
     * @param \swoole_server $serv
     * @param               $task_id
     * @param               $data
     */
    public function onFinish(\swoole_server $serv, $task_id, $data)
    {
    }

    /**
     * http-server的接受一个连接的时的回调函数
     * @param $request
     * @param $response
     */

    public function onRequest($request, $response)
    {
    }

    /**
     * 当WebSocket客户端与服务器建立连接并完成握手后会回调此函数。
     * @param \swoole_websocket_server $svr
     * @param \swoole_http_request     $req
     */
    public function onOpen(\swoole_websocket_server $svr, \swoole_http_request $req)
    {
    }
    /**
     * 当服务器收到来自客户端的数据帧时会回调此函数。
     * @param \swoole_server          $server
     * @param \swoole_websocket_frame $frame
     */
    public function onMessage(\swoole_server $server, \swoole_websocket_frame $frame)
    {
    }
}
