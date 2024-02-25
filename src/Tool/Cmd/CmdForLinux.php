<?php

namespace ChrisComposer\Tool\Cmd;

use app\tool\MonologTool;
use Illuminate\Support\Facades\Log;

class CmdForLinux implements CmdInterface
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
    protected $php = '/usr/bin/php';

    public function __construct($options = [])
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
            $fullCmd = "cd " . $this->rootPath . " && $subCmd > /dev/null &"; //linux系统中&符号 是异步执行
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
        $process = proc_open($fullCmd, $descriptorspec, $pipes);

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
     * @param $isLaravel bool 是否是 laravel 命令
     * @param $options mixed 选项
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/2/13
     */
    public function asyncCall($cmd, $options = ['isThink' => true], &$fullCmd = '')
    {
        $php = $this->php;
        $isThink = $options['isThink'];
        if ($isThink) {
            $subCmd = "$php think $cmd";
            $fullCmd = "cd " . $this->rootPath . " && $subCmd > /dev/null &"; //linux系统中&符号 是异步执行
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

    /**
     * 结束进程
     * @author Chris Yu <chrisyu@crabapple.top> 2023/5/26
     */
    public function killProcess($pid, &$errorMsg = '')
    {
        $errorMsg = '';
        $output = posix_kill($pid, SIGTERM);
        if ($output === false) {
            $errorMsg = posix_strerror(posix_get_last_error());
        }

        return $output;
    }

    /**
     * 根据关键词判断进程是否存在
     * @param $keyword
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/9/8
     */
    public function isProcessExistsByKeyword($keyword)
    {
        $user = 'www';
        $command = "ps aux | grep '$keyword' | grep -v grep | awk '{print $1, $2}' | grep -w {$user} | awk '{print $2}'";
        $execResult = $this->replaceShellExecCmd($command);

        return trim($execResult) ? true : false;
    }

    /**
     * 根据关键词获取进程 id
     * @param $keywords
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/8
     */
    public function getPidByKeyword($keyword, $filterKeyword = '')
    {
        $processIdsArray = [];
        $user = 'www'; // todo 只能结束所有者的，后期可以考虑放到配置里。
        if ($filterKeyword) {
            $command = "ps aux | grep '$keyword' | grep -v grep | awk '{print $1, $2}' | grep -w {$user} | awk '{print $2}'";
            $execResult = $this->replaceShellExecCmd($command);

            if ($execResult) {
                $processIdsArray = explode("\n", trim($execResult));
                foreach ($processIdsArray as $keyProcessId => $itemProcessId) {
                    $command = "ps -p $itemProcessId -o cmd";
                    $execResult = $this->replaceShellExecCmd($command);
                    if ($execResult && strpos($execResult, $filterKeyword) !== false) {
                        unset($processIdsArray[$keyProcessId]);
                    }
                }
            }
        }
        else {
            $command = "ps aux | grep '$keyword' | grep -v grep | awk '{print $1, $2}' | grep -w {$user} | awk '{print $2}'";
            $execResult = $this->replaceShellExecCmd($command);
            if ($execResult) {
                $processIdsArray = explode("\n", trim($execResult));
            }
        }

        return $processIdsArray;
    }

    /**
     * 杀死进程根据关键词
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/8
     */
    public function killProcessByKeyword($keyword, &$errorMsg = '')
    {
        $pidList = $this->getPidByKeyword($keyword);

        foreach ($pidList as $pid) {
            $res = $this->killProcess($pid, $errorMsg);
            if (!$res) {
                return false;
            }
        }
        return true;
    }

    /**
     * 获取父进程 id
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/9
     */
    public function getppid()
    {
        $ppid = posix_getppid();
        return $ppid;
    }

    /**
     * 获取进程创建时间
     * @param $pid
     *
     * @return false|int|mixed
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/9
     */
    public function getProcessCreateTimeByPid($pid)
    {
        $psOutput = $this->replaceExecCmd("ps -p $pid -o lstart=");
        // 将创建时间转换为时间戳
        $createTime = strtotime($psOutput);

        return $createTime;
    }

    /**
     * 检测进程是否在运行
     * @param $pid
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/13
     */
    public function checkProcessRunningByPid($pid)
    {
        $execResult = $this->replaceExecCmd("ps -p $pid --no-headers");
        $output = $execResult ? true : false;

        return $output;
    }

    /**
     * 获取进程状态
     * @param      $pid
     * @param bool $isFormatterSize 是否格式化空间大小
     *
     * @link https://deepinout.com/linux-cmd/linux-process-service-management-cmd/linux-cmd-ps.html
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/15
     */
    public function getProcessStatusByPid($pid, $isFormatterSize = true)
    {
        /**
         * 使用ps命令获取进程信息
         * user: 进程所有者用户名
         * rss: 内存占用大小（单位：KB）
         * rsz: 进程使用的交换空间(虚拟内存 kb)
         * %mem: 进程使用内存的百分比
         * pcpu: 进程占用 cpu 百分比
         * %cpu: 进程自最近一次刷新以来所占用的CPU时间和总时间的百分比
         * state: 进程状态
         * start: 进程开始运行时间
         * time: 进程使用的总 cpu 时间
         * command/cmd: 执行的命令
         */
        $command = 'ps -p ' . $pid . ' -o user,rss,vsz,%mem,pcpu,state,time --no-headers | awk -v OFS=\',\' \'{$1=$1}1\'';
        $execResult = $this->replaceExecCmd($command);

        $execResult = trim($execResult);
        if (empty($execResult)) {
            return null;
        }

        // 解析输出结果
        $output = [];
        $processInfo = explode(",", $execResult);
        $output['user'] = $processInfo[0];
        $output['rss'] = $isFormatterSize ? $this->formatSizeFromKb($processInfo[1]) : $processInfo[1];
        $output['rsz'] = $isFormatterSize ? $this->formatSizeFromKb($processInfo[2]) : $processInfo[2];
        $output['%mem'] = $processInfo[3];
        $output['pcpu'] = $processInfo[4];
        $output['state'] = $processInfo[5];
        $start = $this->getProcessStatusByPidAndField($pid, 'start');
        $output['start'] = $start ? $this->formatterLinuxProcessStartTime($start) : '';
        $output['time'] = $processInfo[6];

        return $output;
    }

    public function getProcessStatusByPidAndField($pid, $field)
    {
        $command = "ps -p $pid -o $field --no-headers";
        $execResult = $this->replaceExecCmd($command);

        return trim($execResult);
    }

    protected function replaceShellExecCmd($cmd)
    {
        if (function_exists('shell_exec')) {
            $execResult = shell_exec($cmd);
        }
        else {
            $execResult = $this->synCall($cmd);
        }

        return $execResult;
    }

    /**
     * @param $cmd
     * @param $output
     * @param $returnVar
     *
     * @return string
     * @author Chris Yu <chrisyu@crabapple.top> 2024/1/15
     */
    protected function replaceExecCmd($cmd, &$output = '', &$returnVar = 0)
    {
        if (function_exists('exec')) {
            $execResult = exec($cmd, $output, $returnVar);
        }
        else {
            $execResult = $this->synCall($cmd);
        }

        return $execResult;
    }

    /**
     * 格式化 linux 进程开始时间
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/16
     */
    protected function formatterLinuxProcessStartTime($startTimeStr)
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

    protected function formatSizeFromKb($size) {
        $units = array('KB', 'MB', 'GB', 'TB');
        $i = 0;
        while ($size >= 1024 && $i < 4) {
            $size /= 1024;
            $i++;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
}
