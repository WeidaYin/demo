<?php
namespace Fend\Dispatcher;

/**
 * Class standard
 *
 * @property  swoole_websocket_server _webserver
 */
class Websocket extends BaseInterface
{
    protected $_config = null;

    /**
     * 当WebSocket客户端与服务器建立连接并完成握手后会回调此函数。
     * @param \swoole_websocket_server $svr
     * @param \swoole_http_request $req
     */
    public function onOpen(\swoole_websocket_server $svr, \swoole_http_request $req)
    {
        //prepare eagle eye
        $traceid    = "";
        $rpcid      = "";
        if (isset($req->header["tal_trace_id"])) {
            $traceid = $req->header["tal_trace_id"];
        }

        if (isset($req->header["tal_rpc_id"])) {
            $rpcid = $req->header["tal_rpc_id"];
        }

        if (isset($req->header["tal_x_version"])) {
            $client_version = $req->header["tal_x_version"];
        }

        //eagle eye request start init
        \Fend\EagleEye::requestStart($traceid, $rpcid);

        //prepare user info
        //$host = parse_url($req->header['host']);
        $user = array();

        $user['fd']             = $req->fd;
        $user['server_ip']      = \Fend\EagleEye::getServerIp();
        $user['server_port']    = $req->server['server_port'];
        $user['user_ip']        = $req->server['remote_addr'];
        $user['user_port']      = $req->server['remote_port'];
        $user['tal_trace_id']   = isset($req->header["tal_trace_id"]) ? $req->header['tal_trace_id'] : \Fend\EagleEye::getTraceId();
        $user['domain']         = parse_url($req->header["host"])['host'];
        $strurl                 = str_replace(array('.php', '.html', '.shtml'), '', $req->server['request_uri']);
        $arr                    = explode('/', $strurl);
        $module                 = !empty($arr[1]) ? $arr[1] : 'index';
        $action                 = !empty($arr[2]) ? $arr[2] : 'index';
        $user['uri']            = $module . "/" . $action;

        $redis                  = \Fend\Cache::Factory(1);
        $redis->set("socket_fd_" . \Fend\EagleEye::getServerIp() . "_" . $user['server_port'] . "_" . $req->fd, $user);

        $eagleEyeLog = array(
            "x_name"        => "websocket.server.connect",
            "x_client_ip"   => $user['user_ip'],
            "x_action"      => $user['domain'] . "/" . $user['uri'],
            "x_param"       => '',
        );
        \Fend\EagleEye::baseLog($eagleEyeLog);
    }

    /**
     * 当服务器收到来自客户端的数据帧时会回调此函数。
     * @param swoole_server $server
     * @param swoole_websocket_frame $frame
     */
    public function onMessage(\swoole_server $server, \swoole_websocket_frame $frame)
    {
        \Fend\Di::factory()->set('swoole_frame', $frame);
        \Fend\Di::factory()->set('socket_server', $server);
        $port = $server->connection_info($frame->fd);
        $port = $port["server_port"];
        $user = \Fend\Cache::factory(1)->get("socket_fd_" . \Fend\EagleEye::getServerIp() . "_" . $port . "_" . $frame->fd);
        $data['event_name'] = "pull";
        $data['fd']     = $frame->fd;
        $data['finish'] = $frame->finish;
        $data['rev']   = $frame->data;
        \Fend\Di::factory()->set('fduser', $user);
        \Fend\Di::factory()->set('fdrev', $data);
        $decodeData = json_decode($frame->data, true);
        $traceId    = "";
        $rpcId      = "";
        if (isset($decodeData["tal_trace_id"])) {
            $traceId = $decodeData["tal_trace_id"];
        }

        if (isset($decodeData["tal_rpc_id"])) {
            $rpcId = $decodeData["tal_rpc_id"];
        }

        //eagle eye request start init
        \Fend\EagleEye::requestStart($traceId, $rpcId);
        //record this request
        \Fend\EagleEye::setRequestLogInfo("client_ip", $user['user_ip']);
        \Fend\EagleEye::setRequestLogInfo("action", $user['uri']);

        $eagleData          = $data;
        $eagleData["user"]  = $user;
        \Fend\EagleEye::setRequestLogInfo("param", json_encode($eagleData));
        $msg['rev'] = $data;
        try {
            ob_start();//打开缓冲区
            \Fend\Acl::Factory()->runSocket($user['uri'], $msg);
            $string = ob_get_contents();//获取缓冲区内容
            ob_end_clean();//清空并关闭缓冲区
            $server->push($frame->fd, $string);
        } catch (\Exception $e) {
            \Fend\EagleEye::setRequestLogInfo("msg", $e->getMessage());
            \Fend\EagleEye::setRequestLogInfo("code", $e->getCode());
            $item = array('errcode' => $e->getCode(), 'errmsg' => $e->getMessage(), 'version' => '1.0',
                          'res' => array(), 'state' => -1);
            $server->push($frame->fd, json_encode($item));
        }
        \Fend\EagleEye::requestFinished();
    }

    public function onClose(\swoole_server $server, $fd, $reactorId)
    {

        //ignore http request process
        $info = $server->connection_info($fd);
        if ($info["websocket_status"] === 0) {
            return;
        }
        $redis      = \Fend\Cache::factory(1);
        $serverIp   = \Fend\EagleEye::getServerIp();

        $eagleEyeLog = array(
            "x_name" => "websocket.server.close",
        );

        $port = $server->connection_info($fd);
        $port = $port["server_port"];
        if ($port == 0) {
            return;
        }

        //get redis info
        $user = $redis->get("socket_fd_" . $serverIp . "_" . $port . "_" . $fd);
        if (isset($user["uri"]) && $user["uri"] != "") {
            //eagle eye request start init
            \Fend\EagleEye::requestStart($user["tal_trace_id"]);

            $data['event_name'] = "close";
            $data['fd'] = $fd;

            $GLOBALS['_user'] = $user;
            $GLOBALS['_sdata'] = $data;

            //eagle eye log
            $eagleEyeLog["x_param"] = json_encode(
                array(
                    "user" => $user,
                    "param" => $data
                )
            );
            //invoke the api
            try {
                $msg['data'] = $data;
                \Fend\Acl::Factory()->runSocket($user['uri'], $msg);
            } catch (\Exception $e) {
                $eagleEyeLog["x_msg"] = $e->getMessage();
                $eagleEyeLog["x_code"] = $e->getCode();
            }

            //remove the fd record on redis

            $redis->del("socket_fd_" . $serverIp . "_" . $port . "_" . $fd);
        } else {
            \Fend\EagleEye::requestStart();
            $eagleEyeLog["x_msg"] = "no fd=$fd info found on redis";
            $eagleEyeLog["x_code"] = "-1";
        }
        \Fend\EagleEye::baseLog($eagleEyeLog);
    }

    /**
     * 事件在Worker进程/Task进程终止时发生
     * @param swoole_server $server
     * @param               $worker_id
     */
    public function onWorkerStop(\swoole_server $server, $worker_id)
    {
    }
}
