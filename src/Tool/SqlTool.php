<?php

namespace ChrisComposer\Tool;

use think\App;
use think\Db;

class SqlTool
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
     * @param $whereArray array
     *
     * @return mixed
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/10
     */
    public static function handleWhereArray($whereArray)
    {
        if (count($whereArray) === 1) {
            $output = $whereArray[0];
        }
        else {
            $output = $whereArray;
        }

        return $output;
    }

    public function sqlGetSchemaTableNameList($database, $tableName, $field = 'table_name')
    {
        return "
SELECT table_name AS {$field}
FROM information_schema.TABLES
WHERE table_name LIKE '{$tableName}' AND table_schema = '{$database}'";
    }

    public function sqlGetSchemaTableNameListByRightLike($database, $tableName, $field = 'table_name')
    {
        return "
SELECT table_name AS {$field}
FROM information_schema.TABLES
WHERE table_name LIKE '{$tableName}%' AND table_schema = '{$database}'";
    }

    public function getSchemaTableNameListByRightLike($database, $tableName, $isOnlyValue = true)
    {
        $result = Db::query($this->sqlGetSchemaTableNameListByRightLike($database, $tableName));
        if ($isOnlyValue === false) {
            return $result;
        }

        $output = [];
        foreach ($result as &$item) {
            $output[] = $item['table_name'];
        }
        return $output;
    }

    /**
     * @param $row array
     * @param $alias string 别名
     * @param $relationName string 关联名
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/5/16
     */
    public function generateRelation($row, $alias, $relationName = '')
    {
        $relationName = $relationName ?: $alias;
        $output = [];
        foreach ($row as $key => $item) {
            $search = "{$alias}__";
            if (strpos($key, $search) === 0) {
                $field = str_replace($search, '', $key);
                $output[$field] = $item;

                unset($row[$key]);
            }
        }
        $row[$relationName] = $output;

        return $row;
    }
}
