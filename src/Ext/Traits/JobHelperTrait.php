<?php

namespace ChrisComposer\Ext\Traits;

use app\ext\log\monolog\handler\CustomRotatingFileHandler;
use app\ext\log\monolog\processor\CommonProcessor;
use app\tool\EmailTool;
use app\tool\LogTool;
use app\tool\MonologTool;
use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;
use think\Env;
use const ChrisComposer\Traits\RUNTIME_PATH;

trait JobHelperTrait
{
    /**
     * 日志保存路径
     *
     * @var string
     */
    protected $logSubDir = '';

    /**
     * 是否记录命令日志
     *
     * @var bool
     */
    protected $logEnable = true;

    /**
     * @var $logger \Monolog\Logger
     */
    protected $logger;

    protected function init()
    {
        $this->initLog();
    }

    /**
     * 初始化日志
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/6
     */
    protected function initLog()
    {
        // 创建 RotatingFileHandler，设置日志文件路径，每个日志文件大小为 10MB，最多保留 5 个日志文件
        $fileHandler = new CustomRotatingFileHandler($this->generateLogPath(), 0, Logger::DEBUG, true, 0644, false);
        // $fileHandler->setFormatter(new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context%" . PHP_EOL, "Y-m-d H:i:s", false, true));
        $fileHandler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true));
        // 创建日志实例
        $logger = new Logger('');

        $logger->pushHandler($fileHandler);
        $logger->pushProcessor(new CommonProcessor('job'));
        $this->logger = $logger;
        // 注册日志处理器
        LogTool::getInstance()->registerLogger($logger);
    }

    /**
     * 生成日志路径
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/1/10
     */
    protected function generateLogPath()
    {
        if (!$this->logSubDir) {
            $dirSub = str_replace('\\', '/', static::class);
            $dirSub = str_replace(['app/', 'job/'], ['', ''], $dirSub);
            $this->logSubDir = $dirSub;
        }
        $dir = RUNTIME_PATH . 'log-job/' . $this->logSubDir;
        return MonologTool::generateSubFilePath($dir);
    }

    /**
     * 递归创建目录
     *
     * @param $dir string 目录或文件地址（绝对路径）
     *
     * @return bool
     */
    protected function create_folders($dir)
    {
        return is_dir($dir) or ($this->create_folders(dirname($dir)) and mkdir($dir, 0755));
    }

    /**
     * 邮件通知
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/2/23
     */
    protected function emailNotify($content, $type = 'info', $to = '')
    {
        $enable = Env::get('email_notify_to_developer.enable', true);
        if (!$enable) {
            return;
        }
        if ($type === 'error') {
            $subject = "job：{$this->description} error";
        }
        else {
            $subject = "job：{$this->description}";
        }
        EmailTool::getInstance()->sendToDeveloper($subject, $content, $to);
    }

    /**
     * 异常邮件通知
     *
     * @param        $content
     * @param string $type
     * @param string $to
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/2/24
     */
    protected function exceptionEmailNotify(\Exception $e, $to = '')
    {
        $enable = Env::get('email_notify_to_developer.enable', true);
        if (!$enable) {
            return;
        }
        $subject = "job：{$this->description} exception";
        EmailTool::getInstance()->sendException($e, $subject, $to);
    }
}
