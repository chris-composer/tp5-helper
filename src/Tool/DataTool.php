<?php

namespace ChrisComposer\Tool;

class DataTool
{
    public static $ins;

    /**
     * 单例模式
     */
    public static function getInstance()
    {
        if (self::$ins instanceof self) {
            return self::$ins;
        }
        self::$ins = new self();
        return self::$ins;
    }

    /**
     * 合并数组字符串
     * @param $input array
     * @param $mergeElement mixed 合并的元素
     * @param $mergePosition int 合并的位置，默认是第一个
     * @param $separator string 分隔符
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/6
     */
    public static function mergeListString($input, $mergeElement, $mergePosition = 0, $separator = ',')
    {
        // 分割
        $inputArray = [];
        if ($input) {
            $inputArray = explode($separator, $input);
        }
        // 插入
        array_splice($inputArray, $mergePosition, 0, $mergeElement);
        // 合并
        return implode($separator, $inputArray);
    }

    /**
     * 批量回调处理
     * @param $size
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2024/1/8
     */
    public static function batchCallbackHandle(callable $callback, $list, $size)
    {
        $initSize = 0;
        $batchList = [];
        foreach ($list as &$item) {
            $initSize++;
            $batchList[] = $item;

            if ($initSize % $size === 0) {
                $res = $callback($batchList);
                if (!$res) {
                    return false;
                }
                // 初始化
                $initSize = 0;
                $batchList = [];
            }
        }
        return true;
    }
}
