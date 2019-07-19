<?php
namespace Fend\Dispatcher;

/**
 * Class standard
 *
 * @property  \swoole_http_server _webserver
 */
class Http extends BaseInterface
{
    protected $_config = null;

    /**
     * 处理 http_server 服务器的 request 请求
     * @param $request
     * @param $response
     * @tutorial 获得 REQUEST_URI 并解析，通过 \Fend\Acl 路由到指定的 controller
     */
    public function onRequest($request, $response)
    {
        \Fend\Di::factory()->set('http_response', $response);
        \Fend\Di::factory()->set('http_request', $request);

        $GLOBALS['_GET']        = !empty($request->get) ? $request->get : array();
        $GLOBALS['_POST']       = !empty($request->post) ? $request->post : array();
        $GLOBALS['_REQUEST']    = array_merge($GLOBALS['_GET'], $GLOBALS['_POST']);
        $GLOBALS['_COOKIE']     = !empty($request->cookie) ? $request->cookie : array();
        $GLOBALS['_HTTPDATA']   = !empty($request->data) ? $request->data : array();
        $GLOBALS['_FD']         = !empty($request->fd) ? $request->fd : '';
        $GLOBALS['_HEADER']     = !empty($request->header) ? $request->header : array();

        if (!empty($request->server)) {
            $_SERVER = array_merge($_SERVER, $request->server, array_change_key_case($request->server, CASE_UPPER));
            $_SERVER["REQUEST_URI"] = $_SERVER["request_uri"];
        }
        $_SERVER['HTTP_USER_AGENT']  = !empty($request->header['user-agent'])?$request->header['user-agent']:'';
        //debug info show
        if (isset($GLOBALS['_GET']["wxdebug"]) && $GLOBALS['_GET']["wxdebug"]==1) {
            $GLOBALS["__DEBUG"]=1;
        } else {
            $GLOBALS["__DEBUG"]=0;
        }
        $host           = parse_url($request->header['host']);
        $_SERVER['HTTP_HOST']   = !empty($host['path'])?$host['path']:$host['host'];
        $_SERVER["REMOTE_ADDR"] = !empty($request->server["remote_addr"])?$request->server["remote_addr"]:'';

        $response->header("Content-Type", "text/html; charset=utf-8;");
        $response->header("Server-Version", "6.0");

        if (!isset($_SERVER['request_uri'])) {
            $response->end("请求非法");
            return;
        }

        $strurl = strtok($_SERVER['request_uri'], '?');
        $strurl = str_replace(array('.php', '.html', '.shtml'), '', $strurl);
        $module = explode('/', $strurl);

        //prepare the traceid
        $traceid    = "";
        $rpcid      = "";

        if (isset($request->header["tal_trace_id"])) {
            $traceid = $request->header["tal_trace_id"];
        }

        if (isset($request->header["tal_rpc_id"])) {
            $rpcid = $request->header["tal_rpc_id"];
        }

        //eagle eye request start init
        \Fend\EagleEye::requestStart($traceid, $rpcid);
        $traceid = \Fend\EagleEye::getTraceId();
        $rpcid = \Fend\EagleEye::getReciveRpcId();

        //set response header contain trace id and rpc id
        $response->header["tal_trace_id"] = $traceid;
        $response->header["tal_rpc_id"] = $rpcid;

        //record this request
        \Fend\EagleEye::setRequestLogInfo("client_ip", \Fend\Funcs\FendHttp::getIp());
        \Fend\EagleEye::setRequestLogInfo("action", $_SERVER['HTTP_HOST']. $strurl);
        \Fend\EagleEye::setRequestLogInfo("param", json_encode(array("post" => $_POST, "get" => $_GET)));
        \Fend\EagleEye::setRequestLogInfo("source", isset($request->header["referer"])?$request->header["referer"]:'');
        \Fend\EagleEye::setRequestLogInfo("user_agent", isset($request->header["user-agent"])?$request->header["user-agent"]:'');
        \Fend\EagleEye::setRequestLogInfo("code", 200);

        $string = '';
        try {
            ob_start();//打开缓冲区
            $state = \Fend\Acl::Factory()->run($module);
            $string = ob_get_contents();//获取缓冲区内容
            \Fend\EagleEye::setRequestLogInfo("code", 200);
        } catch (\Fend\QuitException $e) {
            //quit exception 用于特殊情况终止代码继续执行
            //只是因为不能在worker内exit设计的一个方式
            //方式很low，有更好的替换掉
            $string = ob_get_contents();//获取缓冲区内容
            //todo:exit exception
        } catch (\Throwable $e) {
            //record exception log
            \Fend\Log::exception(basename($e->getFile()), $e->getFile(), $e->getLine(), $e->getMessage(), $e->getCode(), $e->getTraceAsString(), array(
                "action" =>  $_SERVER['HTTP_HOST']. $strurl,
                "server_ip" => \Fend\CliFunc::getLocalIp(),
            ));

            \Fend\EagleEye::setRequestLogInfo("code", 500);
            \Fend\EagleEye::setRequestLogInfo("backtrace", $e->getMessage()."\r\n".$e->getTraceAsString());

            $response->status(500);
            if ($GLOBALS["__DEBUG"]==1) {
                $string .= "onRequest Catch:".$e->getMessage()."\r\n".$e->getTraceAsString();
            }
        }
        ob_end_clean();//清空并关闭缓冲区
        //eagle eye request record finished
        \Fend\EagleEye::setRequestLogInfo("response", $string);
        \Fend\EagleEye::setRequestLogInfo("response_length", strlen($string));
        \Fend\EagleEye::requestFinished();

        //gzip
        if (!empty($request->header["Accept-Encoding"]) && stristr($request->header["Accept-Encoding"], "gzip")) {
            $response->gzip(4);
        }
        //send result
        $response->end($string);

        //clean up last error befor
        error_clear_last();
        clearstatcache();
    }
}
