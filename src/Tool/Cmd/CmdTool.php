<?php

namespace ChrisComposer\Tool\Cmd;

use App\Util\Cmd\CmdForLinux;
use App\Util\Cmd\CmdForWin;

/**
 * 命令工具
 */
class CmdTool
{
    protected $options = [];

    /**
     * @var static 单例模式
     */
    protected static $instance;

    public function __construct($options = [])
    {
        $this->envCheck();
        $this->options = $options;
    }

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

    /**
     * 环境检查
     * @author Chris Yu <chrisyu@crabapple.top> 2024/2/25
     */
    protected function envCheck()
    {
        if (!function_exists('proc_open')) {
            throw new \Exception('请开启 proc_open 函数');
        }
    }

    /**
     * 获取工具
     * @return \app\Util\Cmd\CmdInterface
     * @author Chris Yu <chrisyu@crabapple.top> 2024/1/15
     */
    public function getTool()
    {
        if ($this->isWin()) {
            return CmdForWin::getInstance();
        }
        else {
            return CmdForLinux::getInstance($this->options);
        }
    }

    /**
     * 是否 windows 系统
     * @return bool
     * @author Chris Yu <chrisyu@crabapple.top> 2024/1/15
     */
    public function isWin()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return true;
        }
        else {
            return false;
        }
    }
}
