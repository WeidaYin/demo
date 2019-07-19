<?php
namespace Fend\Db;

/**
 * @version $Id: Mysqli.php 1878 2013-11-12 05:28:15Z liushuai $
 * */
class Mysql extends \Fend\Fend
{
    protected $_db=null;//连接db对象
    protected $_instant_name = "";
    protected $_cfg=null;//连接配置信息

    public static $in=array();

    public static function Factory($r, $db='')
    {
        self::$in[$db][$r] = new self($r, $db);
        return self::$in[$db][$r];
    }

    /**
     * 连接服务器
     * @return bool
     */
    public function connect()
    {
        $retval = $this->_db->real_connect($this->_cfg['host'], $this->_cfg['user'], $this->_cfg['pwd'], $this->_cfg['name'], $this->_cfg['port']);
        /**
         * 连接服务器成功后，设置编码格式
         */
        if ($retval) {
            $this->_db->query("SET character_set_connection={$this->_cfg['lang']},character_set_results={$this->_cfg['lang']},character_set_client=binary,sql_mode='';");
        }
        return $retval;
    }

    /**
     * 检查数据库连接,是否有效，无效则重新建立
     */
    protected function checkConnection()
    {
        if (!@$this->_db->ping()) {
            $this->close();
            return $this->connect();
        }
        return true;
    }

    /**
     * SQL错误信息
     * @param $sql
     * @return string
     */
    protected function errorMessage($sql)
    {
        $msg = $this->_db->error . "<hr />$sql<hr />\n";
        $msg .= "Server: {$this->_cfg['host']}:{$this->_cfg['port']}. <br/>\n";
        if ($this->_db->connect_errno) {
            $msg .= "ConnectError[{$this->_db->connect_errno}]: {$this->_db->connect_error}<br/>\n";
        }
        $msg .= "Message: {$this->_db->error} <br/>\n";
        $msg .= "Errno: {$this->_db->errno}\n";
        return $msg;
    }

    /**
     * @param $call
     * @param $params
     * @return bool|mixed
     */
    protected function tryReconnect($call, $params)
    {
        $result = false;
        for ($i = 0; $i < 2; $i++) {
            $result = call_user_func_array($call, $params);
            /**
             * 请求失败，检查错误
             */
            if ($result === false) {
                if ($this->_db->errno == 2013 or $this->_db->errno == 2006 or ($this->_db->errno == 0 and !$this->_db->ping())) {
                    $r = $this->checkConnection();
                    $call[0] = $this->_db;
                    if ($r === true) {
                        continue;
                    }
                } else {
                    self::showError(__CLASS__ . " SQL Error", $this->errorMessage($params[0]));
                    return false;
                }
            }
            break;
        }
        return $result;
    }

    /**
     * 初始化Mysql对象并进行连接服务器尝试
     * @param $r
     * @param $db
     */
    public function __construct($r, $db)
    {
        $dblist = \Fend\Config::get('db');
        if (empty($dblist)) {
            \Fend\Di::factory()->set("debug_error", \Fend\Exception::Factory("dbconfig  is not set")->ShowTry(1, 1));
            return;
        }
        $db = empty($db)?array_keys($dblist)[0]:$db;
        $this->_instant_name = $db;
        $this->_cfg = $dblist[$db][$r];
        $this->_cfg['port'] = empty($this->_cfg['port'])?3306:$this->_cfg['port'];
        $this->_db = mysqli_init();
        $this->_db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 30);

        //record connect time
        $starttime = microtime(true);
        if (!$this->connect()) {
            //prepare info of error
            $eagleeye_param = array(
                "x_name" => "mysql.connect",
                "x_host" => $this->_cfg['host'],
                "x_module" => "php_mysql_connect",
                "x_duration" => round(microtime(true) - $starttime, 4),
                "x_instance_name" => $r,
                "x_file" => __FILE__,
                "x_line" => __LINE__,
            );

            if (mysqli_connect_errno()) {
                //error
                $eagleeye_param["x_name"] = "mysql.connect.error";
                $eagleeye_param["x_code"] = mysqli_connect_errno();
                $eagleeye_param["x_msg"] = mysqli_connect_error();

                \Fend\EagleEye::baseLog($eagleeye_param);
                self::showError("Connect failed: " . mysqli_connect_error());

                throw new \Exception(mysqli_connect_error(), -mysqli_connect_errno()); //新加
                return; //新加
            }
            //record log
            \Fend\EagleEye::baseLog($eagleeye_param);

            $this->_db->query("SET character_set_connection={$this->_cfg['lang']},character_set_results={$this->_cfg['lang']},character_set_client=binary,sql_mode='';");
        }
    }

    public function setTimeout($timeout = 1)
    {
        $this->_db->options(MYSQLI_OPT_CONNECT_TIMEOUT, $timeout);
    }

    /**
     * 返回数据表对象
     */
    public function getDb()
    {
        return $this->_db;
    }

    /**
     * 选中并打开数据库
     *
     * @param string $name 重新选择数据库,为空时选择默认数据库
     * */
    public function useDb($name = null)
    {
        if (null === $name) {
            $name = $this->_cfg['name'];
        }
        if (!$this->_db->select_db($name)) {
            $this->showError("Can't use {$name}");
        }
    }

    /**
     * 获取记录集合,当记录行为一个字段时输出变量结果 当记录行为多个字段时输出一维数组结果变量
     *
     * @param  string  $sql 标准查询SQL语句
     * @param  integer $r   是否合并数组
     * @return string|array
     * */
    public function get($sql, $r = null)
    {
        $rs = self::query($sql, $r);
        if(!$rs){
            return null;
        }

        $rs = self::fetch($rs);
        if (!empty($r) && !empty($rs)) {
            $rs = join(',', $rs);
        }
        return $rs;
    }

    /**
     * 返回查询记录的数组结果集
     *
     * @param  string  $sql 标准SQL语句
     * @return array
     * */
    public function getall($sql)
    {
        $item = array();
        $q    = self::query($sql);
        while ($rs   = self::fetch($q)) {
            $item[] = $rs;
        }
        return $item;
    }

    /**
     * 获取插入的自增ID
     *
     * @return integer
     * */
    public function getId()
    {
        return $this->_db->insert_id;
    }

    /**
     * 发送查询
     *
     * @param  string  $sql 标准SQL语句
     * @return bool|mysqli_result
     **/
    public function query($sql)
    {
        //$q= $this->_db->query($sql) or self::showMsg("Query to [{$sql}] ");
        //free the result
        if (empty($this->_db)) {
            return false;
        }

        while (mysqli_more_results($this->_db) && mysqli_next_result($this->_db)) {
            $dummyResult = mysqli_use_result($this->_db);
            if ($dummyResult instanceof mysqli_result) {
                mysqli_free_result($this->_db);
            }
        }

        //record query
        $startTime  = microtime(true);
        $query = $this->tryReconnect(array($this->_db, 'query'), array($sql));
        $costTime   = round(microtime(true) - $startTime, 4);
        //prepare info of error
        $eagleeyeParam = array(
            "x_name" => "mysql.request",
            "x_host" => $this->_cfg['host'],
            "x_module" => "php_mysql_query",
            "x_duration" => $costTime,
            "x_instance_name" => $this->_instant_name,
            "x_file" => __FILE__,
            "x_line" => __LINE__,
            "x_action" => $sql,
        );

        if (isset($this->_DEBUG) && $this->_DEBUG == 1) {
            $stime                   = $etime                   = 0;
            $m                       = explode(' ', microtime());
            $_SERVER['REQUEST_TIME'] = !empty($_SERVER['request_time']) ? $_SERVER['request_time'] : $_SERVER['REQUEST_TIME'];
            $stime                   = number_format(($m[1] + $m[0] - $_SERVER['REQUEST_TIME']), 8) * 1000;
            $query                   = $this->_db->query($sql);
            $m                       = explode(' ', microtime());
            $etime                   = number_format(($m[1] + $m[0] - $_SERVER['REQUEST_TIME']), 8) * 1000;
            $sqltime                 = round(($etime - $stime), 8);
            $info                    = $this->_db->info;
            $explain                 = array();
            if ($query && preg_match("/^(select )/i", $sql)) {
                $key = md5($sql);
                $qs = $this->_db->query('EXPLAIN ' . $sql);
                while ($rs = self::fetch($qs)) {
                    $explain[] = $rs;
                }
                if (!empty($explain)) {
                    $this->cfg['dbdebug'][$key]['sql']      = $sql;
                    $this->cfg['dbdebug'][$key]['info']     = $info;
                    $this->cfg['dbdebug'][$key]['explain']  = $explain;
                    $this->cfg['dbdebug'][$key]['time']     = $sqltime;
                }
            }
            return $query;
        } elseif (!$query) {
            //error
            $eagleeyeParam["x_name"] = "mysql.request.error";
            $eagleeyeParam["x_code"] = mysqli_connect_errno();
            $eagleeyeParam["x_msg"] = mysqli_connect_error();
            //$eagleeye_param["x_backtrace"] = implode("\n",debug_backtrace());
            \Fend\EagleEye::baseLog($eagleeyeParam);
            self::showError("Query to [{$sql}] ");
        }
        \Fend\EagleEye::baseLog($eagleeyeParam);
        return $query;
    }

    /**
     * 返回字段名为索引的数组集合
     *
     * @param  results $q 查询指针
     * @return array
     * */
    public function fetch($q)
    {
        return $q->fetch_assoc();
    }

    /**
     * 格式化MYSQL查询字符串
     *
     * @param  string $str 待处理的字符串
     * @return string
     * */
    public function escape($str)
    {
        return $this->_db->real_escape_string($str);
    }

    /**
     * 关闭当前数据库连接
     *
     * @return bool
     * */
    public function close()
    {
        return $this->_db->close();
    }

    /**
     * 取得数据库中所有表名称
     *
     * @param  string $db 数据库名,默认为当前数据库
     * @return array
     * */
    public function getTableList($db = null)
    {
        $item   = array();
        $q      = self::query('SHOW TABLES ' . (null == $db ? null : 'FROM ' . $db));
        while ($rs = self::fetchs($q)) {
            $item[] = $rs[0];
        }
        return $item;
    }

    /**
     * 获取表中所有字段及属性
     *
     * @param  string $tb 表名
     * @return array
     * */
    public function getDbFields($tb)
    {
        $item   = array();
        $q      = self::query("SHOW FULL FIELDS FROM {$tb}"); //DESCRIBE users
        while ($rs = self::fetch($q)) {
            $item[] = $rs;
        }
        return $item;
    }

    /**
     * 生成表的标准Create创建SQL语句
     *
     * @param  string $tb 表名
     * @return string
     * */
    public function sqlTB($tb)
    {
        $q  = self::query("SHOW CREATE TABLE {$tb}");
        $rs = self::fetchs($q);
        return $rs[1];
    }

    /**
     * 整理优化表
     * 注意: 多个表采用多个参数进行传入
     *
     * Example: setTB('table0','table1','tables2',...)
     * @param string 表名称可以是多个
     * @return boolean
     * */
    public function optimizeTable()
    {
        $args = func_get_args();
        foreach ($args as &$v) {
            self::query("OPTIMIZE TABLE {$v};");
        }
    }

    /**
     * 返回键名为序列的数组集合
     *
     * @param  \mysqli_result $q 资源标识指针
     * @return array
     * */
    public function fetchs($q)
    {
        return $q->fetch_row();
    }

    /**
     * 取得结果集中行的数目
     *
     * @param  \mysqli_result $q 资源标识指针
     * @return int
     * */
    public function reRows($q)
    {
        return $q->num_rows;
    }

    /**
     * 取得被INSERT、UPDATE、DELETE查询所影响的记录行数
     *
     * @return int
     * */
    public function afrows()
    {
        return $this->_db->affected_rows;
    }

    /**
     * 释放结果集缓存
     *
     * @param  \mysqli_result $q 资源标识指针
     * */
    public function refree($q)
    {
        $q->free_result();
    }

    /**
     * 启动事务处理
     * @return bool
     */
    public function start()
    {
        $this->_db->query("set autocommit=0");
        return $this->_db->query('START TRANSACTION');
    }

    /**
     * 提交事务处理
     * @return bool
     */
    public function commit()
    {
        $this->_db->query("set autocommit=1");
        return $this->_db->query('COMMIT');
    }

    /**
     * 事务回滚
     * @return bool
     */
    public function back()
    {
        $this->_db->query("set autocommit=1");
        return $this->_db->query('ROLLBACK');
    }

    /**
     * 设置异常消息 可以通过try块中捕捉该消息
     *
     * @param  string $str debug错误信息
     * */
    public function showError($str)
    {
        if (isset($this->_DEBUG) && $this->_DEBUG == 1) {
            \Fend\Di::factory()->set("debug_error", \Fend\Exception::Factory('mysql-connet:'.$this->_db->error)->ShowTry(1, 1));
        } elseif (defined('DBLOG')) {
            $_tmp='';
            if (isset($_SERVER['SERVER_ADDR'])) {
                $_tmp.='['.$_SERVER['SERVER_ADDR'].']';
            }
            if (isset($_SERVER['REQUEST_URI'])) {
                $_tmp.='['.$_SERVER['REQUEST_URI'].']';
            }
            if (!empty($_tmp)) {
                $_tmp.="\n";
            }
            \Fend\Log::write(date("Y-m-d H:i:s > ").$_tmp.$str.$this->_db->error."\n\n", 'mysql-errr.log');
        }
    }

    public function getErrorInfo()
    {
        return array(
            "msg"  => $this->_db->error,
            "code" => $this->_db->errno,
        );
    }
}
