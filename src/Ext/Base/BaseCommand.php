<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace ChrisComposer\Ext\Base;

use app\admin\model\TaskCommandLog;
use app\admin\service\redis\RedisLockService;
use app\tool\ExceptionTool;
use app\traits\CommandHelperTrait;
use think\App;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\queue\Job;

class BaseCommand extends Command
{
    use CommandHelperTrait;

    protected $numSuccess = 0; // 成功数
    protected $numFail = 0; // 失败总数
    protected $numTotal = 0; // 处理总数
    protected $numDelete = 0; // 处理总数

    /**
     * @var $exceptionCallback callable 发生异常回调
     */
    protected $exceptionCallback;

    protected function execute(Input $input, Output $output)
    {
        $this->initLog();

        $this->taskLogInit($input, $output);

        // 调用开始程序
        if(method_exists(static::class, 'executeBefore')){
            $this->executeBefore();
        }

        $this->logForce("执行开始——————");

        try {
            $this->executeStart($input, $output);
        } catch (\Throwable $e) {
            if ($this->exceptionCallback) {
                ($this->exceptionCallback)($e);
            }

            ExceptionTool::getInstance()->triggerExceptionCallback($e);
            RedisLockService::getInstance()->triggerExceptionCallback($e);

            $commonMsg = ExceptionTool::getCommonMsg($e);
            $this->logError($commonMsg, true);
            $this->exceptionEmailNotify($e);
            $this->taskLogEnd(TaskCommandLog::END_STATUS_EXCEPTION, $commonMsg);
            exit;
        }

        // $this->logForce("执行结束——————");
        $this->taskLogEnd();

        // 调用结束程序
        if(method_exists(static::class, 'executeEnd')){
            $this->executeEnd();
        }
    }
}
