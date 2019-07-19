<?php

namespace Fend;

class Config
{

    private static $configPath = SYS_ROOTDIR . "/app/config";

    private static $config;

    public static function setConfigPath($path)
    {
        //not found path
        if (!is_dir($path)) {
            throw new \Exception("Config Path not exists :" . $path, 4757);
        }

        self::$configPath = $path;
    }

    public static function get($name)
    {
        //return loaded config
        if (isset(self::$config[$name])) {
            return self::$config[$name];
        }

        //file not exists
        if (!file_exists(self::$configPath . "/" . $name . ".php")) {
            throw new \Exception("Config File not found :" . self::$configPath . "/" . $name . ".php", 4757);
        }

        self::$config[$name] = include(self::$configPath . "/" . $name . ".php");

        return self::$config[$name];

    }

    /**
     * 清空配置，重新加载
     */
    public static function clean()
    {
        self::$config = [];
    }
}