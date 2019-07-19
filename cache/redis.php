<?php
namespace Fend\Cache;

/**
 * Fend Framework
 * 缓存
 * @property \Redis
 * */
class Redis extends \Fend\Fend
{

    public $mc; //连接成功时的标识
    public $_pre = ''; //标识
    public $_redisconfig = array();
    public $mypid = '';

    /**
     * 预留方法 扩展使用
     *
     **/
    public static function Factory($db = 'default', $hash = '')
    {
        static $rd = array();
        if(!isset($rd[$db]) || $rd[$db] === null){
            $rd[$db]             =  new self($db);
        }
        return $rd[$db];
    }

    /**
     * 初始化对象
     *
     * @return void
     **/
    public function __construct($db, $hash = '')
    {
        $this->mc = new \Redis();

        $redisconfig        = \Fend\Config::get('redis');
        $config             = $redisconfig[$db];
        $this->_redisconfig = $config;

        $this->mc->connect(
            isset($config['host']) ? $config['host'] : '127.0.0.1',
            isset($config['port']) ? $config['port'] : '6379'
        ) or self::showError('[RedisCache:]Could not connect');

        $this->mc->setOption(\Redis::OPT_READ_TIMEOUT, -1);

        if ( isset($config['pre']) ) {
            $this->_pre = $config['pre'];
        }

        // 校验是否需要密码
        $auth = isset($config['user']) ? $this->rdscfg['user'] : '';
        if ( !empty($auth) ) {
            $auth = isset($this->rdscfg['pwd']) ? $auth . ":" . $this->rdscfg['pwd'] : $auth;
        } elseif ( isset($this->rdscfg['pwd']) ) {
            $auth = $this->rdscfg['pwd'];
        }
        if ( !empty($auth) ) {
            if ( $this->mc->auth($auth) == false ) {
                \Fend\Log::error("RedisCache", __FILE__, __LINE__,
                    "Redis cant found " . $this->mc->getLastError() . " Index Config");
                return;
            }
        }

        //select db
        if ( isset($config['dbs']) ) {
            $this->mc->select($config['dbs']);
        }
    }

    /**
     * @param $config
     *
     * @throws exception
     */
    private function connectRedis($config=array())
    {
        $this->mc = new \Redis();
        if (empty($config)) {
            $redisconfig        = \Fend\Config::get('redis');
            $config             = $redisconfig['default'];
            $this->_redisconfig = $config;
        }
        $this->mc->connect(
            isset($config['host']) ? $config['host'] : '127.0.0.1',
            isset($config['port']) ? $config['port'] : '6379'
        ) or self::showError('[RedisCache:]Could not connect');

        if ( isset($config['pre']) ) {
            $this->_pre = $config['pre'];
        }

        // 校验是否需要密码
        $auth = isset($config['user']) ? $this->rdscfg['user'] : '';
        if ( !empty($auth) ) {
            $auth = isset($this->rdscfg['pwd']) ? $auth . ":" . $this->rdscfg['pwd'] : $auth;
        } elseif ( isset($this->rdscfg['pwd']) ) {
            $auth = $this->rdscfg['pwd'];
        }
        if ( !empty($auth) ) {
            if ( $this->mc->auth($auth) == false ) {
                \Fend\Log::error("RedisCache", __FILE__, __LINE__,
                    "Redis cant found " . $this->mc->getLastError() . " Index Config");
                return;
            }
        }

        //db
        if ( isset($config['dbs']) ) {
            $this->mc->select($config['dbs']);
        }

        $this->mc->setOption(\Redis::OPT_READ_TIMEOUT, -1);
    }

    public function checkConnection()
    {
        try {

            if ( $this->mypid != getmypid() ) {
                $this->mypid = getmypid();
                $this->connectRedis($this->_redisconfig);
                return;
            }
            //test connect if not will reconnect
            $ret = $this->mc->ping();
            if ( $ret != "+PONG" ) {
                $this->connectRedis($this->_redisconfig);
                return;
            }
        } catch ( \Exception $e ) {
            $this->connectRedis($this->_redisconfig);
        }
    }

    /**
     * 获取源对象
     * @return Redis
     */
    public function getRedisObj()
    {
        return $this->mc;
    }

    /**
     * 与set方法相同
     * 唯一的区别是: 增加对数组序列化功能
     *
     * @param  string $key 数据的标识
     * @param  string $value 实体内容
     * @param  string $expire 过期时间单位秒
     * @return bool
     * */
    public function sets($key, $value, $expire = 0)
    {
        $this->checkConnection();
        $expire > 0 && $expire = self::setLifeTime($expire);
        return $expire > 0 ? $this->mc->setex($this->_pre . $key, $expire, $value) : $this->mc->set($this->_pre . $key,
            $value);
    }

    /**
     * 获取数据缓存
     *
     * @param  string $key 数据的标识
     * @return string
     * */
    public function gets($key)
    {
        $this->checkConnection();

        return $this->mc->get($this->_pre . $key);
    }

    /**
     * 设置数据缓存
     * 与add|replace比较类似
     * 唯一的区别是: 无论key是否存在,是否过期都重新写入数据
     *
     * @param  string $key 数据的标识
     * @param  string $value 实体内容
     * @param  string $expire 过期时间[天d|周w|小时h|分钟i] 如:8d=8天 默认为0永不过期
     * @param  bool $iszip 是否启用压缩
     * @return bool
     * */
    public function set($key, $value, $expire = 0)
    {
        $this->checkConnection();

        $value = self::rdsCode($value, 1);
        $expire > 0 && $expire = self::setLifeTime($expire);
        return $expire > 0 ? $this->mc->setex($this->_pre . $key, $expire, $value) : $this->mc->set($this->_pre . $key,
            $value);
    }

    /**
     * 获取数据缓存
     *
     * @param  string $key 数据的标识
     * @return string
     * */
    public function get($key)
    {
        $this->checkConnection();

        $value = $this->mc->get($this->_pre . $key);
        return $value ? self::rdsCode($value) : $value;
    }

    /**
     * 获取数据集合
     *
     * @param  array $key 数据的标识
     * @param  int $t 是否清除空记录
     * @return array
     * */
    public function mget(array $key, $t = 0)
    {
        $this->checkConnection();

        $item = array();
        $_tmp = $this->mc->getMultiple($key);
        $_k   = 0; //解决键名中带有ID的情况
        foreach ( $key as $k => &$v ) {
            $v = $_tmp[$_k];
            $v = $v ? self::rdsCode($v) : $v;
            ++$_k;

            if ( $t && empty($v) )
                continue;
            $item[$k] = &$v;
        }
        return $item;
    }

    /**
     * 新增数据缓存
     * 只有当key不存,存在但已过期时被设值
     *
     * @param  string $key 数据的标识
     * @param  string $value 实体内容
     * @param  string $expire 过期时间[天d|周w|小时h|分钟i] 如:8d=8天 默认为0永不过期
     * @param  bool $iszip 是否启用压缩
     * @return bool   操作成功时返回ture,如果存在返回false否则返回true
     * */
    public function add($key, $value, $expire = 0)
    {
        $this->checkConnection();

        if ( $expire > 0 ) {
            $expire = self::setLifeTime($expire);
            if ( $this->mc->exists($this->_pre . $key) ) {
                return false;
            } else {
                return $this->set($key, $value, $expire);
            }
        } else {
            $value = self::rdsCode($value, 1);
            return $this->mc->setnx($this->_pre . $key, $value);
        }
    }

    /**
     * 替换数据
     * 与 add|set 参数相同,与set比较类似
     * 唯一的区别是: 只有当key存在且未过期时才能被替换数据
     *
     * @param  string $key 数据的标识
     * @param  string $value 实体内容
     * @param  string $expire 过期时间[天d|周w|小时h|分钟i] 如:8d=8天 默认为0永不过期
     * @param  bool $iszip 是否启用压缩
     * @return bool
     * */
    public function replace($key, $value, $expire = 0)
    {
        $this->checkConnection();

        if ( self::iskey($key) ) {
            return self::set($key, $value, $expire);
        }
        return false;
    }

    /**
     * 检测缓存是否存在
     *
     * @param  string $key 数据的标识
     * @return bool
     * */
    public function isKey($key)
    {
        $this->checkConnection();

        return $this->mc->exists($this->_pre . $key);
    }

    public function getKey($key)
    {
        $this->checkConnection();

        return $this->mc->keys($key);
    }

    /**
     * @param $key
     *
     * @return array
     */
    public function getKeys($key)
    {
        $this->checkConnection();

        return $this->mc->keys("*{$key}*");
    }

    /**
     * 删除一个数据缓存
     *
     * @param  string $key 数据的标识
     * @param  string $expire 删除的等待时间,好像有问题尽量不要使用
     * @return bool
     * */
    public function del($key)
    {
        $this->checkConnection();

        return $this->mc->del($this->_pre . $key);
    }

    /**
     * 删除多个数据缓存
     *
     * @param  string $key 数据的标识
     * @return bool
     * */
    public function delKeys($key)
    {
        $this->checkConnection();

        $keys = $this->mc->keys("*{$key}*");
        if ( !empty($keys) ) {
            foreach ( $keys as $val ) {
                $this->mc->del($val);
            }
        }
        return true;
    }

    /**
     * Increment the value of a key
     *
     * @param  string $key 数据的标识
     * @return bool
     * */
    public function incr($key)
    {
        $this->checkConnection();

        return $this->mc->incr($this->_pre . $key);
    }

    /**
     * 格式化过期时间
     * 注意: 限制时间小于2592000=30天内
     *
     * @param  string $t 要处理的串
     * @return int
     * */
    private function setLifeTime($t)
    {
        if ( !is_numeric($t) ) {
            switch ( substr($t, -1) ) {
                case 'w'://周
                    $t = (int)$t * 7 * 24 * 3600;
                    break;
                case 'd'://天
                    $t = (int)$t * 24 * 3600;
                    break;
                case 'h'://小时
                    $t = (int)$t * 3600;
                    break;
                case 'i'://分钟
                    $t = (int)$t * 60;
                    break;
                default:
                    $t = (int)$t;
                    break;
            }
        }
        $t > 2592000 && $t = 2592000;
        //if($t>2592000) self::showMsg('Memcached Backend has a Limit of 30 days (2592000 seconds) for the LifeTime');
        return $t;
    }

    /**
     * 编码解码
     *
     * @param  string $str 串
     * @param  string $tp 类型,1编码0为解码
     * @param string $type 编码/解码类型 0 json 1 serialize
     * @return array|string
     * */
    private function rdsCode($str, $tp = 0, $type = 0)
    {

        if ( $type ) {
            return $tp ? @serialize($str) : @unserialize($str);
        }
        return $tp ? @json_encode($str) : @json_decode($str, true);
    }

    /**
     * 添加一个集合
     * @params string $name     集合的名字
     * @params string $value    集合的值
     * @params string $ags      值
     * @return bool
     */
    public function sadd($name, $value)
    {
        if ( empty($value) ) {
            return false;
        }
        $this->checkConnection();

        $arr = explode(',', $value);

        foreach ( $arr as $v ) {
            $this->mc->sAdd($this->_pre . $name, $v);
        }
        return true;
    }

    /**
     * 添加一个集合
     * @params array    $arr   数据集合
     *         $arr[0]=>集合的名字
     *         $arr[>0]=>集合的数据
     * @return bool
     */
    public function cardadd($arr)
    {
        $this->checkConnection();

        if ( count($arr) < 2 ) {
            return false;
        }
        return call_user_func_array(array($this->mc, 'sAdd'), $arr);
    }

    /**
     * 删除集合
     * @param string $cardname 集合名字
     * @param bool
     * @return bool
     */
    public function delcard($cardname)
    {
        $this->checkConnection();

        return $this->mc->del($this->_pre . $cardname);
    }

    /**
     * 删除card中的元素
     * @params string $name     集合的名字
     * @params string $value    集合的值
     * @pa
     */
    public function delcardrember($name, $value)
    {
        $this->checkConnection();

        if ( empty($value) ) {
            return false;
        }
        $arr = explode(',', $value);
        foreach ( $arr as $v ) {
            $this->mc->sRem($this->_pre . $name, $v);
        }
        return true;
    }

    /**
     * 获取给定集合的差集
     * @params string  $card1    集合的名字
     * @params string $card2    集合的名字
     * @return array
     */
    public function carddiff($card1, $card2)
    {
        $this->checkConnection();

        return $this->mc->sDiff($this->_pre . $card1, $this->_pre . $card2);
    }

    /**
     * 获取给定集合的差集并存储到第三个集合中
     * @params string  $newcard    新生成的集合的名字
     * @params string $card2    集合的名字
     * @params string $card3    集合的名字
     * @return array
     */
    public function carddiffstore($newcard, $card1, $card2)
    {
        $this->checkConnection();

        return $this->mc->sDiffStore($this->_pre . $newcard, $this->_pre . $card1, $this->_pre . $card2);
    }

    /**
     * 获取两个集合的交集
     * @params string  $card1    集合的名字
     * @params string $card2    集合的名字
     */
    public function cardinter($card1, $card2)
    {
        $this->checkConnection();

        return $this->mc->sInter($this->_pre . $card1, $this->_pre . $card2);
    }

    /**
     * 获取两个集合的交集并存储到新的集合中
     * @params string $newcard  新的集合的名字
     * @params string  $card1   集合的名字
     * @params string   $card2    集合的名字
     */
    public function cardinterstore($newcard, $card1, $card2)
    {
        $this->checkConnection();

        return $this->mc->sInterStore($this->_pre . $newcard, $this->_pre . $card1, $this->_pre . $card2);
    }

    /**
     * 获取两个集合的并集
     * @params string  $card1    集合的名字
     * @params string $card2    集合的名字
     */
    public function cardunion($card1, $card2)
    {
        $this->checkConnection();

        return $this->mc->sUnion($this->_pre . $card1, $this->_pre . $card2);
    }

    /**
     * 获取两个集合的并集并存储到新的集合中
     * @params string $newcard  新的集合的名字
     * @params string  $card1   集合的名字
     * @params string   $card2    集合的名字
     */
    public function cardunionstore($newcard, $card1, $card2)
    {
        $this->checkConnection();

        return $this->mc->sUnionStore($this->_pre . $newcard, $this->_pre . $card1, $this->_pre . $card2);
    }

    /**
     * 获取集合中的一个或多个随机元素
     * @param string $name 集合的名字
     * @param int $num 获取的元素的个数
     * @return array
     */
    public function cardrand($name, $num)
    {
        $this->checkConnection();

        return $this->mc->sRandMember($this->_pre . $name, $num);
    }

    /**
     * 获取集中元素的数量
     * @params string $name     集合的名字
     * @return int | bool
     */
    public function cardtotal($name)
    {
        $this->checkConnection();

        return $this->mc->sCard($this->_pre . $name);
    }

    /**
     * 获取集合中的成员
     * @params string $name     集合的名字
     * @return array
     */
    public function cardlist($name)
    {
        $this->checkConnection();

        return $this->mc->sMembers($this->_pre . $name);
    }

    public function smembers($name)
    {
        $this->checkConnection();
        return $this->mc->sMembers($this->_pre . $name);
    }

    public function sismember($name, $item)
    {
        $this->checkConnection();
        return $this->mc->sismember($this->_pre . $name, $item);
    }

    public function srem($name, $item)
    {
        $this->checkConnection();
        return $this->mc->srem($this->_pre . $name, $item);
    }

    public function rpush($name, $value)
    {
        if ( $value === "" ) {
            return false;
        }
        $this->checkConnection();
        return $this->mc->rpush($this->_pre . $name, $value);
    }

    public function lpush($name, $value)
    {
        if ( $value === "" ) {
            return false;
        }
        $this->checkConnection();
        return $this->mc->lpush($this->_pre . $name, $value);
    }

    public function brpop($name, $timeout)
    {
        $this->checkConnection();
        return $this->mc->brpop($this->_pre . $name, $timeout);
    }

    /**
     * @param     $name
     * @param int $iszip
     *
     * @return array|string
     */
    public function rpop($name, $iszip = 1)
    {
        $this->checkConnection();
        $res = $this->mc->rpop($this->_pre . $name);
        return ($res && $iszip) ? $this->rdsCode($res) : $res;
    }

    /**
     * @param $key
     * @param int $start
     * @param int $end
     * @return mixed
     */
    public function lrange($key, $start = 0, $end = -1)
    {
        $this->checkConnection();
        return $this->mc->lrange($this->_pre . $key, $start, $end);
    }

    /**
     * 设置异常消息 可以通过try块中捕捉该消息
     */
    private function showError($str)
    {
        \Fend\Di::factory()->set("debug_error", \Fend\Exception::Factory($str)->ShowTry(1, 1));
        return;
    }

    /**
     * 添加一个有序集合
     * @params string $name     集合名称
     * @params string $score    集合排序值
     * @params string $value    集合的值
     * @return bool
     */
    public function zadd($name, $score, $value)
    {
        $this->checkConnection();

        $this->mc->zAdd($this->_pre . $name, $score, $value);
        return true;
    }

    /**
     * 获取zset集合信息
     *
     * @param string $name 集合名称
     * @param string $start 获取区间开始值(索引index)，默认0
     * @param string $end 获取区间结束值(索引index)，默认-1
     * @param int $sort 排序规则 1 score从小到大排序(默认) 2score从大到小排序
     * @param string $withscores 是否输出score值，默认false，不输出
     * @return mixed
     */
    public function zGet($name, $start = 0, $end = -1, $withscores = false, $sort = 1)
    {
        $this->checkConnection();
        if ( $sort == 1 ) {
            return $this->mc->zRange($this->_pre . $name, $start, $end, $withscores);
        }
        return $this->mc->zRevRange($this->_pre . $name, $start, $end, $withscores);
    }

    /**
     * 获取zset信息
     * @param string $name 集合名称
     * @param string $start 获取区间开始值（score>=），默认-inf
     * @param string $end 获取区间结束值(score<=)，默认+inf
     * @param int $sort 排序规则 1 score从小到大排序(默认) 2score从大到小排序
     * @param bool $withscores 是否输出score值，默认false，不输出
     * @return mixed
     */
    public function zGetByScore($name, $start = '-inf', $end = '+inf', $sort = 1, $withscores = true, $limit = 1)
    {
        $this->checkConnection();
        $arr['withscores'] = $withscores;
        $arr['limit']      = $limit;
        if ( $sort == 1 ) {
            return $this->mc->zRangeByScore($this->_pre . $name, $start, $end, $arr);
        }
        return $this->mc->zRevRangeByScore($this->_pre . $name, $end, $start, $arr);
    }

    /**
     * 获取zset中个数,默认返回全部
     * 按照score值进行获取信息
     * @param string $name 集合名称
     * @param string $start 获取区间开始值(score>=)，默认-inf
     * @param string $end 获取区间结束值(score<=)，默认+inf
     * @return mixed
     */
    public function zCount($name, $start = '-inf', $end = '+inf')
    {
        $this->checkConnection();
        return $this->mc->zCount($this->_pre . $name, $start, $end);
    }

    /**
     * 获取zset中所有元素个数
     * @param string $name 集合名称
     * @return bool
     */
    public function zCard($name)
    {
        $this->checkConnection();
        return $this->mc->zCard($this->_pre . $name);
    }

    /**
     * 删除zset中元素
     * @param string $name 集合名称
     * @param string $value
     * @return bool
     */
    public function zRem($name, $value)
    {
        $this->checkConnection();
        $this->mc->zRem($this->_pre . $name, $value);
        return true;
    }

    /**
     * 添加hash缓存信息
     * @param string $name hash缓存名称
     * @param array $data 添加到缓存中信息内容
     * array(
     *      '键值1' => '内容1'，
     *      '键值2' => '内容2',
     *      ......
     *  )
     * @return bool
     */
    public function hMset($name, $data = array())
    {
        if ( empty($data) ) {
            return false;
        }
        $this->checkConnection();
        $this->mc->hMset($this->_pre . $name, $data);
        return true;
    }

    /**
     * 获取hash缓存信息
     * @param string $name hash缓存名称
     * @param array $field 获取键的值，默认为空，则获取全部
     * @return array(
     *      '键值1' => '内容1',
     *      ......
     *  )
     */
    public function hGet($name, $fields = array())
    {
        $this->checkConnection();

        if ( empty($fields) ) {
            return $this->mc->hGetAll($name);
        } else {
            return $this->mc->hMGet($this->_pre . $name, $fields);
        }
    }

    /**
     * 获取hash缓存中所有的键
     * @param string $name hash缓存名称
     * @return  array(
     *      0 => '键值1',
     *      ......
     *  )
     */
    public function hKeys($name)
    {
        $this->checkConnection();

        return $this->mc->hKeys($this->_pre . $name);
    }

    /**
     * 获取hash缓存中所有的键对应的值
     * @param string $name hash缓存名称
     * @return  array(
     *      0 => '值1',
     *      ......
     *  )
     */
    public function hVals($name)
    {
        $this->checkConnection();

        return $this->mc->hVals($this->_pre . $name);
    }

    /**
     * 获取hash缓存中所有的键对应的值
     * @param string $name hash缓存名称
     * @return  array(
     *      0 => '值1',
     *      ......
     *  )
     */
    public function zRange($name, $start, $end, $withscores = false)
    {
        $this->checkConnection();

        return $this->mc->zRange($this->_pre . $name, $start, $end, $withscores);
    }


    /**
     * @author  zhupeijie@100tal.com
     * Increments the score of a member from a sorted set by a given amount.
     * @param $key
     * @param $value
     * @param $member
     * @return float
     */
    public function hIncrBy($key, $value, $member)
    {
        $this->checkConnection();

        return $this->mc->hIncrBy($this->_pre . $key, $value, $member);
    }


    /**
     * Increments the score of a member from a sorted set by a given amount.
     *
     * @param   string $key
     * @param   float $value (double) value that will be added to the member's score
     * @param   string $member
     * @return  float   the new value
     * @link    http://redis.io/commands/zincrby
     * @example
     * <pre>
     * $redis->delete('key');
     * $redis->zIncrBy('key', 2.5, 'member1');  // key or member1 didn't exist, so member1's score is to 0
     *                                          // before the increment and now has the value 2.5
     * $redis->zIncrBy('key', 1, 'member1');    // 3.5
     * </pre>
     */
    public function zIncrBy($key, $value, $member)
    {
        $this->checkConnection();

        return $this->mc->zIncrBy($this->_pre . $key, $value, $member);
    }

    /**
     * Returns the score of a given member in the specified sorted set.
     *
     * @param   string $key
     * @param   string $member
     * @return  float
     * @link    http://redis.io/commands/zscore
     * @example
     * <pre>
     * $redis->zAdd('key', 2.5, 'val2');
     * $redis->zScore('key', 'val2'); // 2.5
     * </pre>
     */
    public function zScore($key, $member)
    {
        $this->checkConnection();

        return $this->mc->zScore($this->_pre . $key, $member);
    }

    /**
     * 代理其他没有实现的redis
     * @param type $name
     * @param type $arguments
     *
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array(array($this->mc, $name), $arguments);
    }

    /**
     * Increment the float value of a key by the given amount
     *
     * @param   string $key
     * @param   float $value
     * @return  float
     * @link    http://redis.io/commands/incrbyfloat
     * @example
     * <pre>
     * $redis = new Redis();
     * $redis->connect('127.0.0.1');
     * $redis->set('x', 3);
     * var_dump( $redis->incrByFloat('x', 1.5) );   // float(4.5)
     *
     * // ! SIC
     * var_dump( $redis->get('x') );                // string(3) "4.5"
     * </pre>
     */
    public function incrByFloat($key, $value)
    {
        $this->checkConnection();

        return $this->mc->incrByFloat($this->_pre . $key, $value);
    }

    /**
     * Increment the number stored at key by one. If the second argument is filled, it will be used as the integer
     * value of the increment.
     *
     * @param   string    $key    key
     * @param   int       $value  value that will be added to key (only for incrBy)
     * @return  int         the new value
     * @link    http://redis.io/commands/incrby
     * @example
     * <pre>
     * $redis->incr('key1');        // key1 didn't exists, set to 0 before the increment and now has the value 1
     * $redis->incr('key1');        // 2
     * $redis->incr('key1');        // 3
     * $redis->incr('key1');        // 4
     * $redis->incrBy('key1', 10);  // 14
     * </pre>
     */
    public function incrBy( $key, $value )
    {
        $this->checkConnection();

        return $this->mc->incrBy( $this->_pre . $key, $value );
    }


    /**
     * Enter and exit transactional mode.
     *
     * @param int Redis::MULTI|Redis::PIPELINE
     * Defaults to Redis::MULTI.
     * A Redis::MULTI block of commands runs as a single transaction;
     * a Redis::PIPELINE block is simply transmitted faster to the server, but without any guarantee of atomicity.
     * discard cancels a transaction.
     * @return Redis returns the Redis instance and enters multi-mode.
     * Once in multi-mode, all subsequent method calls return the same object until exec() is called.
     * @link    http://redis.io/commands/multi
     * @example
     * <pre>
     * $ret = $redis->multi()
     *      ->set('key1', 'val1')
     *      ->get('key1')
     *      ->set('key2', 'val2')
     *      ->get('key2')
     *      ->exec();
     *
     * //$ret == array (
     * //    0 => TRUE,
     * //    1 => 'val1',
     * //    2 => TRUE,
     * //    3 => 'val2');
     * </pre>
     */
    public function multi($mode = 1)
    {
        $this->checkConnection();
        $mode = \Redis::MULTI ;
        if ($mode == 2) {
            $mode = \Redis::PIPELINE;
        }
        return $this->mc->multi($mode);
    }

    /**
     * 管道模式
     * @return mixed
     */
    public function pipeline()
    {
        $this->checkConnection();

        return $this->mc->pipeline();
    }
}
