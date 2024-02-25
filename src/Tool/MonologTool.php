<?php

namespace ChrisComposer\Tool;

use DateTime;
use Monolog\Logger;
use think\App;
use think\Env;

/**
 * Class MonologTool
 *
 * @method static void debug($message, array $context = [], $isForce = false, $isConsoleOutput = false)
 * @method static void info($message, array $context = [], $isForce = false, $isConsoleOutput = false)
 * @method static void notice($message, array $context = [], $isForce = false, $isConsoleOutput = false)
 * @method static void warning($message, array $context = [], $isForce = false, $isConsoleOutput = false)
 * @method static void error($message, array $context = [], $isForce = false, $isConsoleOutput = false)
 * @method static void critical($message, array $context = [], $isForce = false, $isConsoleOutput = false)
 * @method static void alert($message, array $context = [], $isForce = false, $isConsoleOutput = false)
 * @method static void emergency($message, array $context = [], $isForce = false, $isConsoleOutput = false)
 */
class MonologTool
{
    /**
     * @var static 单例模式
     */
    protected static $instance;

    /**
     * 单例模式
     */
    public static function getInstance()
    {
        if (static::$instance instanceof static) {
            return static::$instance;
        }
        static::$instance = new static();
        return static::$instance;
    }

    protected $traceId;

    /**
     * @var $logger \Monolog\Logger
     */
    protected $logger;

    const TYPE_DEBUG = 'debug';
    const TYPE_INFO = 'info';
    const TYPE_NOTICE = 'notice';
    const TYPE_WARNING = 'warning';
    const TYPE_ERROR = 'error';
    const TYPE_CRITICAL = 'critical';
    const TYPE_ALERT = 'alert';
    const TYPE_EMERGENCY = 'emergency';

    public static function __callStatic($name, $arguments)
    {
        $logger = self::getInstance()->getLogger();

        [$message, $context, $isForce, $isConsoleOutput] = [$arguments[0], $arguments[1] ?? [], $arguments[2] ?? false, $arguments[3] ?? false];

        if ($logger instanceof Logger && (App::$debug || $isForce || $name !== self::TYPE_INFO)) {
            // 日志打印
            call_user_func_array([$logger, $name], [$message, $context]);
            // 终端打印
            if (!IS_CLI) {
                return false;
            }
            if ($isConsoleOutput) {
                ConsoleTool::getInstance()->output($name, $message);
            }
        }
    }

    /**
     *
     * @param $baseDir
     *
     * @return mixed|string
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/29
     */
    public static function generateSubFilePath($baseDir)
    {
        $now = new DateTime(); // 创建一个表示当前日期和时间的 DateTime 对象
        $year = $now->format('Y'); // 当前年份，如：2023
        $month = $now->format('m'); // 当前月份，如：06
        $day = $now->format('d'); // 当前日

        $baseDir .= '/' . $year . '/' . $month;
        $globPattern = $baseDir . '/' . $day . '*.log';
        $globResult = glob($globPattern);
        if ($globResult) {
            return FileTool::getLastCreateTimeFilePath($globResult);
        }
        else {
            return $baseDir . '/' . $day . '.log';
        }
    }

    /**
     * 注册日志处理器
     * @param $logger \Monolog\Logger
     * @author Chris Yu <chrisyu@crabapple.top> 2023/4/10
     */
    public function registerLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return \Monolog\Logger
     * @author Chris Yu <chrisyu@crabapple.top> 2023/4/17
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @return string
     */
    public function getTraceId()
    {
        return $this->traceId;
    }

    public function setTraceId($length = 32)
    {
        $this->traceId = substr(hash('md5', uniqid('', true)), 0, $length);
    }
}
