<?php

namespace ChrisComposer\Ext\Traits;

use app\tool\EmailTool;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use think\App;
use think\Env;

trait EnumTrait
{
    /**
     * 获取列表
     *
     * @return array
     * @throws \PhpEnum\Exceptions\IllegalArgumentException
     * @throws \PhpEnum\Exceptions\InstantiationException
     * @author Chris Yu <chrisyu@crabapple.top> 2022/8/28
     */
    public static function getList($isAll = false)
    {
        $array = self::values();
        $result = [];
        foreach ($array as $item) {
            if ($isAll) {
                $result[] = get_object_vars($item);
            }
            else {
                $result[] = [
                    'key'  => $item->getKey(),
                    'name' => $item->getName()
                ];
            }
        }
        return $result;
    }

    /**
     * 获取 map
     *
     * @return array
     * @throws \PhpEnum\Exceptions\IllegalArgumentException
     * @throws \PhpEnum\Exceptions\InstantiationException
     * @author Chris Yu <chrisyu@crabapple.top> 2022/8/28
     */
    public static function getMap($isAll = false)
    {
        $array = self::values();
        $result = [];
        foreach ($array as $item) {
            if ($isAll) {
                $getObjectVars = get_object_vars($item);
                $result[$getObjectVars['key']] = $getObjectVars;
            }
            else {
                $result[$item->getKey()] = $item->getName();
            }
        }
        return $result;
    }

    /**
     * 根据 key 获取名称
     *
     * @param $key
     *
     * @return string
     * @throws \PhpEnum\Exceptions\IllegalArgumentException
     * @throws \PhpEnum\Exceptions\InstantiationException
     * @author Chris Yu <chrisyu@crabapple.top> 2023/1/15
     */
    public static function getNameByKey($key)
    {
        foreach (self::values() as $item) {
            if ($key == $item->getKey()) {
                return $item->getName();
            }
        }
        return '';
    }

    public static function getColorMap()
    {
        $array = self::values();
        $result = [];
        foreach ($array as $item) {
            $result[$item->getKey()] = $item->getColor();
        }
        return $result;
    }

    /**
     * 获取 name 属性集合
     * @param $keyList
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/7/4
     */
    public static function getNameListByKeyList($keyList)
    {
        $output = [];
        /* @var $item self */
        foreach (self::values() as $item) {
            if (in_array($item->getKey(), $keyList)) {
                $output[] = $item->getName();
            }
        }
        return $output;
    }
}

