<?php

namespace Fend;

class Response
{
    private $_type = "fpm";

    /**
     * Response constructor.
     * @param string $type 可选项fpm,swoole_http
     * @throws \Exception
     */
    public function __construct($type = "fpm")
    {
        $this->_type = $type;
    }

    /**
     * 设置返回的header
     * @param $key
     * @param $value
     * @return bool
     * @throws \Exception
     */
    public function header($key, $value)
    {
        if ($this->_type === "fpm") {
            return header($key . ": " . $value);
        } elseif ($this->_type == "swoole_http") {

            $response = \Fend\Di::factory()->get("http_response");

            if (!$response) {
                throw new \Exception("swoole request 获取失败", 11);
            }

            return $response->header($key, $value);

        } else {
            throw new \Exception("未知request类型", 12);
        }
    }

    /**
     * 设置返回的cookie
     * @param string $key
     * @param string $value
     * @param int $expire
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httponly
     * @return bool
     * @throws \Exception
     */
    public function cookie(string $key, string $value = '', int $expire = 0, string $path = '/', string $domain = '', bool $secure = false, bool $httponly = false)
    {
        if ($this->_type === "fpm") {
            return setcookie($key, $value, $expire, $path, $domain, $secure, $httponly);
        } elseif ($this->_type == "swoole_http") {

            $response = \Fend\Di::factory()->get("http_response");

            if (!$response) {
                throw new \Exception("swoole request 获取失败", 11);
            }

            return $response->cookie($key, $value, $expire, $path, $domain, $secure, $httponly);

        } else {
            throw new \Exception("未知request类型", 12);
        }
    }

    /**
     * 设置返回http code，并附加header
     * @param int $httpCode
     * @return bool
     * @throws \Exception
     */
    public function status($httpCode)
    {
        if ($this->_type === "fpm") {
            return http_response_code($httpCode);
        } elseif ($this->_type == "swoole_http") {

            $response = \Fend\Di::factory()->get("http_response");

            if (!$response) {
                throw new \Exception("swoole request 获取失败", 11);
            }
            return $response->status($httpCode);

        } else {
            throw new \Exception("未知request类型", 12);
        }

    }

    /**
     * 跳转到指定网址
     * @param string $url
     * @param int $code
     * @throws \Exception
     */
    public function redirect($url, $code = 302)
    {
        if ($this->_type === "fpm") {

            switch ($code) {
                case 301:
                    header("HTTP/1.1 301 Moved Permanently");
                    header("Location: " . $url);
                    break;
                case 302:
                    header("Location: " . $url);
                    break;
                default:
                    throw new \Exception("未知redirect code类型", 23);
            }

        } elseif ($this->_type == "swoole_http") {

            $response = \Fend\Di::factory()->get("http_response");

            if (!$response) {
                throw new \Exception("swoole request 获取失败", 11);
            }

            switch ($code) {
                case 301:
                    $response->redirect($url, 301);
                    break;
                case 302:
                    $response->redirect($url, 302);
                    break;
                default:
                    throw new \Exception("未知redirect code类型", 23);
            }
        } else {
            throw new \Exception("未知request类型", 12);
        }

        //end rest code
        $this->break("");

    }

    /**
     * 返回json结果，并设置json header
     * @param $data
     * @param mixed ...$option
     * @return mixed
     * @throws \Exception
     */
    public function json($data, ...$option)
    {
        $this->header("Content-type", "application/json; charset=utf-8");
        array_unshift($option, $data);
        return call_user_func_array("json_encode", $option);
    }

    /**
     * 结束后续代码，返回指定结果
     * @param string $data 返回的结果，仅接受字符串
     * @throws \Exception
     */
    public function break($data)
    {
        throw new \Fend\ExitException($data);
    }

}