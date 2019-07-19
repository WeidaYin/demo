<?php
namespace Fend;

/**
 * Fend_Write
 * 生成单表的写数据表对象
 *
 * @Author  gary
 * @version $Id$
 **/
class Write extends \Fend\Db\Module
{

    public static function Factory($table='', $db='', $driver='Mysql')
    {
        return new self($table, $db, $driver);
    }

    public function __construct($table='', $db=null, $driver='Mysql')
    {
        if (!empty($table)) {
            $this->_tableName   = $table;
            $this->_cacheKey    = $table.':';
        }
        $Db_Module              = "\\Fend\\Db\\".$driver;
        $this->_db              = $Db_Module::Factory('w', $db);
    }

    /**
     * 返回数据表对象
     */
    public function getModule()
    {
        return $this->_db;
    }

    /**
     * 添加数据接口
     * @param $data
     * @return mixed
     */
    public function add($data)
    {
        $sql = $this->subSQL($data, $this->_tableName, 'insert');
        if ($this->query($sql)) {
            return $this->getLastId();
        }
        return false;
    }

    /**
     * 添加数据接口
     * @param $data
     * @return string
     */
    public function addForSql($data)
    {
        if (empty($data)) {
            return array('code' => 0, 'argument is error [$arg[0]:int ,$arg[1]:array]');
        }
        $sql = $this->subSQL($data, $this->_tableName, 'insert');
        return $sql . ';';
    }

    /**
     * @param $id
     * @param $data
     * @return string
     */
    public function editByIdForSql($id, $data)
    {
        if (empty($id) || empty($data)) {
            return array('code' => 0, 'argument is error [$arg[0]:int ,$arg[1]:array]');
        }
        $sql = $this->subSQL($data, $this->_tableName, 'update', " id={$id}");
        return $sql . ';';
    }

    /**
     * @param $conditios
     * @param $data
     * @return array|string
     */
    public function editForSql($conditios, $data)
    {
        if (empty($conditios) || empty($data)) {
            return array('code' => 0, 'argument is error [$arg[0]:array ,$arg[1]:array]');
        }
        $this->setConditions($conditios);
        $where = !empty($ids) ? " id IN(" . join(",", $ids) . ")" : $this->_mod->getWhere();
        $sql = $this->subSQL($data, $this->_tableName, 'update', $where);
        return $sql . ';';
    }

    /**
     * @param $conditios
     * @param $data
     * @return array|string
     */
    public function edit($conditios, $data)
    {
        if (empty($conditios) || empty($data)) {
            return array('stat'=>0,'argument is error [$arg[0]:array ,$arg[1]:array]');
        }
        $this->setConditions($conditios);
        $where  = !empty($ids)?" id IN(".join(",", $ids).")":$this->getWhere();
        $sql = $this->subSQL($data, $this->_tableName, 'update', $where);
        return $this->query($sql);
    }

    /**
     * 根据自增id更新记录
     * @param   int     $id
     * @param   array   $data
     * @return bool
     */
    public function editById($id, $data)
    {
        if (empty($id) || empty($data)) {
            return array('stat'=>0,'argument is error [$arg[0]:int ,$arg[1]:array]');
        }
        $sql = $this->subSQL($data, $this->_tableName, 'update', " id={$id}");
        return $this->query($sql);
    }

    /**
     * 根据条件删除记录
     * @param   mixed $condition
     * @return bool
     */
    public function del($condition)
    {
        if (empty($condition)) {
            return false;
        }
        $this->setConditions($condition);
        $this->setLimit(0, 0);
        $sql = $this->subSQL(array(), $this->_tableName, 'delete', $this->getWhere());
        return $this->query($sql);
    }


    /**
     * @param $id int answerID
     * @param $fields array  要获取的字段
     * @return  array()
     **/
    public function getById($id, $fields = array())
    {
        $this->setWhere("id={$id}");
        return $this->getOne();
    }

    /**
     * @param $idarr array
     * @param $fields array  要获取的字段
     * @return  array()
     **/
    public function getByIdArray($idarr, $fields = array())
    {
        $ids = join(',', $idarr);
        $this->setWhere("id IN({$ids})");
        $this->setField($fields);
        return $this->getList();
    }

    /**
     * @param $conditions
     *
     * @return mixed
     */
    public function getListByCondition($conditions = array(), $fields = array(), $order = array())
    {
        $this->setConditions($conditions);
        $this->setField($fields);
        $this->setOrder($order);
        return $this->getList();
    }

    /**
     * @param $conditions
     *
     * @return mixed
     */
    public function getInfoByCondition($conditions=array(), $fields = array())
    {
        $this->setWhere("");
        $this->setConditions($conditions);
        $this->setField($fields);
        return $this->getOne();
    }

    /**
     * @param $con
     * @param int $start
     * @param int $limit
     * @return array
     */
    public function getDataList($con=array(), $start=0, $limit=20, $fields = array())
    {
        $item = array('psize' => $limit, 'skip' => $start, 'total' => 0, 'list' => array());
        $this->setConditions($con);
        $this->setField($fields);
        $this->setLimit($start, $limit);
        $item['list'] = $this->getList();
        return $item;
    }
}
