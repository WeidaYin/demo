<?php
namespace Fend;

/*
 * 老用户兼容使用
 * 老框架没有namespace
 * 加载后老框架所有功能可以使用
 * by ChangLong.Xu
 */

define('FD_SERVICES', dirname(SYS_ROOTDIR) . FD_DS . 'services' . FD_DS);
define('FD_LIBMODEL', dirname(SYS_ROOTDIR) . FD_DS . 'model' . FD_DS);
define('FD_DATAMODEL', dirname(SYS_ROOTDIR) . FD_DS . 'datamodel' . FD_DS);

spl_autoload_register("\Fend\Fend_AutoLoad");


/**
 * 魔法函数: 自动加载对象文件
 * */
function Fend_AutoLoad($class)
{

    $class  = strtolower($class);
    $mods   = explode('_', $class);
    $module = strtolower(array_pop($mods));
    $prefix = strtok($class, '_');

    if ($prefix == 'fend') {
        $file = SYS_ROOTDIR . str_replace('_', FD_DS, substr($class, 5));
    } elseif (in_array($module, array('read', 'write'))) {
        $file = FD_LIBMODEL . str_replace('_', FD_DS, $class);
    } elseif ($prefix == 'model') {
        $file = FD_LIBMODEL . str_replace(array('_', 'model'), array(FD_DS, ''), $class);
    } elseif ($prefix == 'conf') {
        $file = FD_CONF . str_replace(array('_', 'conf'), array(FD_DS, ''), $class);
    } elseif ($prefix == 'services') {
        $file = FD_SERVICES . str_replace(array('_', 'services'), array(FD_DS, ''), $class);
    } elseif ($prefix == 'datamodel') {
        $file = FD_DATAMODEL . str_replace(array('_', 'datamodel'), array(FD_DS, ''), $class);
    } elseif ($prefix == 'controller') {
        $file = SYS_CONTROLLER . str_replace(array('_', 'controller'), array(FD_DS, ''), $class);
    }

    if (empty($file) || !is_file($file . '.php')) {//捕捉异常
        $swoole = \Fend\Di::factory()->get('http_response');
        if (!empty($swoole && $GLOBALS["__DEBUG"] == 1)) {
            $content = ob_get_contents() . "\r\n"; //获取缓冲区内容
            ob_start(); //打开缓冲区
            \Fend\Exception::Factory("$file'.php':Has Not Found Class $class")->ShowTry(1);
            $content .= ob_get_contents(); //获取缓冲区内容
            $swoole->header("Content-Length", strlen($content));
            $swoole->status(404);
            ob_end_clean(); //清空并关闭缓冲区
            $swoole->end($content);
            return false;
        }
        return false;
    } else {
        include_once($file . '.php');
        return true;
    }
}



class_alias("Fend\\Cache", 'Fend_Cache');
//class_alias("Fend\\Cache\\Memcache", "Fend_Cache_Memcache");
//class_alias("Fend\\Cache\Redis", "Fend_Cache_Redis");

class_alias("Fend\\CliFunc", "Fend_CliFunc");

class_alias("Fend\\Db\\Module", "Fend_Db_Module");
class_alias("Fend\\Db\\Mysql", "Fend_Db_Mysql");
class_alias("Fend\\Db\\MysqlPdo", "Fend_Db_MysqlPdo");

//class_alias("Fend\\Dispatcher\\BaseInterface", "Fend_Dispatcher_BaseInterface");
//class_alias("Fend\\Dispatcher\\Http", "Fend_Displatcher_Http");
//class_alias("Fend\\Dispatcher\\Websocket", "Fend_Displatcher_Websocket");

class_alias("Fend\\Funcs\\FendArray", "FendArray");
class_alias("Fend\\Funcs\\FendCheckdata", "FendCheckdata");
class_alias("Fend\\Funcs\\FendHttp", "FendHttp");
class_alias("Fend\\Funcs\\FendString", "FendString");
class_alias("Fend\\Funcs\\FendTimer", "FendTimer");

class_alias("Fend\\Image\\Zoom", "Fend_Image_Zoom");
class_alias("Fend\\Process\\Manage", "Fend_Process_manage");

class_alias("Fend\\Server\\BaseServer", "Fend_Server_BaseServer");
class_alias("Fend\\Server\\Http", "Fend_Server_Http");
class_alias("Fend\\Server\\Websocket", "Fend_Server_Websocket");

class_alias("Fend\\Controller", "Fend_Controller");

//class_alias("Fend\\Service\\Base", "Fend_Service_Base");
//class_alias("Fend\\Service\\Helper", "Fend_Service_Helper");
//class_alias("Fend\\Service\\Result", "Fend_Service_Result");

class_alias("Fend\\Debug", "Fend_Debug");
class_alias("Fend\\EagleEye", "Fend_EagleEye");
class_alias("Fend\\Exception", "Fend_Exception");
class_alias("Fend\\Log", "Fend_Log");
class_alias("Fend\\LogAgent", "Fend_LogAgent");
class_alias("Fend\\Validator", "Fend_Validator");

class_alias("Fend\\Read", "Fend_Read");
class_alias("Fend\\Write", "Fend_Write");
class_alias("Fend\\Task", "Fend_Task");

//class_alias("Fend\\Acl", "\Fend_Acl");
class_alias("Fend\\Fend", "\Fend");
class_alias("Fend\\Template", "\Router");
class_alias("Fend\\Di", "\Fend_Di");

