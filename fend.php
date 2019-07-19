<?php
namespace Fend;

/**
 * 路由器 核心加载器
 * 模块需要通过该对象进行激活
 * @Author  gary <gary@100tal.com>
 * @version $Id: Core.php  $
 * */

abstract class Fend
{
    const FEND_VERSION = '1.0.1';

    /**
     * 魔法方法: 动态载入全局变量 当变量不存在时试图创建之
     *
     * @param  string $k 变量名称
     * @return variable  变量内容
     * */
    public function &__get($k)
    {
        !isset($GLOBALS['_' . $k]) && self::__set($k);
        return $GLOBALS['_' . $k];
    }

    /**
     * 魔法方法: 动态创建全局变量 被成功创建的变量保存在GLOBALS中
     *
     * Example : $this->var1=123 对象中var1不存在时自动创建到$GLOBALS['_var1']中
     * @param string $k 变量名称
     * @param string $v 变量值
     * */
    public function __set($k, $v = null)
    {
        if (!isset($GLOBALS['_' . $k])) {//初始化系统变量
            if (isset($this->FD_REG_FUNC[$k])) {
                $v = $this->FD_REG_FUNC[$k]();
            } else {
                $GLOBALS['_' . $k] = &$v;
            }
        }
        $GLOBALS['_' . $k] = $v; //设置临时变量
    }

    /**
     * 魔法方法: 检测被动态创建的变量也可以是全局变量GLOBALS
     *
     * Example : isset($this->var1) = isset($GLOBALS['_var1'])
     * @param string $k 变量名称
     * @return string
     * */
    public function __isset($k)
    {
        return isset($GLOBALS['_' . $k]);
    }

    /**
     * 魔法方法: 释放变量资源
     *
     * Example : unset($this->var1) = unset($GLOBALS['_var1'])
     * @param string $k 变量名称
     * */
    public function __unset($k)
    {
        unset($GLOBALS['_' . $k]);
    }

}
