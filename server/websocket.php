<?php
namespace Fend\Server;

/**
 *
 * websocket-server
 * 对象介绍
 *
 * @Author  PhpStorm
 * @version $Id$
**/
class Websocket
{
    private $_module 	= array();//被注册的模块
    private $_routeobj 	= null;//路由器对象
    private $_route 	= array();//被路由的模块
    private $_server    = null;
    private static $in;//内置全局状态变量

    /**
     * 工厂模式: 激活并返回对象
     *
     * @return object
     **/
    public static function Factory()
    {
        if (!isset(self::$in)) {
            self::$in=new self();
        }
        return self::$in;
    }
    /**
     * 获取URL
     *
     * @param int $tp 获取集合还是一个
     **/
    public function getParam($url=array())
    {
        $uri = str_replace('.html', '', $_SERVER['REQUEST_URI']);
        $app = strtok($uri, '?');
        $app = explode('/', $app);
        $this->url = $app;
    }


    public function start()
    {
        if (!extension_loaded('swoole') || empty($this->cfg['swoole'])) {
            die('请安装swoole扩展');
        }
        swoole_set_process_name("websocket_master");
        $this->_server = new swoole_websocket_server($this->cfg['swoole']['ip'], $this->cfg['swoole']['port']);
        $this->_server->set($this->cfg['swoole']['set']);
        $this->_server->on('open', function (swoole_websocket_server $server, $request) {
            $data['server'] = $request->server;
            $data['cookie'] = $request->cookie;
            $data['get']    = $request->get;
            $data['post']   = $request->post;
            Cache_Redis::Factory()->set("fd_".$request->fd, $data);
        });

        $this->_server->on('message', function (swoole_websocket_server $server, $frame) {
            $_SERVER['HTTP_HOST'] = $this->cfg['swoole']['server'];
            $file   = "fd_".intval($frame->fd);
            $q      = Cache_Redis::Factory()->get($file);
            $_SERVER['request_uri'] = !empty($q['server']['request_uri'])?$q['server']['request_uri']:'/';
            $_GET['socket_uid'] = $frame->fd;       //当前登录用户
            $_GET['socket_msg'] = $frame->data;     //发送的数据
            $_GET['socket_opcode']= $frame->opcode;
            $_GET['socket_finish']= $frame->finish;
            $strurl = strtok($_SERVER['request_uri'], '?');
            $strurl = str_replace('.html', '', $strurl);
            $arr    = explode('/', $strurl);
            $module = !empty($arr[1])?$arr[1]:'index';
            $action = !empty($arr[2])?$arr[2]:'index';
            $this->run(array($module,$action));
        });

        $this->_server->on('close', function ($server, $fd) {
            Cache_Redis::Factory()->del("fd_".$fd);
            echo "this is close {$fd}\n";
        });
        $this->_server->start();
    }

    public function run($app, $moddir='')
    {
        self::getParam();
        $dmethod = '';
        $hostname= $_SERVER['HTTP_HOST'];
        $baseurl = $this->cfg['sys_controller'];
        if (isset($this->doname[$hostname])) {
            $baseurl .= $this->doname[$hostname];
        }
        if (!empty($moddir)) {
            $baseurl .= $moddir.'/';
            $dmethod = $app[1];
        }
        if (!empty($app) && file_exists($baseurl.$app[0].'.php')) {
            $dmethod = empty($this->url[2])?$dmethod:$this->url[2];
            require_once($baseurl.$app[0].'.php');
            $controller = 'Controller'.ucfirst($app[0]);
            $obj = new $controller();
        } elseif (!empty($app) && is_file($baseurl.$app[0].'/'.$app[1].'.php')) {
            $dmethod = empty($this->url[3])?$dmethod:$this->url[3];
            require_once($baseurl.$app[0].'/'.$app[1].'.php');
            $controller = 'Controller'.ucfirst($app[1]);
            $obj = new $controller();
        } elseif (!empty($app) && is_file($baseurl.$app[0].'/'.$app[0].'.php')) {
            $dmethod = empty($this->url[2])?$dmethod:$this->url[2];
            require_once($baseurl.$app[0].'/'.$app[0].'.php');
            $controller = 'Controller'.ucfirst($app[0]);
            $obj = new $controller();
        } else {
            return 404;
        }

        $methods = get_class_methods($obj);
        if (!empty($moddir)) {
            $dmethod = empty($dmethod)?'index':$dmethod;
        }
        foreach ($methods as &$v) {
            $v = strtolower($v);
        }
        $dmethod = strtolower($dmethod);
        if (in_array('init', $methods)) {
            call_user_func_array(array($obj,'Init'), array());
        }
        if (!empty($dmethod) && in_array($dmethod, $methods)) {
            call_user_func_array(array($obj,$dmethod), array());
        } elseif (in_array('index', $methods)) {
            call_user_func_array(array($obj,'index'), array());
        } else {
            return 404;
        }
        unset($obj);
    }
}
