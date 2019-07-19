<?php

namespace Fend;

/**
 * 请求路由解析
 * 内部使用fastrouter方式
 * Class Router
 * @package Fend
 */
class Router
{

    private $config = array();

    private $dispatcherHandle = [];

    /**
     * 初始化路由类，并加载路由配置
     * Router constructor.
     * @param $routerConfig
     * @throws \Exception 配置获取错误会抛异常
     */
    public function __construct()
    {
        //record routerConfig
        $this->config = Config::get("router");

        //init router by config
        foreach ($this->config['map'] as $domain => $config) {
            if (!$config["direct"] && !$config["fastrouter"]) {
                throw new RouterException("Router 配置错误！Domain:" . $domain . " direct及fastrouter至少开启一个", -2355);
            }

            $this->dispatcherHandle[$domain]["root"]       = rtrim($config["root"],"\\");
            $this->dispatcherHandle[$domain]["direct"]     = $config["direct"];
            $this->dispatcherHandle[$domain]["fastrouter"] = $config["fastrouter"];

            //when the fastRouter,init it
            if ($config["fastrouter"]) {
                $this->dispatcherHandle[$domain]["router"] = \FastRoute\cachedDispatcher(function (\FastRoute\RouteCollector $routerCollector) use ($config, $domain) {
                    foreach ($config["router"] as $routerDefine) {
                        $routerCollector->addRoute($routerDefine[0], $routerDefine[1], $routerDefine[2]);
                    }
                }, [
                    'cacheFile'     => SYS_CACHE . 'route.' . $domain . '.cache',
                    'cacheDisabled' => false,
                ]);
            }
        }

    }

    /**
     * 根据method及uri调用对应配置的类
     * @param string $domain 域名
     * @param string $httpMethod post get other
     * @param string $uri 请求网址
     * @return string
     * @throws \Exception
     */
    public function dispatch($domain, $httpMethod, $uri)
    {

        $uri = str_replace(array("//", "///", "////"), "", $uri);

        //域名查找配置
        if (isset($this->config["map"][$domain])) {
            $dispatcher = $this->dispatcherHandle[$domain];
        } else if (isset($this->config["map"]["default"])) {
            $dispatcher = $this->dispatcherHandle["default"];
        } else {
            throw new RouterException("router has no default config for router", -2356);
        }

        //如果启用了fastrouter
        if ($dispatcher["fastrouter"]) {
            //解析路由

            $routeInfo = $dispatcher["router"]->dispatch($httpMethod, $uri);

            //result status decide
            switch ($routeInfo[0]) {
                case\FastRoute\Dispatcher::NOT_FOUND:
                    // ... 404 Not Found
                    //try other router
                    break;
                case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                    //$allowedMethods = $routeInfo[1];
                    // ... 405 Method Not Allowed
                    throw new RouterException("405 Method Not Allowed", 405);
                    break;
                case \FastRoute\Dispatcher::FOUND:
                    $handler = $routeInfo[1];
                    $vars    = $routeInfo[2];

                    //设置网址内包含的参数
                    \Fend\Di::factory()->set("urlParam", $vars);

                    //string rule is controllerName@functionName
                    if (is_string($handler)) {
                        //decode handle setting
                        $handler = explode("@", $handler);
                        if (count($handler) != 2) {
                            throw new RouterException("Router Config error on handle.Handle only support two parameter with @" . $uri, -105);
                        }

                        $className = $handler[0];
                        $func      = $handler[1];

                        //class check
                        if (!class_exists($className)) {
                            throw new RouterException("Router $uri Handle definded Class Not Found", -106);
                        }

                        //method check
                        if (!method_exists($className, $func)) {
                            throw new RouterException("Router $uri Handle definded $func Method Not Found", -107);
                        }

                        //new controller
                        $controller = new $className();

                        //invoke controller and get result
                        return $controller->$func();

                    } else if (is_callable($handler)) {
                        //call direct when router define an callable function
                        return call_user_func_array($handler, []);
                    } else {
                        throw new RouterException("Router Config error on handle." . $uri, -108);
                    }
                    break;
            }
        }

        if ($dispatcher["direct"]) {
            return $this->defaultRouter($domain, $httpMethod, $uri);
        }

        throw new RouterException("router has no default config for router", -2356);
    }

    /**
     * 默认路由方式
     * 如果fastrouter没有设置路由，那么会请求到这里
     * 默认路由会根据uri到对应目录找文件，找到会调用他
     * 这么做是为了方便开发，个性设置走个性设置，常规默认能工作
     * @param string $domain
     * @param string $uri
     * @param string $httpMethod
     * @return string
     * @throws RouterException
     */
    public function defaultRouter($domain, $httpMethod, $uri)
    {
        //域名查找配置
        if (isset($this->config["map"][$domain])) {
            $dispatcher = $this->dispatcherHandle[$domain];
        } else if (isset($this->config["map"]["default"])) {
            $dispatcher = $this->dispatcherHandle["default"];
        } else {
            throw new RouterException("router has no default config for router", -2356);
        }

        $uri = trim($uri, "/");
        $uri = explode("/", $uri);

        //uri为空，那么默认index
        if (empty($uri) || count($uri) == 1 && $uri[0] === "") {
            $className = $dispatcher["root"] . "\\Index";

            if (class_exists($className) && method_exists($className, "index")) {
                $controller = new $className();
                //init
                if (method_exists($className, "Init")) {
                    $controller->Init();
                }
                return $controller->index();
            }

            //找不到404
            throw new RouterException("Default Router index/index Handle define Class Not Found", 404);
        }

        //尝试uri为class名称
        //查找index执行
        //$className = $className . "\\" . $function;
        $className = $dispatcher["root"] . "\\" . implode("\\", $uri);
        $function  = "index";

        if (class_exists($className)) {


            if (method_exists($className, $function)) {
                $controller = new $className();

                //init
                if (method_exists($className, "Init")) {
                    $controller->Init();
                }
                //invoke controller and get result
                return $controller->$function();
            }

        }

        //判断最后uri是否为function,前面作为类路径
        //检测一次

        $function  = array_pop($uri);
        $className = $dispatcher["root"] ."\\". implode("\\", $uri);

        //前面是否为合法类路径
        if (class_exists($className)) {

            //并且最后一个是function name
            if (method_exists($className, $function)) {
                $controller = new $className();

                //init
                if (method_exists($className, "Init")) {
                    $controller->Init();
                }
                //invoke controller and get result
                return $controller->$function();
            }

        }

        //找不到了
        throw new RouterException("404 " . implode("\\", $uri) . " map not found", 404);

    }


}