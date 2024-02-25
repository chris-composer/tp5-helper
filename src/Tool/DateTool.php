<?php

namespace ChrisComposer\Tool;

use app\enums\SymbolEnum;
use DateTime;
use fast\Date;
use think\App;
use think\Log;

class DateTool
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
     * 格式化英文格式的时间
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/16
     */
    public static function formatterEnDate($dateStr)
    {
        $year = date('Y');
        $date = DateTime::createFromFormat('M d', $dateStr);
        $date->setDate($year, $date->format('m'), $date->format('d'));
        return $date->format('Y-m-d');
    }

    /**
     * 格式化 linux 进程开始时间
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/16
     */
    public static function formatterLinuxProcessStartTime($startTimeStr)
    {
        $timestamp = strtotime($startTimeStr);
        $date = date("H:i:s", $timestamp);
        if ($date === '00:00:00') {
            return date('Y-m-d', $timestamp);
        }
        else {
            return date('Y-m-d H:i:s', $timestamp);
        }
    }
    
    /**
     * 格式化时长。时间戳 => 可读字符串
     * @param $duration int|string 时长
     * @author Chris Yu <chrisyu@crabapple.top> 2023/2/15
     */
    public static function formatterDuration($duration)
    {
        $duration = (int)$duration;
        $dayTimestamp = 60 * 60 * 24;
        $hourTimestamp = 60 * 60;
        $minuteTimestamp = 60;

        // 日
        $day = floor($duration / $dayTimestamp);
        $dayTimestampTotal = $day * $dayTimestamp;
        // 小时
        $surplusTimestamp = $duration - $dayTimestampTotal;
        $hour = floor($surplusTimestamp / $hourTimestamp);
        $hourTimestampTotal = $hour * $hourTimestamp;
        // 分钟
        $surplusTimestamp = $duration - $dayTimestampTotal - $hourTimestampTotal;
        $minute = floor($surplusTimestamp / $minuteTimestamp);
        $minuteTimestampTotal = $minute * $minuteTimestamp;
        // 秒
        $second = $duration - $dayTimestampTotal - $hourTimestampTotal - $minuteTimestampTotal;

        $output = '';
        if ($day) {
            $output .= "{$day}日";
        }
        if ($hour) {
            $output .= "{$hour}时";
        }
        if ($minute) {
            $output .= "{$minute}分";
        }
        if ($second) {
            $output .= "{$second}秒";
        }
        return $output;
    }

    /**
     * 格式化时长。可读字符串 => 时间戳
     * @param $duration int|string 时长
     * @author Chris Yu <chrisyu@crabapple.top> 2023/2/15
     */
    public static function formatterDurationToTimestamp($durationString)
    {
        // 使用正则表达式提取数字部分
        preg_match('/^(?:([\d]+)天)?(?:([\d]+)时)?(?:([\d]+)分)?(?:([\d]+)秒)?$/', $durationString, $matches);

        // 提取的值分别存储在 $matches 数组中
        $day = !empty($matches[1]) ? $matches[1] : 0;
        $hour = !empty($matches[2]) ? $matches[2] : 0;
        $minute = !empty($matches[3]) ? $matches[3] : 0;
        $second = !empty($matches[4]) ? $matches[4] : 0;

        $dayTimestamp = 60 * 60 * 24;
        $hourTimestamp = 60 * 60;
        $minuteTimestamp = 60;

        $output = 0;
        if ($day) {
            $output += $day * $dayTimestamp;
        }
        if ($hour) {
            $output += $hour * $hourTimestamp;
        }
        if ($minute) {
            $output += $minute * $minuteTimestamp;
        }
        if ($second) {
            $output += $second;
        }
        return $output;
    }

    /**
     * 解析来自日期时间区间组件的值
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/16
     */
    public static function parseValueFromDatetimeRangeModule($value, $formatter = 'Y-m-d H:i:s')
    {
        [$startTime, $endTime] = explode(' - ', $value);
        return [date($formatter, strtotime($startTime)), date($formatter, strtotime($endTime))];
    }

    /**
     * 解析来自日期时间区间组件的值为时间戳
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/16
     */
    public static function parseValueToTimestampFromDatetimeRangeModule($value, $separator = ' - ')
    {
        [$startTime, $endTime] = explode($separator, $value);
        return [strtotime($startTime), strtotime($endTime)];
    }

    /**
     * 解析来自日期时间区间组件的值为时间戳字符串
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/16
     */
    public static function parseValueToTimestampStringFromDatetimeRangeModule($value)
    {
        $separator = SymbolEnum::SEPARATOR_DATETIME_RANGE_VALUE()->getKey();
        [$startTime, $endTime] = explode($separator, $value);
        return strtotime($startTime) . $separator . strtotime($endTime);
    }

    /**
     * 解析来自日期时间组件的值为时间戳
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/16
     */
    public static function parseValueToTimestampFromDatetimePickerModule($value, $separator = ',')
    {
        [$startTime, $endTime] = explode($separator, $value);
        return [$startTime ? strtotime($startTime) : null, $endTime ? strtotime($endTime) : null];
    }

    /**
     * 解析来自日期时间组件的值为时间戳组字符串
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/16
     */
    public static function parseValueToTimestampListStringFromDatetimePickerModule($value, $separator = ',', $options = [])
    {
        [$startTime, $endTime] = explode($separator, $value);

        $startTimestamp = strtotime($startTime);

        if (isset($options['endTimestampMode']) && $options['endTimestampMode'] === 'endOfDay') {
            $endTimestamp = strtotime($endTime . ' +1 day') - 1;
        }
        else {
            $endTimestamp = strtotime($endTime);
        }

        return implode($separator, [$startTimestamp, $endTimestamp]);
    }

    /**
     * 获取月份范围
     * @param        $startMonth
     * @param        $endMonth
     * @param string $outputFormatter
     *
     * @return array
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/30
     */
    public static function generateMonthRangeList($startMonth, $endMonth, $outputFormatter = 'Y-m')
    {
        // 将起始日期和结束日期转换为 DateTime 对象
        $startMonthObj = DateTime::createFromFormat($outputFormatter, $startMonth)->modify('first day of this month');
        $endMonthObj = DateTime::createFromFormat($outputFormatter, $endMonth)->modify('first day of this month');

        // 初始化结果数组
        $months = array();

        // 生成月份列表
        while ($startMonthObj <= $endMonthObj) {
            $months[] = $startMonthObj->format($outputFormatter);
            $startMonthObj->modify('+1 month');
        }

        return $months;
    }

    /**
     * 获取日期范围
     * @param $startDate
     * @param $endDate
     * @param $outputFormatter
     *
     * @return array
     * @author Chris Yu <chrisyu@crabapple.top> 2023/9/17
     */
    public static function generateDateRangeList($startDate, $endDate, $outputFormatter = 'Y-m-d')
    {
        // 将日期字符串转换为时间戳
        $startTimestamp = strtotime($startDate);
        $endTimestamp = strtotime($endDate);

        // 初始化日期数组
        $dateList = array();

        // 从开始日期逐步增加一天，直到结束日期
        while ($startTimestamp <= $endTimestamp) {
            $dateList[] = date($outputFormatter, $startTimestamp);
            $startTimestamp = strtotime('+1 day', $startTimestamp);
        }

        return $dateList;
    }

    /**
     * 从时间字符串中生成秒数。
     * 09:00:00 生成秒数 => 9 * 60 * 60
     * @param $timeString
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/7/13
     */
    public static function generateSecondByTimeString($timeString)
    {
        return strtotime($timeString) - strtotime('00:00:00');
    }

    /**
     * 获取当季时间范围
     * @author Chris Yu <chrisyu@crabapple.top> 2023/9/21
     */
    public static function generateCurrentQuarterRange($format = 'Y-m-d H:i:s')
    {
        $startTimestamp = Date::unixtime('quarter', 0, 'begin');
        $endTimestamp = Date::unixtime('quarter', 0, 'end');
        return [
            $format ? date($format, $startTimestamp) : $startTimestamp,
            $format ? date($format, $endTimestamp) : $endTimestamp,
        ];
    }

    /**
     * 获取当前时间的毫秒
     * @author Chris Yu <chrisyu@crabapple.top> 2023/10/20
     */
    public static function generateCurrentMillSeconds($isString = true)
    {
        list($usec, $sec) = explode(" ", microtime());
        $milliseconds = round(((float)$usec + (float)$sec) * 1000);

        return $isString ? (string)$milliseconds : $milliseconds;
    }
}
