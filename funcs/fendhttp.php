<?php
namespace Fend\Funcs;

/**
 * 网络相关
 *
 **/
class FendHttp
{

    /**
     * get 通用处理方法
     * @param $name
     *
     * @return null|string
     */
    public static function doGet($name)
    {
        //$_GET = array_change_key_case($_GET);
        if (get_magic_quotes_gpc()) {
            if (isset($_GET[$name])) {
                return is_array($_GET[$name]) ? $_GET[$name] : trim($_GET[$name]);
            } else {
                return null;
            }
        } else {
            if (isset($_GET[$name])) {
                return is_array($_GET[$name]) ? $_GET[$name] : addslashes(trim($_GET[$name]));
            } else {
                return null;
            }
        }
    }

    /**
     * Post 通用处理方法
     * @param $name
     *
     * @return null|string
     */
    public static function doPost($name)
    {
        //$_POST = array_change_key_case($_POST);
        if (get_magic_quotes_gpc()) {
            if (isset($_POST[$name])) {
                return is_array($_POST[$name]) ? $_POST[$name] : trim($_POST[$name]);
            } else {
                return null;
            }
        } else {
            if (isset($_POST[$name])) {
                return is_array($_POST[$name]) ? $_POST[$name] : addslashes(trim($_POST[$name]));
            } else {
                return null;
            }
        }
    }

    /**
     * quest 通用处理方法
     * @param $name
     *
     * @return null|string
     */
    public static function doRequest($name)
    {
        //$_POST = array_change_key_case($_POST);
        if (get_magic_quotes_gpc()) {
            if (isset($_REQUEST[$name])) {
                return is_array($_REQUEST[$name]) ? $_REQUEST[$name] : trim($_REQUEST[$name]);
            } elseif (isset($_POST[$name])) {
                return is_array($_POST[$name]) ? $_POST[$name] : trim($_POST[$name]);
            } elseif (isset($_GET[$name])) {
                return is_array($_GET[$name]) ? $_GET[$name] : trim($_GET[$name]);
            } else {
                return null;
            }
        } else {
            if (isset($_REQUEST[$name])) {
                return is_array($_REQUEST[$name]) ? $_REQUEST[$name] : addslashes(trim($_REQUEST[$name]));
            } elseif (isset($_POST[$name])) {
                return is_array($_POST[$name]) ? $_POST[$name] : addslashes(trim($_POST[$name]));
            } elseif (isset($_GET[$name])) {
                return is_array($_GET[$name]) ? $_GET[$name] : addslashes(trim($_GET[$name]));
            } else {
                return null;
            }
        }
    }

    //写cookie信息
    public static function setRawCookie($name, $value, $life, $path = '/', $domain='')
    {
        if ($life == 0 || $life == '') {
            return setrawcookie($name, $value, time(), $path, '.' . $domain);
        } else {
            return setrawcookie($name, $value, time() + $life, $path, '.' . $domain);
        }
    }

    //写COOKIE信息
    public static function setCookie($name, $value, $life = 0, $path = '/', $domain = '')
    {
        $main   = \Fend\Di::factory()->get('cookiedomain');
        $domain = empty($domain)?$main:$domain;
        $life       = empty($life) ? time() + 365 * 24 * 3600 : time() + $life;
        //发送兼容Iframe报头
        static $p3p = null;
        $response = \Fend\Di::factory()->get('http_response');
        if (null === $p3p) {
            if (!empty($response)) {
                $response->header('P3P', 'CP=CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR');
                return $response->cookie($name, $value, $life, $path, $domain);
            } else {
                header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
                return setcookie($name, $value, $life, $path, $domain);
            }
        }
    }

    //获取跳转信息
    public static function getCookieMsg($domain = COOKIE_DOMAIN)
    {
        if (isset($_COOKIE['json'])) {
            $jAry = json_decode($_COOKIE['json'], true);
            $msg  = (isset($jAry['msg'])) ? $jAry['msg'] : '';
        } else {
            $msg = '';
        }
        $jAry['msg'] = '';
        $jsonStr     = json_encode($jAry);
        setcookie('json', $jsonStr, time() + 3600 * 24, '/', '.' . $domain);
        setcookie('json', $jsonStr, time() + 3600 * 24, '/');
        return $msg;
    }

    //直接跳转
    public static function redirect($url = '')
    {
        if (!$url) {
            if (isset($_SERVER['HTTP_REFERFER']) && $_SERVER['HTTP_REFERER']) {
                $url = $_SERVER['HTTP_REFERER'];
            } else {
                $url = '/';
            }
        }
        header("Location: " . $url);
    }

    public static function gof($info, $url, $frame = 'window')
    {
        echo("<script language='javascript'>alert('$info');$frame.location.href='$url'</script>");
    }

    /**
     * 获取内容的href标签
     *
     * @param string $content
     * @return array
     */
    public static function getHref($content)
    {
        $pat = '/<a(.*?)href="(.*?)"(.*?)>(.*?)<\/a>/i';
        preg_match_all($pat, $content, $hrefAry);
        return $hrefAry;
    }

    /**
     * 获取url含rar内容的href标签
     *
     * @param string $content
     * @return array
     */
    public static function getHrefRar($content)
    {
        $pat = '/<a(.*?)href="(.*?).rar"(.*?)>(.*?)<\/a>/i';
        preg_match_all($pat, $content, $hrefAry);
        return $hrefAry;
    }

    /**
     * 获取url含src内容的href标签
     *
     * @param string $content
     * @return array
     */
    public static function getHrefImg($content)
    {
        $pat = "/<img(.+?)src='(.+?)'/i";
        preg_match_all($pat, $content, $hrefAry);
        if (empty($hrefAry[0])) {
            $pat = "/<img(.+?)src=\"(.+?)\"/i";
            preg_match_all($pat, $content, $hrefAry);
        }
        return $hrefAry;
    }

    //获取客户端IP
    public static function getIp()
    {
        $ip = '0.0.0.0';
        if (isset($_SERVER['remote_addr'])) {
            $ip = $_SERVER['remote_addr'];
        } elseif (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $ip = getenv('REMOTE_ADDR');
        } elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } elseif (!empty($_SERVER['HTTP_HOST'])) {
            $ip = gethostbyname($_SERVER['HTTP_HOST']);
        }
        return $ip;
    }

    /**
     * 发送数据
     * @param string $url 数据报文
     * @param string $data 数据报文
     * @param string $method 数据报文
     * @return mixed
     */
    public static function getUrl($url, $data = '', $method = 'get', $time = 30000, $header = array(), $cert=array())
    {
        $rpc_id = \Fend\EagleEye::getNextRpcId();
        $start_time = microtime(true);

        //append header for eagle eye trace id and rpc id
        $header[] = "tal_trace_id: " . \Fend\EagleEye::getTraceId();
        $header[] = "tal_rpc_id: " . $rpc_id;
        $header[] = "tal_x_version: " . \Fend\EagleEye::getVersion();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        curl_setopt($ch, CURLOPT_NOSIGNAL, true); //支持毫秒级别超时设置
        if (!empty($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        //需要pem证书双向认证
        if (!empty($cert)) {
            //证书的类型
            curl_setopt(self::$ch, CURLOPT_SSLCERTTYPE, $cert['type']);
            //PEM文件地址
            curl_setopt(self::$ch, CURLOPT_SSLCERT, $cert['cert']);
            //私钥的加密类型
            curl_setopt(self::$ch, CURLOPT_SSLKEYTYPE, $cert['type']);
            //私钥地址
            curl_setopt(self::$ch, CURLOPT_SSLKEY, self::$conf['key']);
        }
        /*
          curl_setopt($ch, CURLOPT_SSLCERT, $this->config['cert']);
          curl_setopt($ch, CURLOPT_SSLCERTTYPE, $this->config['certtype']);
          curl_setopt($ch, CURLOPT_SSLKEY, $this->config['key']);

          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
          curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
         */
        if ($method == 'post') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 3000);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $time);

        $response = curl_exec($ch);

        $eagleeye_param = array(
            "x_name" => "http." . $method,
            "x_module" => "php_http_request",
            "x_duration" => round(microtime(true) - $start_time, 4),
            "x_action" => $url,
            "x_param" => $data,
            "x_file" => __FILE__,
            "x_line" => __LINE__,
            "x_dns_duration" => round(sprintf("%.f", curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME)), 4),
            "x_response_length" => strlen($response),
            "x_code" => curl_getinfo($ch, CURLINFO_HTTP_CODE)
        );

        //http error
        if ($response === false) {
            $eagleeye_param["x_name"] = $eagleeye_param["x_name"] . ".error";
            $eagleeye_param["x_code"] = curl_errno($ch);
            $eagleeye_param["x_msg"] = curl_error($ch);
            $eagleeye_param["x_backtrace"] = self::getTraceString();
            $eagleeye_param["x_response"] = $response;

            //record eagle eye
            \Fend\EagleEye::baseLog($eagleeye_param, $rpc_id);
            curl_close($ch);

            return false;
        } else {
            //success
            $eagleeye_param["x_response"] = $response;

            json_decode($response);
            if (json_last_error() == JSON_ERROR_NONE) {
                $return = json_decode($response, true);
            } else {
                $return = $response;
            }
            //record eagle eye
            \Fend\EagleEye::baseLog($eagleeye_param, $rpc_id);
            curl_close($ch);

            return $return;
        }
    }

    /**
     * http访问远程接口，返回array包括错误码及result
     * @param $url
     * @param string $data
     * @param string $method = 'get'
     * @param int $time = 30000ms
     * @param array $header = array()
     * @return array
     */
    public static function getHttp($url, $data = '', $method = 'get', $time = 30000, $header = array(), $cert=array())
    {
        $rpc_id         = \Fend\EagleEye::getNextRpcId();
        $start_time     = microtime(true);

        //append header for eagle eye trace id and rpc id
        $header[] = "tal_trace_id: " . \Fend\EagleEye::getTraceId();
        $header[] = "tal_rpc_id: " . $rpc_id;
        $header[] = "tal_x_version: " . \Fend\EagleEye::getVersion();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        curl_setopt($ch, CURLOPT_NOSIGNAL, true); //支持毫秒级别超时设置
        if (!empty($header)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }
        //需要pem证书双向认证
        if (!empty($cert)) {
            //证书的类型
            curl_setopt(self::$ch, CURLOPT_SSLCERTTYPE, $cert['type']);
            //PEM文件地址
            curl_setopt(self::$ch, CURLOPT_SSLCERT, $cert['cert']);
            //私钥的加密类型
            curl_setopt(self::$ch, CURLOPT_SSLKEYTYPE, $cert['type']);
            //私钥地址
            curl_setopt(self::$ch, CURLOPT_SSLKEY, self::$conf['key']);
        }
        if (strtoupper($method) == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 3000);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $time);

        $response   = curl_exec($ch);

        $eagleeye_param = array(
            "x_name"        => "http." . $method,
            "x_module"      => "php_http_request",
            "x_duration"    => round(microtime(true) - $start_time, 4),
            "x_action"      => $url,
            "x_param"       => $data,
            "x_file"        => __FILE__,
            "x_line"        => __LINE__,
            "x_dns_duration"=> round(sprintf("%.f", curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME)), 4),
            "x_response_length" => strlen($response),
            "x_code" => curl_getinfo($ch, CURLINFO_HTTP_CODE)
        );

        if (curl_errno($ch) === 0) {
            \Fend\EagleEye::baseLog($eagleeye_param, $rpc_id);
            $eagleeye_param["x_response"] = $response;

            json_decode($response);
            if (json_last_error() == JSON_ERROR_NONE) {
                $return = json_decode($response, true);
            } else {
                $return = $response;
            }
            curl_close($ch);
            return array(0, $return);
        } else {
            $eagleeye_param["x_name"] = $eagleeye_param["x_name"] . ".error";
            $eagleeye_param["x_code"] = curl_errno($ch);
            $eagleeye_param["x_msg"] = curl_error($ch);
            $eagleeye_param["x_backtrace"] = self::getTraceString();
            $eagleeye_param["x_response"] = $response;

            //record eagle eye
            \Fend\EagleEye::baseLog($eagleeye_param, $rpc_id);
            curl_close($ch);
            return array($eagleeye_param["x_code"], $eagleeye_param["x_msg"]);
        }
    }

    /**
     * 获取本次调用堆栈层级文字描述
     * @return string
     */
    public static function getTraceString()
    {
        $result = "";
        $line = 0;
        $backtrace = debug_backtrace();
        foreach ($backtrace as $btrace) {
            if (!empty($btrace["file"]) && !empty($btrace["line"])) {
                $result .= "#" . $line . " " . $btrace["file"] . "(" . $btrace["line"] . ")" . " " . $btrace["class"] . $btrace["type"] . $btrace["function"] . "(" . http_build_query($btrace) . ")".PHP_EOL;
                $line++;
            }
        }
        return $result;
    }

    /**
     * 跳转-2header
     * @param $url
     */
    public static function getHeader($url)
    {
        $response = \Fend\Di::factory()->get('http_response');
        if (!empty($response)) {
            $response->header("location", $url);
            $response->status('301');
        } else {
            header("location:{$url}");
        }
        return;
    }
    /**
     * 设置header
     * @param $string
     */
    public static function settHeader($string)
    {
        $response = \Fend\Di::factory()->get('http_response');
        if (!empty($response)) {
            $response->header($string);
        } else {
            header($string);
        }
        return;
    }
    /**
     * 跳转-2:关闭当前窗口
     * @param $str
     */
    public static function getClose($str)
    {
        echo "<SCRIPT LANGUAGE='JavaScript'>alert('" . $str . "');window.close();</SCRIPT>";
        return;
    }

    /**
     * 跳转-3:Script
     * @param $url
     */
    public static function getScript($url)
    {
        echo "<Script language='javascript'><!-- window.location='" . $url . "'; --></Script>";
        return;
    }

    /**
     * 用户IP
     * @return mixed
     */
    public static function getUserIP()
    {
        if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $user_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else {
            $user_ip = $_SERVER["REMOTE_ADDR"];
        }
        return $user_ip;
    }

    /**
     * @param null $url
     *  302重定向 默认定向到来访页面
     */
    public static function doBreak($url=null)
    {
        $response = \Fend\Di::factory()->get('http_response');
        !$url && $url=isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/';
        if (!empty($response)) {
            $response->header("location", $url);
            $response->status('302');
        } else {
            header("location:{$url}");
        }
        return;
    }
}
