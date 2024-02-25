<?php

namespace ChrisComposer\Tool\Cmd;

use Illuminate\Support\Facades\Log;

interface CmdInterface
{
    /**
     * 同步调用
     *
     * @param        $cmd
     * @param array  $options
     * @param string $cmd
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/7/14
     */
    public function synCall($cmd, $options = []);

    /**
     * 异步调用
     *
     * @param $command string 命令
     * @param $isLaravel bool 是否是 laravel 命令
     * @param $options mixed 选项
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/2/13
     */
    public function asyncCall($command, $options = []);

    /**
     * 根据关键词判断进程是否存在
     * @param $keyword
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/9/8
     */
    public function isProcessExistsByKeyword($keyword);

    /**
     * 根据关键词获取进程 id
     * @param $keywords
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/8
     */
    public function getPidByKeyword($keyword, $filterKeyword = '');

    /**
     * 获取父进程 id
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/9
     */
    public function getppid();

    /**
     * 获取进程创建时间
     * @param $pid
     *
     * @return false|int|mixed
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/9
     */
    public function getProcessCreateTimeByPid($pid);

    /**
     * 检测进程是否在运行
     * @param $pid
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/13
     */
    public function checkProcessRunningByPid($pid);

    /**
     * 获取进程状态
     * @param      $pid
     * @param bool $isFormatterSize 是否格式化空间大小
     *
     * @link https://deepinout.com/linux-cmd/linux-process-service-management-cmd/linux-cmd-ps.html
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/15
     */
    public function getProcessStatusByPid($pid, $isFormatterSize = true);

    /**
     * 获取进程状态根据进程 id 和进程字段
     * @param $pid
     * @param $field
     *
     * @return mixed
     * @author Chris Yu <chrisyu@crabapple.top> 2024/1/15
     */
    public function getProcessStatusByPidAndField($pid, $field);

    /**
     * 结束进程
     * @author Chris Yu <chrisyu@crabapple.top> 2023/5/26
     */
    public function killProcess($pid, &$errorMsg = '');

    /**
     * 杀死进程根据关键词
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/8
     */
    public function killProcessByKeyword($keyword, &$errorMsg = '');
}
