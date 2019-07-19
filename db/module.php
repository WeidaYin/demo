<?php
namespace Fend\Db;

class Module extends \Fend\Fend
{
    protected $_tableName        = '';
    protected $_where            = '1';
    protected $_field            = '';
    protected $_order            = '';
    protected $_group            = '';

    //left join使用
    protected $_collect_table    = '';
    protected $_collect_field    = '';
    protected $_collection_on    = '';
    protected $_collection_where = '';

    protected $_start            = 0;
    protected $_limit            = 0;
    protected $_checksafe        = 1;

    /**
     * @var \Fend\Db\Mysql
     */
    protected $_db               = null;
    protected $checkcmd          = array('SELECT', 'UPDATE', 'INSERT', 'REPLACE', 'DELETE');
    protected $config            = array(
        'dfunction' => array('load_file', 'hex', 'substring', 'if', 'ord', 'char'),
        'daction'   => array('intooutfile', 'intodumpfile', 'unionselect', '(select', 'unionall', 'uniondistinct', '@'),
        'dnote'     => array('/*', '*/', '#', '--', '"'),
        'dlikehex'  => 1,
        'afullnote' => 1
    );

    /**
     * @param $conditions
     * @param string $type
     */
    public function setConditions($conditions, $type = 'AND')
    {
        $where = '1';
        if (is_array($conditions) && !empty($conditions)) {
            foreach ($conditions as $key => $value) {
                if (is_array($value) && !empty($value)) {
                    foreach ($value as $ky => $val) {
                        if (is_numeric($val)) {
                            $val = intval($val);
                        }
                        $wheres = (is_string($key)) ? " {$ky} {$key} {$val} " : " {$ky}  {$val} ";
                        $where  .= !empty($where) ? " {$type} " . $wheres : $wheres;
                    }
                } else {
                    if (is_numeric($value)) {
                        $value = intval($value);
                    } elseif (is_string($value)) {
                        $value = $this->escape($value);
                    }
                    $vs    = is_numeric($value) ? $value : "'{$value}'";
                    $where .= !empty($where) ? " AND `{$key}` = {$vs}" : " {$type} `{$key}` = {$vs}";
                }
            }
            $this->_where = $where;
        } elseif (!empty($conditions) && is_string($conditions)) {
            $this->_where = $conditions;
        }
    }

    /**
     * 设置数据表
     */
    public function setTable($table)
    {
        $this->_tableName     = $table;
        $this->_where         = '';
        $this->_order         = '';
        $this->_group         = '';
        $this->_start         = 0;
        $this->_limit         = 0;
        $this->_collect_table = '';
    }

    /**
     * 获取当前实例的表名字
     */
    public function getTable()
    {
        return $this->_tableName;
    }

    /**
     * 设置查询字段
     *
     * */
    public function setField($field = array())
    {
        if (empty($field)) {
            $this->_field = '*';
        } elseif (is_array($field)) {
            $this->_field = join(',', $field);
        } elseif (!empty($field) && !is_array($field)) {
            $this->_field = $field;
        }
    }

    /**
     * 设置limit数据
     * */
    public function setLimit($start = 0, $end = 20)
    {
        $this->_start = $start;
        $this->_limit = $end;
    }

    /**
     * 是否打开SQL检测
     * @param bool $open sql检测
     * */
    public function setSqlSafe($open = true)
    {
        $this->_checksafe = $open;
    }

    /**
     * 设置查询条件
     * @param string $where 字符串拼出来的where条件
     * */
    public function setWhere($where = '')
    {
        $this->_where = empty($where) ? 1 : $where;
    }

    /**
     * 获取查询条件
     * @return string 最终结果的的where条件
     */
    public function getWhere()
    {
        return $this->_where;
    }

    /**
     * 设置排序
     * */
    public function setOrder($order = '')
    {
        $this->_order = $order;
    }

    /**
     * 设置group by 内容 如果设置sql会自动跟随在后面增加group by
     * @param string $group 分组相关输出内容
     * */
    public function setGroup($group = '')
    {
        $this->_group = $group;
    }

    /**
     * 设置Left Join关系表名
     * */
    public function setRelationTable($table)
    {
        if (!empty($table)) {
            $this->_collect_table = $table;
        }
    }

    /**
     * 设置Left Join 关联字段
     * */
    public function setRelationOn($on)
    {
        $arron = array();
        if (is_array($on) && !empty($on)) {
            foreach ($on as $key => $val) {
                $arron[] = "{$this->_tableName}.{$key} = {$this->_collect_table}" . '.' . $val;
            }
        }
        if (!empty($arron)) {
            $this->_collection_on = join(' AND ', $arron);
        }
    }

    /**
     * 这个用于Left Join查询右边SQL的Field
     * @param $field
     */
    public function setRelationField($field)
    {
        if (!empty($field) && is_array($field)) {
            foreach ($field as &$val) {
                if ($this->_collect_table) {
                    $val = "{$this->_collect_table}.{$val}";
                }
            }
            $this->_collect_field = ',' . join(',', $field);
        }
    }

    /**
     * Left Join 查询的Where条件设置
     * @param $conditions
     * @param string $type
     */
    public function setRelationWhere($conditions, $type = 'AND')
    {
        $where = '';
        if (is_array($conditions) && !empty($conditions) && !empty($this->_collect_table)) {
            foreach ($conditions as $key => $value) {
                if (is_array($value) && !empty($value)) {
                    foreach ($value as $ky => $val) {
                        if (is_numeric($val)) {
                            $val = intval($val);
                        } elseif (is_string($val)) {
                            $val = $this->escape($val);
                        }
                        $wheres = (is_string($key)) ? " {$this->_collect_table}.{$ky} {$key} {$val} " : " {$this->_collect_table}.{$ky}  {$val} ";
                        $where  .= !empty($where) ? " {$type} " . $wheres : $wheres;
                    }
                } else {
                    if (is_numeric($value)) {
                        $value = intval($value);
                    } elseif (is_string($value)) {
                        $value = $this->escape($value);
                    }
                    $where .= !empty($where) ? " AND {$this->_collect_table}.`{$key}` = '{$value}'" : " {$type} {$this->_collect_table}.`{$key}` = '{$value}'";
                }
            }
            $this->_collection_where = $where;
        } elseif (!is_array($conditions) && !empty($conditions) && !empty($this->_collect_table)) {
            $this->_collection_where = ' AND ' . $this->escape($conditions);
        }
    }

    /**
     * 格式化MYSQL查询字符串
     *
     * @param  string $str 待处理的字符串
     * @return string
     * */
    public function escape($str)
    {
        return $this->_db->escape($str);
    }

    /**
     * 生成REPLACE|UPDATE|INSERT等标准SQL语句
     * 此函数用于对数据添加更新
     *
     * @param  string $arr    操纵数据库的数组源
     * @param  string $dbname 数据表名
     * @param  string $type   SQL类型 UPDATE|INSERT|REPLACE|IFUPDATE
     * @param  string $where  where条件
     * @return string         一个标准的SQL语句
     * */
    public function subSQL($arr, $dbname, $type = 'update', $where = null, $duplicate = array())
    {
        $tem  = $vals = array();
        if (!empty($arr) && is_array($arr)) {
            foreach ($arr as $k => &$v) {
                if (is_array($v) && $type == 'insertall') {
                    if (empty($keys)) {
                        $keys = join(',', array_keys($v));
                    }
                    if (!empty($v)) {
                        $vals[] = "('" . join("','", $v) . "')";
                    }
                } else {
                    $k = $this->escape($k);
                    $v = $this->escape($v);

                    $tem[$k] = "`{$k}`='{$v}'";
                }
            }
        }
        switch (strtolower($type)) {
            case 'insertall'://批量插入
                if (!empty($keys) && !empty($vals)) {
                    $sql = "INSERT INTO {$dbname} ({$keys}) VALUES " . join(',', $vals);
                } else {
                    $sql = null;
                }
                break;
            case 'insert'://插入
                $sql = "INSERT INTO {$dbname} SET " . join(',', $tem);
                break;
            case 'replace'://替换
                $sql = "REPLACE INTO {$dbname} SET " . join(',', $tem);
                break;
            case 'update'://更新
                $sql = "UPDATE {$dbname} SET " . join(',', $tem) . " WHERE {$where}";
                break;
            case 'ifupdate'://存在则更新记录
                $tem = join(',', $tem);
                if (!empty($duplicate)) {
                    foreach ($duplicate as $ks => &$vs) {
                        $ifitem[$ks] = "`{$ks}`={$vs}";
                    }
                    $ifitem = join(',', $ifitem);
                    $sql    = "INSERT INTO {$dbname} SET {$tem} ON DUPLICATE KEY UPDATE {$ifitem}";
                } else {
                    $sql = "INSERT INTO {$dbname} SET {$tem} ON DUPLICATE KEY UPDATE {$tem}";
                }
                break;
            case 'delete'://存在则更新记录
                $sql = "delete FROM {$dbname} WHERE  {$where}";
                break;
            default:
                $sql = null;
                break;
        }
        return $sql;
    }

    /**
     * 用于生成查询SQL
     * @return string
     */
    public function getSql()
    {
        $sql = '';
        if (empty($this->_field)) {
            $sql = 'SELECT ' . ' * ';
        } else {
            $sql = 'SELECT ' . $this->_field;
        }
        if (!empty($this->_collect_field) && !empty($this->_collect_table)) {
            $sql .= $this->_collect_field;
        }

        $sql .= ' FROM ' . $this->_tableName;
        if (!empty($this->_collect_table) && !empty($this->_collection_on)) {
            $sql .= ' LEFT JOIN ' . $this->_collect_table;
            $sql .= ' ON ' . $this->_collection_on;
        }
        if (!empty($this->_where) && !empty($this->_collection_where)) {
            $sql .= ' WHERE ' . $this->_where . ' ' . $this->_collection_where;
        } elseif (!empty($this->_where)) {
            $sql .= ' WHERE ' . $this->_where;
        } elseif (!empty($this->_collection_where)) {
            $sql .= ' WHERE ' . $this->_collection_where;
        }

        if (!empty($this->_group)) {
            $sql .= ' GROUP BY ' . $this->_group;
        }

        if (!empty($this->_order)) {
            $sql .= ' ORDER BY ' . $this->_order;
        }
        if (!empty($this->_limit)) {
            $start = !empty($this->_start) ? $this->_start : 0;
            $sql   .= ' LIMIT ' . $start . ',' . $this->_limit;
        }
        return $sql;
    }

    /**
     * @return string
     */
    public function getSqlSum()
    {
        $sql = 'SELECT COUNT(*) AS total ';

        $sql .= ' FROM ' . $this->_tableName;
        if (!empty($this->_collect_table) && !empty($this->_collection_on)) {
            $sql .= ' LEFT JOIN ' . $this->_collect_table;
            $sql .= ' ON ' . $this->_collection_on;
        }
        if (!empty($this->_where) && !empty($this->_collection_where)) {
            $sql .= ' WHERE ' . $this->_where . ' ' . $this->_collection_where;
        } elseif (!empty($this->_where)) {
            $sql .= ' WHERE ' . $this->_where;
        } elseif (!empty($this->_collection_where)) {
            $sql .= ' WHERE ' . $this->_collection_where;
        }
        if (!empty($this->_group)) {
            $sql .= ' GROUP BY ' . $this->_group;
        }
        return $sql;
    }

    /**
     * @return mixed
     */
    public function getLastId()
    {
        return $this->_db->getId();
    }

    /**
     * @return mixed
     */
    public function getOne()
    {
        $sql = $this->getSql();
        return $this->_db->get($sql);
    }

    /**
     * @param string $sql;
     * @param int $r;
     * @return mixed
     */
    public function get($sql, $r = 0)
    {
        return $r > 0 ? $this->_db->get($sql, 1) : $this->_db->get($sql);
    }

    /**
     * 获取查询的数据的总数
     * @return array();
     * */
    public function getSum()
    {
        $sql = $this->getSqlSum();
        return $this->_db->get($sql, 1);
    }

    /**
     * 获取列表
     */
    public function getList()
    {
        $list  = array();
        $query = $this->query();
        if (!empty($query)) {
            while ($rs = $this->fetch(($query))) {
                $list[] = $rs;
            }
        }
        return $list;
    }

    /**
     * @param $sql
     * @return source  mysql query
     */
    public function query($sql = '')
    {
        $sql     = empty($sql) ? $this->getSql() : $sql;

        //sql 安全检查
        $sqlsafe = $this->checkquery($sql);

        //sql安全不符合，拒绝记录日志
        if ($sqlsafe < 1 && empty($this->_checksafe)) {
            $_tmp = '';
            $str  = "SqlSafe failed: ";
            isset($_SERVER['SERVER_ADDR']) && $_tmp .= '[' . $_SERVER['SERVER_ADDR'] . ']';
            isset($_SERVER['REQUEST_URI']) && $_tmp .= '[' . $_SERVER['REQUEST_URI'] . ']';
            $_tmp && $_tmp .= "\n";
            file_put_contents(DBLOG, date("Y-m-d H:i:s > ") . $_tmp . $str . $this->_db->error . "\n\n", FILE_APPEND);
            return false;
        }

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
        } else {
            return $this->_db->query($sql);
        }
    }

    /**
     * 取得被INSERT、UPDATE、DELETE查询所影响的记录行数
     *
     * @return int
     * */
    public function afrows()
    {
        return $this->_db->afrows();
    }

    /**
     * @param $query
     * @return source  mysql fetch
     */
    public function fetch($query)
    {
        if (!empty($query)) {
            return $this->_db->fetch($query);
        }
        return false;
    }

    //开启事务
    public function trans_begin()
    {
        return $this->_db->start();
    }

    //事务回滚
    public function trans_rollback()
    {
        return $this->_db->back();
    }

    //事务回滚
    public function trans_commit()
    {
        return $this->_db->commit();
    }

    /**
     * sql安全监测
     * @param $sql
     * @return bool
     */
    public function checkquery($sql)
    {
        $cmd = trim(strtoupper(substr($sql, 0, strpos($sql, ' '))));
        if (in_array($cmd, $this->checkcmd)) {
            $test = self::_do_query_safe($sql);
            if ($test < 1) {
                return false;
            }
        }
        return true;
    }

    /**
     * 私有sql检测
     * 只要一点不符合就拒绝
     * @param $sql
     * @return int|string
     */
    private function _do_query_safe($sql)
    {
        $sql   = str_replace(array('\\\\', '\\\'', '\\"', '\'\''), '', $sql);
        $mark  = $clean = '';
        if (strpos($sql, '/') === false && strpos($sql, '#') === false && strpos($sql, '-- ') === false) {
            $clean = preg_replace("/'(.+?)'/s", '', $sql);
        } else {
            $len   = mb_strlen($sql);
            $mark  = $clean = '';
            for ($i = 0; $i < $len; $i++) {
                $str = $sql[$i];
                switch ($str) {
                    case '\'':
                        if (!$mark) {
                            $mark  = '\'';
                            $clean .= $str;
                        } elseif ($mark == '\'') {
                            $mark = '';
                        }
                        break;
                    case '/':
                        if (empty($mark) && $sql[$i + 1] == '*') {
                            $mark  = '/*';
                            $clean .= $mark;
                            $i++;
                        } elseif ($mark == '/*' && $sql[$i - 1] == '*') {
                            $mark  = '';
                            $clean .= '*';
                        }
                        break;
                    case '#':
                        if (empty($mark)) {
                            $mark  = $str;
                            $clean .= $str;
                        }
                        break;
                    case "\n":
                        if ($mark == '#' || $mark == '--') {
                            $mark = '';
                        }
                        break;
                    case '-':
                        if (empty($mark) && substr($sql, $i, 3) == '-- ') {
                            $mark  = '-- ';
                            $clean .= $mark;
                        }
                        break;

                    default:

                        break;
                }
                $clean .= $mark ? '' : $str;
            }
        }

        if (strpos($clean, '@') !== false) {
            return '-3';
        }
        $clean = preg_replace("/[^a-z0-9_\-\(\)#\*\/\"]+/is", "", strtolower($clean));

        if ($this->config['afullnote']) {
            $clean = str_replace('/**/', '', $clean);
        }

        if (is_array($this->config['dfunction'])) {
            foreach ($this->config['dfunction'] as $fun) {
                if (strpos($clean, $fun . '(') !== false) {
                    return '-1';
                }
            }
        }

        if (is_array($this->config['daction'])) {
            foreach ($this->config['daction'] as $action) {
                if (strpos($clean, $action) !== false) {
                    return '-3';
                }
            }
        }

        if ($this->config['dlikehex'] && strpos($clean, 'like0x')) {
            return '-2';
        }

        if (is_array($this->config['dnote'])) {
            foreach ($this->config['dnote'] as $note) {
                if (strpos($clean, $note) !== false) {
                    return '-4';
                }
            }
        }
        return 1;
    }

    /**
     * 切换数据库
     * @param  mixed $dbName
     */
    public function useDb($dbName = null)
    {
        $this->_db->useDb($dbName);
    }

    public function setTimeout($timeout = 30)
    {
        $this->_db->setTimeout($timeout);
    }

    public function getErrorInfo()
    {
        return $this->_db->getErrorInfo();
    }
}
