<?php

namespace ChrisComposer\Ext\Base;

use app\admin\model\QueueControl;
use app\admin\model\QueueControlDisableLog;
use app\admin\model\QueueControlRunLog;
use app\admin\model\QueueFail;
use app\admin\service\redis\RedisLockService;
use app\tool\CommandTool;
use app\tool\EmailTool;
use app\tool\ExceptionTool;
use app\tool\MonologTool;
use app\traits\JobHelperTrait;
use think\App;
use think\Db;
use think\queue\Job;
use function ChrisComposer\Ext\config;

/**
 * 消息队列基类
 * Class BaseJobs
 * @package crmeb\basic
 */
class BaseJobs
{
    use JobHelperTrait;

    const DATA_FIELD_DATA = 'data'; // data 字段子字段-data

    protected $description;

    /**
     * @var $queueName string 队列名称
     */
    public static $queueName;

    /**
     * @var $job Job
     */
    protected $job;

    /**
     * @var int 重新发布延迟事件
     */
    protected $releaseDelay = 0;

    /**
     * @var mixed 数据
     */
    protected $data;

    /**
     * @var QueueControl
     */
    protected $queueControlModel;

    /**
     * @var $logger \Monolog\Logger
     */
    protected $logger;

    protected $title = '';

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function __construct()
    {
        $this->init();
    }

    /**
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        $this->fire(...$arguments);
    }

    /**
     * 运行消息队列
     * @param Job $job
     * @param $data
     */
    public function fire(Job $job, $data): void
    {
        $this->job = $job;
        $this->data = $data;
        MonologTool::info("job[" . self::getQueueName() . "]开始 ——————", ['description' => $this->description, 'params' => $data], true, true);

        // 注册队列控制
        $this->registerQueueControl();

        try {
            $action     = $data['do'] ?? 'doJob';//任务名
            $infoData   = $data['data'] ?? [];//执行数据
            $errorCount = $data['errorCount'] ?? 3;//最大错误次数
            $this->runJob($action, $job, $infoData, $errorCount);
        } catch (\Throwable $e) {
            Db::rollback();
            // 删除队列
            $job->delete();
            // 创建失败记录
            $commonMsg = ExceptionTool::getCommonMsg($e);
            $saveData = [
                'queue_name' => static::getQueueName(),
                'job_name' => $this->description,
                'job_class' => static::class,
                'params' => $job->getRawBody(),
                'attempts' => $job->attempts(),
                'error_msg' => $commonMsg,
                'title' => $this->getTitle(),
            ];
            QueueFail::create($saveData);

            MonologTool::error("job 异常：" . $commonMsg, [], true, true);
            // 异常回调
            ExceptionTool::getInstance()->triggerExceptionCallback($e);
            RedisLockService::getInstance()->triggerExceptionCallback($e);
            // 发送邮件
            $this->exceptionEmailNotify($e);
        }

        // MonologTool::info("job[" . $this->description . "]结束 ——————", [], true, true);

        // 结束队列
        $this->disableJob();
    }

    /**
     * 执行队列
     * @param string $action
     * @param Job $job
     * @param array $infoData
     * @param int $errorCount
     */
    protected function runJob(string $action, Job $job, array $infoData, int $errorCount = 3)
    {
        $action = method_exists($this, $action) ? $action : 'handle';
        if (!method_exists($this, $action)) {
            $job->delete();
        }

        if ($this->{$action}($infoData)) {
            //删除任务
            $job->delete();
        } else {
            if ($job->attempts() >= $errorCount && $errorCount) {
                //删除任务
                $job->delete();

                // 写入数据库
                QueueFail::create([
                    'queue_name' => static::getQueueName(),
                    'job_name' => $this->description,
                    'job_class' => static::class,
                    'params' => $job->getRawBody(),
                    'attempts' => $job->attempts(),
                    'title' => $this->getTitle(),
                ]);
            } else {
                //从新放入队列
                $job->release($this->releaseDelay);
            }
        }
    }

    /**
     * 获取队列名称
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/7
     */
    public static function getQueueName()
    {
        return config('queue.prefix') . static::$queueName;
    }

    /**
     * 创建注册队列控制
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/8
     */
    protected function registerQueueControl()
    {
        $queueName = static::getQueueName();
        $this->queueControlModel = QueueControl::getByQueueName($queueName);
        if (!$this->queueControlModel) {
            $this->queueControlModel = QueueControl::create([
                'queue_name' => $queueName,
                'name' => $this->description,
                'description' => $this->description,
                'job_class' => static::class,
                'is_only' => 0,
                'status' => QueueControl::STATUS_RUNNING
            ]);
        }
    }

    /**
     * 禁用
     * @todo 目前使用场景消费者是一个的情况下
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/8
     */
    protected function disableJob()
    {
        $ppid = CommandTool::getppid();
        $queueControlRunLog = QueueControlRunLog::getOneByPid($ppid);
        if ($queueControlRunLog['is_trigger_disable']) {
            $now = time();
            $queueName = $this->queueControlModel->queue_name;

            $queueControlDisableLog = QueueControlDisableLog::getDisableTaskByQueueControlRunId($queueControlRunLog->id);
            // 结束父进程
            $res = CommandTool::killProcess($ppid, $errorMsg);
            if (!$res) {
                $queueControlRunLog->save([
                    'is_trigger_disable' => 0,
                ]);
                $queueControlDisableLog->save([
                    'fail_error_msg' => $errorMsg,
                    'status' => QueueControlDisableLog::STATUS_FAIL,
                ]);
                return false;
            }

            // 数据库记录
            QueueControlRunLog::updateOverByRow($queueControlRunLog, QueueControlRunLog::OVER_STATUS_NORMAL);

            $queueControlDisableLog->save([
                'over_time' => $now,
                'status' => QueueControlDisableLog::STATUS_SUCCESS,
            ]);

            QueueControl::setStatusWaitById($this->queueControlModel->id);

            MonologTool::info("队列已禁用，进程已结束", [__METHOD__ . '[' . __LINE__ . ']'], true, true);

            EmailTool::getInstance()->sendToDeveloper("队列结束。queueName = $queueName", "队列已禁用，进程已结束");
            // 结束子进程。当前进程是由 think-queue 的 proc_open 创建的子进程
            exit(0);
        }
    }
}
