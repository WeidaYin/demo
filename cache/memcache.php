<?php
namespace Fend\Cache;

use Fend\Config;

/**
 * Fend Framework
 * 缓存
 * */
class MemCache extends \Fend\Fend
{
    public $mc = '';                    //连接成功时的标识
    public $_pre = '';                  //标识

    private $_config_tag = array();

    /**
     * 预留方法 扩展使用
     *
     **/
    public static function Factory($t=0, $hash='')
    {
        static $mcs = array();

        if (empty($mcs[$t])) {
            $mcs[$t] = new self($t, $hash);
        }


        return $mcs[$t];
    }

    /**
     * 初始化对象
     *
     * @return void
     **/
    private function __construct($t, $hash = '')
    {
        $config = Config::get('memcache');

        $this->mc = new \memcached($t);
        $config   = (!empty($t) && !empty($config[$t])) ? array($config[$t]) : $config;

        //创建对比数组
        $cmp = array();

        foreach ($config as $v) {
            $key       = $v["host"] . "_" . $v["port"];
            $cmp[$key] = 1;
        }

        //如果对比数组有不同或config没有设置，那么重置连接
        //为了兼容fpm下不重启更新config问题
        //由于memcached维持长连接导致addserver重复执行问题
        //这里也要做下config变更更新问题
        if (count(array_diff_assoc($cmp, $this->_config_tag)) || (!empty($config) && count($this->mc->getServerList()) == 0)) {
            $this->_config_tag = array();
            $this->mc->resetServerList();
            foreach ($config as $v) {
                $key                     = $v["host"] . "_" . $v["port"];
                $this->_config_tag[$key] = 1;
                $this->mc->addServer($v['host'], $v['port']);
            }
        }
    }

    public function getObj()
    {
        return $this->mc;
    }

    /**
     * 设置数据缓存
     * 与add|replace比较类似
     * 唯一的区别是: 无论key是否存在,是否过期都重新写入数据
     *
     * @param  string $key    数据的标识
     * @param  string $value  实体内容
     * @param  string $expire 过期时间[天d|周w|小时h|分钟i] 如:8d=8天 默认为0永不过期
     * @param  bool   $iszip  是否启用压缩
     * @return bool
     * */
    public function set($key, $value, $expire = 0)
    {
        $expire = self::setLifeTime($expire);
        return  $this->mc->set($this->_pre . $key, $value, $expire);
    }

    /**
     * 获取数据缓存
     *
     * @param  string $key    数据的标识
     * @return string
     * */
    public function get($key)
    {
        $value = $this->mc->get($this->_pre . $key);
        return $value ? self::rdsCode($value) : $value;
    }



    /**
     * 删除一个数据缓存
     *
     * @param  string $key    数据的标识
     * @param  string $expire 删除的等待时间,好像有问题尽量不要使用
     * @return bool
     * */
    public function del($key)
    {
        return $this->mc->delete($this->_pre . $key);
    }

    /**
     * 格式化过期时间
     * 注意: 限制时间小于2592000=30天内
     *
     * @param  string $t 要处理的串
     * @return int
     *
     */
    private function setLifeTime($t)
    {
        $t = empty($t)?86400:$t;
        if (!is_numeric($t)) {
            switch (substr($t, -1)) {
                case 'w'://周
                    $t = (int) $t * 7 * 24 * 3600;
                    break;
                case 'd'://天
                    $t = (int) $t * 24 * 3600;
                    break;
                case 'h'://小时
                    $t = (int) $t * 3600;
                    break;
                case 'i'://分钟
                    $t = (int) $t * 60;
                    break;
                default:
                    $t = (int) $t;
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
     * @param  string $tp  类型,1编码0为解码
     * @param string $type 编码/解码类型 0 json 1 serialize
     * @return array|string
     * */
    private function rdsCode($str, $tp = 0, $type = 0)
    {
        if ($type) {
            return $tp ? @serialize($str) : @unserialize($str);
        }
        return $tp ? @json_encode($str) : @json_decode($str, true);
    }
}
