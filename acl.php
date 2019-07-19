<?php
namespace Fend;

/**
 * 框架老router
 * 即将淘汰，留着兼容
 * Class Acl
 * @package Fend
 */
class Acl extends Fend
{
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

    public function run($app=array(), $moddir='')
    {
        \Fend\Di::factory()->set("debug_error", null);
        $swoole = \Fend\Di::factory()->get('http_response');
        $router = \Fend\Config::get('acl');
        $domainMap = $router["domain"];

        //reset status 200
        if ($swoole) {
            $swoole->status(200);
            \Fend\EagleEye::setRequestLogInfo("code", 200);
        }

        if (!empty($swoole->server)) {
            $_SERVER = array_merge($_SERVER, $swoole->server);
        }

        $uri = '';
        if (!empty($_SERVER['request_uri'])) {
            $uri = str_replace('.html', '', $_SERVER['request_uri']);
        } elseif (!empty($_SERVER['REQUEST_URI'])) {
            $uri = str_replace('.html', '', $_SERVER['REQUEST_URI']);
        }
        $app = strtok($uri, '?');
        $app = explode('/', $app);
        $app = \Fend\Funcs\FendArray::getFilterArray($app);
        $this->url = $app;

        $dmethod  = '';
        $baseurl  = defined('SYS_CONTROLLER') ? SYS_CONTROLLER : '';
        $hostname = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $app      = !empty($app) ? $app : $this->url;
        $app      = \Fend\Funcs\FendArray::getFilterArray($app);
        if (isset($domainMap[$hostname])) {
            $baseurl .= $domainMap[$hostname];
        }

        if (!empty($moddir)) {
            $baseurl .= $moddir . '/';
        }
        $baseurl = str_replace('//', '/', $baseurl);
        if (empty($app)) {
            $dmethod  = 'index';
            $pathfile = rtrim(strtolower($baseurl), '/') . '/index.php';
            if (is_file($pathfile)) {
                require_once($pathfile);
            }
        } else {
            $app       = array_values($app);
            $pathtotal = count($app);
            $pathfile  = $baseurl . join('/', $app) . '.php';
            $pathfile  = strtolower($pathfile);
            foreach ($app as $key => $path) {
                if (is_file($pathfile)) {
                    require_once($pathfile);
                    $dmethod = !empty($dmethod) ? $dmethod : 'index';
                    break;
                } else {
                    $pathfile   = strtolower(dirname($pathfile)).".php";
                    $dmethod    =  $app[$pathtotal-($key+1)];
                }
            }
        }
        $controller = str_ireplace(SYS_CONTROLLER, '', str_replace('.php', '', $pathfile)); //找到要拼接控制器的字符
        $controller = ucwords(str_replace("/", " ", $controller)); //每级 首字母大写
        $controller = "Controller" . str_replace(" ", "", $controller); //拼接控制器类名


        if (empty($controller) || !class_exists($controller)) {
            \Fend\Di::factory()->set("debug_error", \Fend\Exception::Factory('not fount file:' . $pathfile)->ShowTry(1, 1));
            if ($swoole) {
                $swoole->status(404);
                \Fend\EagleEye::setRequestLogInfo("code", 404);
            }
        } else {
            $obj     = new $controller();
            $methods = get_class_methods($obj);

            foreach ($methods as &$v) {
                $v = strtolower($v);
            }
            $dmethod = strtolower($dmethod);
            if (in_array('init', $methods)) {
                call_user_func_array(array($obj, 'Init'), array());
            }

            if (!empty($dmethod) && in_array($dmethod, $methods)) {
                call_user_func_array(array($obj, $dmethod), array());
            } else {
                \Fend\Di::factory()->set("debug_error", \Fend\Exception::Factory("file:" . $pathfile . ' not find method:' . $dmethod)->ShowTry(1, 1));
                if ($swoole) {
                    $swoole->status(404);
                    \Fend\EagleEye::setRequestLogInfo("code", 404);
                }
            }
        }
        //if debug is open show debug info
        if (isset($this->_DEBUG) && $this->_DEBUG == 1) {
            \Fend\Debug::Factory()->dump();
        }
        return false;
    }



    /**
     * 执行异步路由
     * @param  string $uri       请求路由地址
     * @param  string $moddir      路由起始地址
     * @param  array $data        传递数据
     * @return mixed
     */
    public function runTask($uri, $moddir='', $data=array())
    {
        //确定路由目录
        if (!empty($moddir)) {
            $baseurl = $moddir;
            $first   = '';
        } else {
            $baseurl = SYS_CONTROLLER;
            $first   = 'Controller';
        }
        if (strpos($uri, '/')===0) {
            $url = substr($uri, 1);
        }
        $app     = explode('/', $uri);
        if (count($app)>=3) {
            if (!empty($app[0])) {
                $baseurl .= $app[0].'/';
            }
            if (!empty($app[1])) {
                $file    = $app[1];
            } else {
                $file    = 'index';
            }
            $dmethod = !empty($app[2])?$app[2]:'index';
        } else {
            if (!empty($app[0])) {
                $file    = $app[0];
            } else {
                $file    = 'index';
            }
            $dmethod = !empty($app[1])?$app[1]:'index';
        }

        $filename = strtolower($baseurl.$file.'.php');
        if (!empty($app) && file_exists($filename)) {
            require_once($filename);
            $controller = $first.ucfirst($file);
            $obj = new $controller();
        } else {
            return 404;
        }
        $methods = get_class_methods($obj);
        foreach ($methods as &$v) {
            $v = strtolower($v);
        }
        $dmethod = strtolower($dmethod);

        if (!empty($dmethod) && in_array($dmethod, $methods)) {
            return call_user_func_array(array($obj,$dmethod), array($data));
        } elseif (in_array('index', $methods)) {
            return call_user_func_array(array($obj,'index'), array($data));
        } else {
            return 404;
        }
    }
}
