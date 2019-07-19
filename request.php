<?php

namespace Fend;

/**
 * Request封装
 * Class Request
 * @package Fend
 */

class Request
{

    private $_type = "fpm";

    private $_post = [];
    private $_get = [];
    private $_server = [];
    private $_cookie = [];
    private $_file = [];

    /**
     * Request constructor.
     * @param string $type 可选项fpm,swoole_http
     * @throws \Exception
     */
    public function __construct($type = "fpm")
    {
        if ($type === "fpm") {
            $this->_post   = $_POST;
            $this->_get    = $_GET;
            $this->_server = $_SERVER;
            $this->_cookie = $_COOKIE;
            $this->_file   = $_FILES;

        } elseif ($type === "swoole_http") {
            /**
             * @var \Swoole\Http\Request
             */
            $request = \Fend\Di::factory()->get("http_request");

            if (!$request) {
                throw new \Exception("swoole request 获取失败", 11);
            }

            $this->_post   = $request->post;
            $this->_get    = $request->get;
            $this->_server = $request->server;
            $this->_cookie = $request->cookie;
            $this->_file   = $request->file;

        } else {
            throw new \Exception("未知request类型", 12);
        }
    }

    /**
     * 获取post参数
     * @param string $name
     * @return array|mixed|string
     */
    public function post($name = "")
    {
        if ($name == '') {
            return $this->_post;
        }

        if (!isset($this->_post[$name])) {
            return '';
        }

        return $this->_post[$name];
    }

    /**
     * 获取get参数
     * @param string $name
     * @return array|mixed|string
     */
    public function get($name = "")
    {
        if ($name == '') {
            return $this->_get;
        }

        if (!isset($this->_get[$name])) {
            return '';
        }

        return $this->_get[$name];
    }

    /**
     * 获取Server信息
     * @param string $name
     * @return array|mixed|string
     */
    public function server($name = "")
    {
        if ($name == '') {
            return $this->_server;
        }

        if (!isset($this->_server[$name])) {
            return '';
        }

        return $this->_server[$name];
    }

    /**
     * 获取cookie
     * @param string $name
     * @return array|mixed|string
     */
    public function cookie($name = "")
    {
        if ($name == '') {
            return $this->_cookie;
        }

        if (!isset($this->_cookie[$name])) {
            return '';
        }

        return $this->_cookie[$name];
    }

    /**
     * 获取提交file
     * @param string $name
     * @return array|mixed|string
     */
    public function file($name = "")
    {
        if ($name == '') {
            return $this->_file;
        }

        if (!isset($this->_file[$name])) {
            return '';
        }

        return $this->_file[$name];
    }

    /**
     * 获取body内容
     * @return false|string|void
     * @throws \Exception
     */
    public function getRaw()
    {
        if ($this->_type === "fpm") {
            return file_get_contents("php://input");

        } elseif ($this->_type === "swoole_http") {
            /**
             * @var \Swoole\Http\Request
             */
            $request = \Fend\Di::factory()->get("http_request");
            if (!$request) {
                throw new \Exception("swoole request 获取失败", 11);
            }
            return $request->rawContent();

        } else {
            throw new \Exception("未知request类型", 12);
        }
    }

}