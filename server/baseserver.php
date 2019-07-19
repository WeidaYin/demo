<?php
namespace Fend\Server;

/**
 * 服务端基础服务类
 * 根据配置启动服务端口，不同端口不同dispatcher处理请求
 * Class Fend_Server_BaseServer
 *
 * @property \swoole_websocket_server _server
 * @property \Fend\Dispatcher\BaseInterface _mainDispatcher
 * @property \Fend\Dispatcher\BaseInterface _subDispatcher
 */

class BaseServer
{
    private $_server;

    private $_config;

    private $_subserver = array();

    private $_mainDispatcher = null;

    private $_subDispatcher = array();

    /**
     * Fend_Server_BaseServer constructor.
     * 用于服务启动配置
     * @param $config
     */
    public function __construct($config)
    {
        \Fend\Di::factory()->set('swoole_config', $config);
        $this->_config = $config;
    }

    /**
     * 启动服务
     */
    public function start()
    {
        //create server
        $class_name = $this->_config["server"]["class"];
        $class_obj = $this->_config["server"]["classname"];

        $processMode = SWOOLE_PROCESS;

        //是否开启debug模式，如果开启将会使用base模式执行
        if (isset($this->_config["server"]["process_mode"]) && $this->_config["server"]["process_mode"]) {
            $processMode = SWOOLE_BASE;
        }

        $this->_server = new $class_name(
            $this->_config["server"]["host"],
            $this->_config["server"]["port"],
            $processMode,
            $this->_config["server"]["socket"]
        );

        //set the swoole config
        $this->_server->set($this->_config["swoole"]);

        //show config
        \Fend\Log::write(json_encode($this->_config["swoole"]));

        if (!class_exists($class_obj)) {
            die("baseserver->Config->server->dispatcher class was not found!");
        }

        $this->_mainDispatcher = new $class_obj($this, $this->_server);

        //bind event with main dispatcher
        $this->_server->on('Start', array($this->_mainDispatcher, 'onStart'));
        $this->_server->on('Shutdown', array($this->_mainDispatcher, 'onShutdown'));

        $this->_server->on('WorkerStart', array($this->_mainDispatcher, 'onWorkerStart'));
        $this->_server->on('WorkerError', array($this->_mainDispatcher, 'onWorkerError'));
        $this->_server->on('WorkerStop', array($this->_mainDispatcher, 'onWorkerStop'));

        $this->_server->on('ManagerStart', array($this->_mainDispatcher, 'onManagerStart'));
        $this->_server->on('ManagerStop', array($this->_mainDispatcher, 'onManagerStop'));

        $this->_server->on('Task', array($this->_mainDispatcher, 'onTask'));
        $this->_server->on('Finish', array($this->_mainDispatcher, 'onFinish'));

        $this->_server->on('Close', array($this->_mainDispatcher, 'onClose'));

        //tcp
        if ($this->_config["server"]["class"] == "swoole_server") {
            $this->_server->on('Connect', array($this->_mainDispatcher, 'onConnect'));
            $this->_server->on('Receive', array($this->_mainDispatcher, 'onReceive'));
        }

        //websocket
        if ($this->_config["server"]["class"] == "swoole_websocket_server") {
            $this->_server->on('Open', array($this->_mainDispatcher, 'onOpen'));
            $this->_server->on('Message', array($this->_mainDispatcher, 'onMessage'));
            $this->_server->on('Request', array($this->_mainDispatcher, 'onRequest'));
        }

        //http
        if ($this->_config["server"]["class"] == "swoole_http_server") {
            $this->_server->on('Request', array($this->_mainDispatcher, 'onRequest'));
        }

        //udp
        if ($this->_config["server"]["socket"] == "SWOOLE_SOCK_UDP") {
            $this->_server->on('Packet', array($this->_mainDispatcher, 'onPacket'));
            $this->_server->on('Receive', array($this->_mainDispatcher, 'onReceive'));
        }

        if (is_array($this->_config["listen"]) && count($this->_config["listen"]) > 0) {

            //create new listen with dispatcher
            foreach ($this->_config["listen"] as $key => $config) {
                $this->_subserver[$key] = $this->_server->addListener($config["host"], $config["port"], $config["socket"]);

                $classname = $config['classname'];

                if (!class_exists($classname)) {
                    die("baseserver->Config->listen->" . $key . "->dispatcher class was not found!");
                }
                $this->_subDispatcher[$key] = new $classname($this, $this->_subserver[$key], $config);

                //bind event with listen dispatcher
                //websocket
                if (isset($config["protocol"]["open_websocket_protocol"]) && $config["protocol"]["open_websocket_protocol"] && $config["socket"] == SWOOLE_SOCK_TCP) {
                    $this->_subserver[$key]->on('Open', array($this->_subDispatcher[$key], 'onOpen'));
                    $this->_subserver[$key]->on('Message', array($this->_subDispatcher[$key], 'onMessage'));
                    $this->_subserver[$key]->on('Request', array($this->_subDispatcher[$key], 'onRequest'));
                    $this->_subserver[$key]->on('Close', array($this->_subDispatcher[$key], 'onClose'));
                    continue;
                }

                //http
                if (isset($config["protocol"]["open_http_protocol"]) && $config["protocol"]["open_http_protocol"] && $config["socket"] == SWOOLE_SOCK_TCP) {
                    $this->_subserver[$key]->on('Request', array($this->_subDispatcher[$key], 'onRequest'));
                    continue;
                }

                //tcp
                if ($config["socket"] == SWOOLE_SOCK_TCP) {
                    $this->_subserver[$key]->on('Connect', array($this->_subDispatcher[$key], 'onConnect'));
                    $this->_subserver[$key]->on('Receive', array($this->_subDispatcher[$key], 'onReceive'));
                    $this->_subserver[$key]->on('Close', array($this->_subDispatcher[$key], 'onClose'));
                }

                //udp
                if ($config["socket"] == SWOOLE_SOCK_UDP) {
                    $this->_subserver[$key]->on('Packet', array($this->_subDispatcher[$key], 'onPacket'));
                    $this->_subserver[$key]->on('Receive', array($this->_subDispatcher[$key], 'onReceive'));
                }
            }
        }

        //log agent
        \Fend\EagleEye::setVersion(\Fend\Fend::FEND_VERSION);

        //设置日志存储路径
        \Fend\LogAgent::setLogPath($this->_config["server"]["logpath"]);

        //设置输出日志级别
        \Fend\Log::setLogLevel($this->_config["server"]["loglevel"]);

        //设置channel异步方式写入日志
        \Fend\LogAgent::setDumpLogMode(\Fend\LogAgent::LOGAGENT_DUMP_LOG_MODE_CHANNEL);//swoole channel combine the log mode

        //性能监控服务暂时不开启
        \Fend\EagleEye::disable();

        //log agent for dump log
        $this->_server->addProcess(new \swoole_process(function () {
            \Fend\CliFunc::setProcessName($this->_config["server"]["server_name"], "log");
            \Fend\LogAgent::threadDumpLog();
        }));

        \Fend\Log::info(
            "Server",
            __FILE__,
            __LINE__,
            "Server IP:" . $this->_config["server"]["host"] . " Port:" . $this->_config["server"]["port"] . " LocalIP:" . \Fend\CliFunc::getLocalIp()
        );

        $this->_server->start();
    }

    public function getConfig()
    {
        return $this->_config;
    }
}
