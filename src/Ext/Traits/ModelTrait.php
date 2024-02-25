<?php

namespace ChrisComposer\Ext\Traits;

use app\tool\CodeStyleTool;
use think\Exception;

trait ModelTrait
{
    /**
     * @param $input int|object|\app\admin\model\Customer
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/1/11
     */
    public static function getOne($input)
    {
        if (is_int($input) || is_string($input)) {
            return self::where(['id' => $input])->find();
        }
        else if (is_object($input) || $input instanceof self) {
            return $input;
        }
    }

    public static function getListByIdList($idList, $field = '*', $fieldExcept = false)
    {
        return self::where('id', 'in', $idList)->field($field, $fieldExcept)->select();
    }

    public static function getFieldListByIdList($idList, $field = 'id')
    {
        return self::where('id', 'in', $idList)->column($field);
    }

    public static function selectOneOrFail(callable $callback)
    {
        $list = $callback();
        if (count($list) > 1) {
            throw new Exception("查询结果超过1条");
        }
        return $list ? $list[0] : null;
    }

    /**
     * 开关软删除
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/6
     */
    public function switchSoftDelete(bool $status)
    {
        if ($status) {
            $this->deleteTime = 'deletetime';
        }
        else {
            $this->deleteTime = false;
        }
    }

    /**
     * 保存当前数据对象【含转换字段风格】
     * @access public
     * @param array  $data     数据
     * @param array  $where    更新条件
     * @param string $sequence 自增序列名
     * @return integer|false
     */
    public function saveWithConvertFieldStyle($data = [], $where = [], $sequence = null, $fieldStyle = CodeStyleTool::STYLE_SNAKE_CASE)
    {
        $codeStyleTool = CodeStyleTool::getInstance();
        $dataNew = $codeStyleTool->arrayTo($data, $fieldStyle);
        return $this->save($dataNew, $where, $sequence);
    }

    /**
     * 保存多个数据到当前数据对象【含转换字段风格】
     * @access public
     * @param array   $dataSet 数据
     * @param boolean $replace 是否自动识别更新和写入
     * @return array|false
     * @throws \Exception
     */
    public function saveAllWithConvertFieldStyle($dataSet, $replace = true, $fieldStyle = CodeStyleTool::STYLE_SNAKE_CASE)
    {
        $codeStyleTool = CodeStyleTool::getInstance();
        $dataSetNew = [];
        foreach ($dataSet as $data) {
            $dataSetNew[] = $codeStyleTool->arrayTo($data, $fieldStyle);
        }
        return $this->saveAll($dataSet, $replace);
    }

    /**
     * 获取表名（不含前缀）
     * @return string|string[]
     * @author Chris Yu <chrisyu@crabapple.top> 2023/4/26
     */
    public function getTableNameWithoutPrefix()
    {
        $prefix = $this->getConfig('prefix');
        $tableName = $this->getTable();
        if ($prefix && strpos($tableName, $prefix) === 0) {
            return substr_replace($tableName, '', 0, strlen($prefix));
        }
        return $tableName;
    }

    public static function insertOrUpdate($saveData, $where)
    {
        $row = self::where($where)->find();
        // 更新
        if ($row) {
            return $row->save($saveData);
        }
        // 新增
        else {
            return self::create($saveData);
        }
    }

    public static function insertOrUpdateMulti($saveData, $where)
    {
        $row = self::where($where)->find();
        // 更新
        if ($row) {
            $saveData += ['updatetime' => time()];
            self::where($where)->update($saveData); // 可以更新多条
        }
        // 新增
        else {
            self::create($saveData);
        }
    }

    /**
     * 生成关联字段
     * @param string $alias 别名
     * @param string|array $field 字段。'*'|'name'|'age'|['name', 'age']
     * @param false  $except 输入的 $field 字段是否为排除的字段
     * @param bool   $isReturnString 返回值是否为字符串
     *
     * @return array|string
     * @author Chris Yu <chrisyu@crabapple.top> 2023/5/30
     */
    public static function generateJoinField($alias, $field = '*', $except = false, $isReturnString = true)
    {
        $field = is_string($field) && $field !== '*' ? explode(',', $field) : $field;
        $tableFields = self::getTableFields();
        $output = [];
        foreach ($tableFields as $itemField) {
            $value = "$alias.$itemField as {$alias}__" . $itemField;
            if ($except && $field !== '*') {
                if (!in_array($itemField, $field)) {
                    $output[] = $value;
                }
            }
            else {
                if ($field === '*' || in_array($itemField, $field)) {
                    $output[] = $value;
                }
            }
        }
        return $isReturnString ? implode(',', $output) : $output;
    }

    /**
     * 生成字段列表
     * @param string|array $field
     * @param false  $except 是否排除
     * @param string $alias
     * @param bool   $isReturnString
     *
     * @return array|false|mixed|string|string[]
     * @author Chris Yu <chrisyu@crabapple.top> 2023/7/31
     */
    public function generateFieldList($field = '', $except = false, $alias = '', $isReturnString = true)
    {
        $fieldList = [];
        if ($field) {
            $fieldList = is_string($field) ? explode(',', $field) : $field;
        }

        $tableFields = self::getTableFields();
        // 排除
        if ($except) {
            if ($fieldList) {
                $fieldList = array_diff($tableFields, $fieldList);
            }
        }
        // 不排除
        else {
            $fieldList = $fieldList ?: $tableFields;
        }

        if ($alias) {
            foreach ($fieldList as &$item) {
                $item = "$alias.$item";
            }
        }

        return $isReturnString ? implode(',', $fieldList) : $fieldList;
    }
}
