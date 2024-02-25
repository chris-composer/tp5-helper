<?php

namespace ChrisComposer\Tool;

use think\console\Output;

/**
 * Class ConsoleTool
 *
 * @method void info($message)
 * @method void error($message)
 * @method void comment($message)
 * @method void warning($message)
 * @method void highlight($message)
 * @method void question($message)
 */
class ConsoleTool
{
    const TYPE_INFO = 'info';
    const TYPE_ERROR = 'error';
    const TYPE_COMMENT = 'comment';
    const TYPE_WARNING = 'warning';
    const TYPE_HIGHLIGHT = 'highlight';
    const TYPE_QUESTION = 'question';

    /**
     * @var Output
     */
    protected $consoleOutput;

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

    public function __construct()
    {
        $this->consoleOutput = new Output();
    }

    /**
     * 输出
     * @param $action
     * @param $msg
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/5/9
     */
    public function output($action, $msg)
    {
        $action = $this->handleOutputAction($action);

        if ($action !== self::TYPE_INFO) {
            $msg = "{$action}。$msg";
        }
        $msg = "[" . date('Y-m-d H:i:s', time()) . "]: " . $msg;
        $this->consoleOutput->$action($msg);
    }

    public function handleOutputAction($action)
    {
        $match = [
            MonologTool::TYPE_DEBUG => self::TYPE_INFO,
            MonologTool::TYPE_INFO => self::TYPE_INFO,
            MonologTool::TYPE_NOTICE => self::TYPE_COMMENT,
            MonologTool::TYPE_WARNING => self::TYPE_WARNING,
            MonologTool::TYPE_ERROR => self::TYPE_ERROR,
            MonologTool::TYPE_CRITICAL => self::TYPE_ERROR,
            MonologTool::TYPE_ALERT => self::TYPE_ERROR,
            MonologTool::TYPE_EMERGENCY => self::TYPE_HIGHLIGHT,
        ];

        return $match[$action];
    }
}
