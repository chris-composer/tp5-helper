<?php

namespace ChrisComposer\Ext\Traits;

use app\ext\exception\BusinessException;

/**
 * Trait ValidateTrait
 * 自定义验证必须含关键词 custom
 *
 * @package app\traits
 */
trait ValidateTrait
{
    /**
     * 字段必须有
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/28
     */
    protected function customExist($value, $rule, $data, $field, $message)
    {
        if (!isset($data[$field])) {
            return "字段\"{$field}\"不存在";
        }
        return true;
    }

    /**
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/28
     */
    protected function customString($value, $rule, $data, $field, $message)
    {
        if (!is_string($data[$field])) {
            return "字段\"{$field}\"必须是字符串";
        }
        return true;
    }
}
