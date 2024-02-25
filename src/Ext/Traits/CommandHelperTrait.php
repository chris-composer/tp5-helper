<?php

namespace ChrisComposer\Ext\Traits;

use app\admin\model\TaskCommand;
use app\admin\model\TaskCommandLog;
use app\ext\log\monolog\handler\CustomRotatingFileHandler;
use app\ext\log\monolog\processor\CommonProcessor;
use app\tool\EmailTool;
use app\tool\LogTool;
use app\tool\MonologTool;
use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;
use think\App;
use think\Cache;
use think\console\Input;
use think\console\Output;
use think\Env;
use think\Exception;
use const ChrisComposer\Traits\ROOT_PATH;

trait CommandHelperTrait {

    /**
     * 日志保存路径
     * @var string
     */
    protected $logSubDir = '';

    /**
     * @var $logger \Monolog\Logger
     */
    protected $logger;

    /**
     * 是否记录命令日志
     * @var bool
     */
    protected $logCommandEnable = true;

    /**
     * @var $taskCommand TaskCommand
     */
    protected $taskCommand;

    /**
     * @var $taskCommandLog TaskCommandLog
     */
    protected $taskCommandLog;

    /**
     * 初始化日志
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/6
     */
    protected function initLog($logName = '')
    {
        // 创建 RotatingFileHandler，设置日志文件路径，每个日志文件大小为 10MB，最多保留 5 个日志文件
        $fileHandler = new CustomRotatingFileHandler($this->generateLogPath(), 0, Logger::DEBUG, true, 0644, false);
        // $fileHandler->setFilenameFormat('{date}', 'Y-m-d');
        // $fileHandler->setFormatter(new LineFormatter("[%datetime%] %level_name%: %message% %context%" . PHP_EOL, "Y-m-d H:i:s", false, true));

        $fileHandler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true));
        // 创建日志实例
        $logger = new Logger('');

        $logger->pushHandler($fileHandler);
        $logger->pushProcessor(new CommonProcessor('command'));

        $this->logger = $logger;
        // 注册日志处理器
        LogTool::getInstance()->registerLogger($this->logger);
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
            $dirSub = str_replace(['app/', 'command/'], ['', ''], $dirSub);
            $this->logSubDir = $dirSub;
        }
        $dir = ROOT_PATH . 'runtime/log-command/' . $this->logSubDir;
        return MonologTool::generateSubFilePath($dir);
    }

    /**
     * 任务日志初始化
     * @param \think\console\Input  $input
     * @param \think\console\Output $output
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/13
     */
    protected function taskLogInit(Input $input, Output $output)
    {
        // 日志
        $options = $input->getOptions();
        unset($options['help'], $options['help'], $options['version'], $options['quiet'], $options['verbose'],
            $options['ansi'], $options['no-ansi'], $options['no-interaction'], $options['simulate'], $options['env'],
            $options['no-debug'], $options['daemon'], $options['command']);
        foreach ($options as &$option) {
            if (mb_strlen($option) > 150) {
                $option = mb_substr($option, 0, 150) . '...';
            }
        }
        unset($option);
        $cmdParams = [
            'arguments' => $input->getArguments(),
            'options' => $options,
        ];

        MonologTool::info('命令[' . $this->getName() . ']', $cmdParams, true, true);

        // 唯一性
        $taskCommand = TaskCommand::where('command', $this->getName())->find();
        if (!$taskCommand) {
            $this->logError('该命令未登记到数据库');
            return false;
        }
        if (TaskCommand::checkExec($taskCommand) === false) {
            throw new Exception('该命令是唯一且正在执行');
        }
        // 更新
        $taskCommand['status'] = TaskCommand::STATUS_RUNNING;
        $taskCommand['last_exec_time'] = time();
        $taskCommand->save();

        $this->taskCommand = $taskCommand;

        // 命令日志表
        $pid = getmypid(); // 获取当前进程 id
        $this->taskCommandLog = TaskCommandLog::create([
            'task_command_id' => $taskCommand->id,
            'process_id' => $pid,
            'params' => json_encode($cmdParams),
            'status' => TaskCommandLog::STATUS_RUNNING,
            'start_time' => time()
        ]);

        return true;
    }

    /**
     * 任务日志结束
     * @param \think\console\Input  $input
     * @param \think\console\Output $output
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/13
     */
    protected function taskLogEnd($endStatus = TaskCommandLog::END_STATUS_NORMAL, $errorMsg = '')
    {
        if (!$this->taskCommand) {
            return false;
        }
        // 更新日志
        $this->taskCommandLog->save([
            'status' => TaskCommandLog::STATUS_END,
            'end_time' => time(),
            'end_status' => $endStatus,
            'error_msg' => $errorMsg
        ]);

        // 更新状态为等待中
        TaskCommand::updateStatusWait($this->taskCommand->id);
    }

    /**
     * 打印
     *
     * @param $message
     */
    protected function print($message, $type = 'info')
    {
        $this->output->$type("[" . date('Y-m-d H:i:s', time()) . "]: " . $message);
    }

    /**
     * 记录日志
     *
     * @param $message string 内容
     * @param $isPrint bool 是否打印
     */
    protected function log($message, $isPrint = true, $printType = LogTool::TYPE_INFO, $force = false)
    {
        // 命令日志
        $this->logCommand($message, $printType, $force);

        if ($isPrint) {
            if ($printType !== LogTool::TYPE_INFO) {
                $message = "{$printType}。$message";
            }
            $this->print($message, $printType);
        }
    }

    /**
     * 记录错误日志
     * @param       $message
     * @param bool  $isPrint
     * @param false $force
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/2/17
     */
    protected function logError($message, $isPrint = true)
    {
        $this->log($message, $isPrint, LogTool::TYPE_ERROR);
    }

    protected function logForce($message, $isPrint = true, $printType = LogTool::TYPE_INFO)
    {
        $this->log($message, $isPrint, $printType, true);
    }

    /**
     * 记录命令日志
     * @param $message string 内容
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/2/14
     */
    protected function logCommand($message, $printType = LogTool::TYPE_INFO, $force = false)
    {
        if ($this->logCommandEnable) {
            if (App::$debug || $printType === LogTool::TYPE_ERROR || $force) {
                $this->logger->$printType($message);
            }
        }
    }

    /**
     * 执行锁定
     * @author Chris Yu <chrisyu@crabapple.top> 2023/2/22
     */
    protected function lockExec()
    {
        $lockName = $this->getName() . "_lock";
        $val = Cache::get($lockName, false);
        Cache::set($lockName, true);

        if ($val) {
            $this->logError("该命令已添加执行锁，请等待解锁再来执行");
        }
    }

    /**
     * 执行解锁
     * @author Chris Yu <chrisyu@crabapple.top> 2023/2/22
     */
    protected function unlockExec()
    {
        $lockName = $this->getName() . "_lock";
        Cache::set($lockName, false);
    }

    /**
     * 邮件通知
     * @author Chris Yu <chrisyu@crabapple.top> 2023/2/23
     */
    protected function emailNotify($content, $type = 'info', $to = '')
    {
        $enable = Env::get('email_notify_to_developer.enable', true);
        if (!$enable) {
            return;
        }
        if ($type === 'error') {
            $subject = "命令：{$this->getName()}[{$this->getDescription()}] error";
        }
        else {
            $subject = "命令：{$this->getName()}[{$this->getDescription()}]";
        }
        EmailTool::getInstance()->sendToDeveloper($subject, $content, $to);
    }

    /**
     * 异常邮件通知
     * @param        $content
     * @param string $type
     * @param string $to
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/2/24
     */
    protected function exceptionEmailNotify(\Throwable $e, $to = '')
    {
        $enable = Env::get('email_notify_to_developer.enable', true);
        if (!$enable) {
            return;
        }
        $subject = "命令：{$this->getName()}[{$this->getDescription()}] exception";
        EmailTool::getInstance()->sendException($e, $subject, $to);
    }
}
