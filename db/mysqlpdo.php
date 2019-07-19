<?php
namespace Fend\Db;

/**
 * @version $Id: Mysqli.php 1878 2013-11-12 05:28:15Z liushuai $
 **/
class MysqlPDO extends \Fend\Fend
{
    protected $_db = null;//连接池标识
    protected $_cfg = null;//连接配置信息
    public $dbError = false;//是否开启异常抛出
    protected $options = array(
        \PDO::ATTR_CASE              => \PDO::CASE_LOWER,
        \PDO::ATTR_ERRMODE           => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_ORACLE_NULLS      => \PDO::NULL_NATURAL,
        \PDO::ATTR_STRINGIFY_FETCHES => false,
    );

    public static $in=array();
    public static function Factory($r, $db='')
    {
        $dbconfig = \Fend\Config::get('db');

        if (empty($dbconfig)) {
            self::showError("请添加数据库配置文件");
            return;
        }
        $db = empty($db)?array_keys($dbconfig)[0]:'';
        self::$in[$db][$r]=new self($r, $db);
        return self::$in[$db][$r];
    }

    /**
     * 初始化Mysql对象并进行连接服务器尝试
     * \Fend\Db\MysqlPdo constructor.
     * @param $r
     * @param $db
     */
    public function __construct($r, $db)
    {
        $dbconfig = \Fend\Config::get('db');

        $this->_cfg = $dbconfig[$db][$r];
        $this->_cfg['port'] = empty($this->_cfg['port']) ? 3306 : $this->_cfg['port'];
        $this->_cfg['dsn'] = self::parseDsn($this->_cfg);
        try {
            $this->_db = new \PDO($this->_cfg['dsn'], $this->_cfg['user'], $this->_cfg['pwd'], $this->options);
        } catch (\PDOException $e) {
            self::showMsg("Connect failed: " . $e->getMessage());
        }
    }

    /**
     * 解析pdo连接的dsn信息
     * @access public
     * @param array $config 连接信息
     * @return string
     */
    protected function parseDsn($config)
    {
        $dsn = 'mysql:dbname=' . $config['name'] . ';host=' . $config['host'];
        if (!empty($config['port'])) {
            $dsn .= ';port=' . $config['port'];
        } elseif (!empty($config['socket'])) {
            $dsn .= ';unix_socket=' . $config['socket'];
        }
        if (!empty($config['lang'])) {
            //为兼容各版本PHP,用两种方式设置编码
            $this->options[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES ' . $config['lang'];
            $dsn .= ';charset=' . $config['lang'];
        }
        return $dsn;
    }


    public function setTimeout($timeout=30)
    {
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
     **/
    public function useDb($name=null)
    {
        null===$name && $name=$this->_cfg['name'];
        $this->_db->query("use {$name}") or self::showMsg("Can't use {$name}");
    }

    /**
     * 获取记录集合,当记录行为一个字段时输出变量结果 当记录行为多个字段时输出一维数组结果变量
     *
     * @param  string  $sql 标准查询SQL语句
     * @param  integer $r   是否合并数组
     * @return string|array
     **/
    public function get($sql, $r=null)
    {
        $rs=self::fetch(self::query($sql, $r));
        null !== $r and $rs = @join(',', $rs);
        return $rs;
    }

    /**
     * 返回查询记录的数组结果集
     *
     * @param  string $sql 标准SQL语句
     * @return array
     **/
    public function getall($sql)
    {
        $item = array();
        $q = self::query($sql);
        while ($rs = self::fetch($q)) {
            $item[] = $rs;
        }
        return $item;
    }

    /**
     * 获取插入的自增ID
     *
     * @return integer
     **/
    public function getId()
    {
        return $this->_db->lastInsertId();
    }

    /**
     * 发送查询
     *
     * @param  string $sql 标准SQL语句
     * @return PDOStatement | bool
     */
    public function query($sql)
    {
        return $this->_db->query($sql) or self::showMsg("Query to [{$sql}] ");
    }

    /**
     * 返回字段名为索引的数组集合
     *
     * @param  object $q 查询指针
     * @return array
     **/
    public function fetch($q)
    {
        return $q->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * 格式化MYSQL查询字符串
     *
     * @param  string $str 待处理的字符串
     * @return string
     **/
    public function escape($str)
    {
        return addslashes($str);
    }

    /**
     * 关闭当前数据库连接
     *
     * @return bool
     **/
    public function close()
    {
        return $this->_db = null;
    }

    /**
     * 取得数据库中所有表名称
     *
     * @param  string $db 数据库名,默认为当前数据库
     * @return array
     **/
    public function getTB($db=null)
    {
        $item=array();
        $q=self::query('SHOW TABLES '.(null==$db ? null : 'FROM '.$db));
        while ($rs=self::fetchs($q)) {
            $item[]=$rs[0];
        }
        return $item;
    }

    /**
     * 根据已知的表复制一张新表,如有自增ID时自增ID重置为零
     * 注意: 仅复制表结构包括索引配置,而不复制记录
     *
     * @param  string  $souTable 源表名
     * @param  string  $temTable 目标表名
     * @param  boolean $isdel    是否在处理前检查并删除目标表
     * @return boolean
     **/
    public function copyTB($souTable, $temTable, $isdel=false)
    {
        $isdel && self::query("DROP TABLE IF EXISTS `{$temTable}`");//如果表存在则直接删除
        $temTable_sql=self::sqlTB($souTable);
        $temTable_sql=str_replace('CREATE TABLE `'.$souTable.'`', 'CREATE TABLE IF NOT EXISTS `'.$temTable.'`', $temTable_sql);

        $this->_cfg['lang']!='utf-8' && $temTable_sql=iconv($this->_cfg['lang'], 'utf-8', $temTable_sql);

        $result=self::query($temTable_sql);//创建复制表
        stripos($temTable_sql, 'AUTO_INCREMENT') && self::query("ALTER TABLE `{$temTable}` AUTO_INCREMENT =1");//更新复制表自增ID
        return $result;
    }

    /**
     * 获取表中所有字段及属性
     *
     * @param  string $tb 表名
     * @return array
     **/
    public function getFD($tb)
    {
        $item=array();
        $q=self::query("SHOW FULL FIELDS FROM {$tb}");//DESCRIBE users
        while ($rs=self::fetch($q)) {
            $item[]=$rs;
        }
        return $item;
    }

    /**
     * 生成表的标准Create创建SQL语句
     *
     * @param  string $tb 表名
     * @return string
     **/
    public function sqlTB($tb)
    {
        $q=self::query("SHOW CREATE TABLE {$tb}");
        $rs=self::fetchs($q);
        return $rs[1];
    }

    /**
     * 如果表存在则删除
     *
     * @param  string $tables 表名称
     * @return boolean
     **/
    public function delTB($tables)
    {
        return self::query("DROP TABLE IF EXISTS `{$tables}`");
    }

    /**
     * 整理优化表
     * 注意: 多个表采用多个参数进行传入
     *
     * Example: setTB('table0','table1','tables2',...)
     * @param string 表名称可以是多个
     * @return boolean
     **/
    public function setTB()
    {
        $args=func_get_args();
        foreach ($args as &$v) {
            self::query("OPTIMIZE TABLE {$v};");
        }
    }

    /**
     * 生成REPLACE|UPDATE|INSERT等标准SQL语句
     *
     * @param  string $arr    操纵数据库的数组源
     * @param  string $dbname 数据表名
     * @param  string $type   SQL类型 UPDATE|INSERT|REPLACE|IFUPDATE
     * @param  string $where  where条件
     * @return string         一个标准的SQL语句
     **/
    public function subSQL($arr, $dbname, $type='update', $where=null, $duplicate=array())
    {
        $tem=array();
        if (!empty($arr) && is_array($arr)) {
            foreach ($arr as $k=>&$v) {
                $v = self::escape($v);
                if (preg_match("/`/i", $v)) {
                    $tem[$k]="`{$k}`={$v}";
                } else {
                    $tem[$k]="`{$k}`='{$v}'";
                }
            }
        }
        switch (strtolower($type)) {
            case 'insert'://插入
                $sql="INSERT INTO {$dbname} SET ".join(',', $tem);
                break;
            case 'replace'://替换
                $sql="REPLACE INTO {$dbname} SET ".join(',', $tem);
                break;
            case 'update'://更新
                $sql="UPDATE {$dbname} SET ".join(',', $tem)." WHERE {$where}";
                break;
            case 'ifupdate'://存在则更新记录
                $tem    = join(',', $tem);
                if (!empty($duplicate)) {
                    foreach ($duplicate as $ks=>&$vs) {
                        $ifitem[$ks]="`{$ks}`={$vs}";
                    }
                    $ifitem = join(',', $ifitem);
                    $sql = "INSERT INTO {$dbname} SET {$tem} ON DUPLICATE KEY UPDATE {$ifitem}";
                } else {
                    $sql = "INSERT INTO {$dbname} SET {$tem} ON DUPLICATE KEY UPDATE {$tem}";
                }
                break;
            case 'delete'://存在则更新记录
                $sql="delete FROM {$dbname} WHERE  {$where}";
                break;
            default:
                $sql=null;
                break;
        }
        return $sql;
    }

    /**
     * 生成REPLACE|UPDATE|INSERT等标准SQL语句 同subsql函数相似但该函数会直接执行不返回SQL
     *
     * @param  string $arr 操纵数据库的数组源
     * @param  string $dbname 数据表名
     * @param  string $type SQL类型 UPDATE|INSERT|REPLACE|IFUPDATE
     * @param  string $where where条件
     * @return boolean
     **/
    public function doQuery($arr, $dbname, $type='update', $where=null)
    {
        $sql=self::subSQL($arr, $dbname, $type, $where);
        return self::query($sql);
    }

    /**
     * 返回键名为序列的数组集合
     *
     * @param  resource $q 资源标识指针
     * @return array
     **/
    public function fetchs($q)
    {
        return $q->fetch();
    }

    /**
     * 取得结果集中行的数目
     *
     * @param  resource $q 资源标识指针
     * @return array
     **/
    public function reRows($q)
    {
        return $q->rowCount();
    }

    /**
     * 取得被INSERT、UPDATE、DELETE查询所影响的记录行数
     *
     * @return int
     **/
    public function afrows()
    {
        return $this->_db->rowCount();
    }

    /**
     * 释放结果集缓存
     *
     * @param  resource $q 资源标识指针
     * @return boolean
     **/
    public function refree(&$q)
    {
        $q = null;
    }
    /**
     * 启动事务处理
     * @return unknown_type
     */
    public function start()
    {
        $this->_db->beginTransaction();
    }
    /**
     * 提交事务处理
     * @return unknown_type
     */
    public function commit()
    {
        $this->_db->commit();
    }
    /**
     * 事务回滚
     * @return unknown_type
     */
    public function back()
    {
        $this->_db->rollBack();
    }
    /**
     * 设置异常消息 可以通过try块中捕捉该消息
     *
     * @param  string $str debug错误信息
     * @return void
     **/
    public function showMsg($str)
    {
        if ($this->dbError) {
            echo($str.$this->_db->error);
        } elseif (defined('DBLOG')) {
            $_tmp='';
            isset($_SERVER['SERVER_ADDR']) && $_tmp.='['.$_SERVER['SERVER_ADDR'].']';
            isset($_SERVER['REQUEST_URI']) && $_tmp.='['.$_SERVER['REQUEST_URI'].']';
            $_tmp && $_tmp.="\n";
            file_put_contents(DBLOG, date("Y-m-d H:i:s > ").$_tmp.$str.$this->_db->error."\n\n", FILE_APPEND);
        }
    }

    public function __destruct()
    {
        // TODO: Implement __destruct() method.
        $this->close();
    }

    public function getErrorInfo()
    {
        return array(
            "msg" => implode("\n", $this->_db->errorInfo()),
            "code" => $this->_db->errorCode()
        );
    }
}
