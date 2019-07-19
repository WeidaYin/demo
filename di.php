<?php

namespace Fend;

/**
 * 全局变量管理
 * User: gary
 * Date: 2017/11/20
 * Time: 下午5:49
 */
class Di extends Fend
{
    protected $BI = array();

    public static function factory()
    {
        static $_obj = null;
        //是否需要重新连接
        if (empty($_obj)) {
            $_obj = new self();
        }
        return $_obj;
    }

    /**
     * 存储对象或数据到di内
     * @param $key
     * @param $val
     */
    public function set($key, $val)
    {
        $this->BI[$key] = $val;
    }

    //原来的set如果存在禁止设置
    //现在的set已经可以覆盖，这个函数就没用了
    //留着是为了兼容
    public function reSet($key, $val)
    {
        $this->BI[$key] = $val;
    }

    /**
     * 获取全部di内容
     * @return array
     */
    public function getList()
    {
        return $this->BI;
    }

    /**
     * 获取对象或数据
     * @param $key
     * @return mixed|string
     */
    public function get($key)
    {
        return isset($this->BI[$key]) ? $this->BI[$key] : '';
    }

    /**
     * 设置request对象
     * @param Request $request
     */
    public function setRequest(\Fend\Request $request)
    {
        $this->BI["request"] = $request;
    }

    /**
     * 获取request对象
     * @return \Fend\Request
     */
    public function getRequest()
    {
        return $this->BI["request"];
    }

    /**
     * set Response
     * @param Response $request
     */
    public function setResonse(\Fend\Response $response)
    {
        $this->BI["response"] = $response;
    }

    /**
     * 获取response对象
     * @return \Fend\Response
     */
    public function getResponse()
    {
        return $this->BI["response"];
    }
}
