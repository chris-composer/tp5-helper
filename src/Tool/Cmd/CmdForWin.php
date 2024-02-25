<?php

namespace ChrisComposer\Tool\Cmd;

use app\tool\MonologTool;
use Illuminate\Support\Facades\Log;

class CmdForWin implements CmdInterface
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

    protected $rootPath = '';
    protected $php = 'php';

    public function __construct()
    {
        if (isset($options['rootPath'])) {
            $this->rootPath = $options['rootPath'];
        }
        if (isset($options['php'])) {
            $this->php = $options['php'];
        }
    }

    /**
     * 同步调用
     * @author Chris Yu <chrisyu@crabapple.top> 2023/7/14
     */
    public function synCall($cmd, $options = ['isThink' => true], &$fullCmd = '')
    {
        $php = $this->php;
        $isThink = $options['isThink'];
        if ($isThink) {
            $subCmd = "$php think $cmd";
            $fullCmd = "cd " . $this->rootPath . " && start \"$subCmd\" /b cmd /c $subCmd";
        }
        else {
            $fullCmd = $cmd;
        }

        MonologTool::info("调用同步命令", ['fullCmd' => $fullCmd]);

        // 创建进程
        $descriptorspec = array(
            0 => array("pipe", "r"),  // 标准输入
            1 => array("pipe", "w"),  // 标准输出
            2 => array("pipe", "w")   // 标准错误
        );
        $pipes = [];
        $process = proc_open($cmd, $descriptorspec, $pipes);

        $out = '';
        // 读取进程的输出
        while (true) {
            $stdout = fread($pipes[1], 1024);
            if ($stdout === false || strlen($stdout) === 0) {
                // 没有数据可读
                break;
            }

            $out .= $stdout;
        }

        // 关闭进程
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $out;
    }

    /**
     * 异步调用
     *
     * @param $command string 命令
     * @param $isLaravel bool 是否是 think 命令
     * @param $processId int 进程 id
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/2/13
     */
    public function asyncCall($cmd, $options = ['isThink' => true], &$fullCmd = '')
    {
        $php = $this->php;
        $isThink = $options['isThink'];
        if ($isThink) {
            $subCmd = "$php think $cmd";
            $fullCmd = "cd " . $this->rootPath . " && start \"$subCmd\" /b cmd /c $subCmd";
        }
        else {
            $fullCmd = $cmd;
        }

        MonologTool::info("调用异步命令", ['fullCmd' => $fullCmd]);

        // 开启子进程
        $descriptorspec = array(
            0 => array("pipe", "r"),  // 标准输入
            1 => array("pipe", "w"),  // 标准输出
            2 => array("pipe", "w")   // 标准错误
        );
        $pipes = [];
        $process = proc_open($cmd, $descriptorspec, $pipes);

        // 关闭进程
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
    }

    public function isProcessExistsByKeyword($keyword)
    {
        // TODO: Implement isProcessExistsByKeyword() method.
    }

    public function getPidByKeyword($keyword, $filterKeyword = '')
    {
        // TODO: Implement getPidByKeyword() method.
    }

    public function getppid()
    {
        // TODO: Implement getppid() method.
    }

    public function getProcessCreateTimeByPid($pid)
    {
        // TODO: Implement getProcessCreateTimeByPid() method.
    }

    public function checkProcessRunningByPid($pid)
    {
        // TODO: Implement checkProcessRunningByPid() method.
    }

    public function getProcessStatusByPid($pid, $isFormatterSize = true)
    {
        // TODO: Implement getProcessStatusByPid() method.
    }

    public function getProcessStatusByPidAndField($pid, $field)
    {
        // TODO: Implement getProcessStatusByPidAndField() method.
    }

    public function killProcess($pid, &$errorMsg = '')
    {
        // TODO: Implement killProcess() method.
    }

    public function killProcessByKeyword($keyword, &$errorMsg = '')
    {
        // TODO: Implement killProcessByKeyword() method.
    }
}
