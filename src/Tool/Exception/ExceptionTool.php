<?php

namespace ChrisComposer\Tool\Exception;

class ExceptionTool
{
    protected $exceptionCallbackList = [];

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

    public function __construct($options = [])
    {
        if (isset($options['rootPath'])) {
            $this->rootPath = $options['rootPath'];
        }
    }

    /**
     * @param $whereArray array
     *
     * @return mixed
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/10
     */
    public static function getCommonMsg(\Throwable $e)
    {
        return str_replace(ROOT_PATH, '', $e->getFile()) . "[" . $e->getLine() . "]: " . $e->getMessage();
    }

    public static function getDebugMsg(\Throwable $e, $isString = true)
    {
        $data = [
            'name'    => get_class($e),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'message' => $e->getMessage(),
            'trace'   => $e->getTrace(),
        ];
        if ($isString) {
            return json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        return $data;
    }

    /**
     * 注册异常回调
     * 如果某个变量需要在异常回调中使用，需要使用use传递，如果该变量需要最新值，则需要引用传递
     * @author Chris Yu <chrisyu@crabapple.top> 2023/4/7
     */
    public function registerExceptionCallback(callable $callback)
    {
        $this->exceptionCallbackList[] = $callback;
    }

    public function triggerExceptionCallback(\Throwable $e)
    {
        if ($this->exceptionCallbackList) {
            foreach ($this->exceptionCallbackList as $exceptionCallback) {
                $exceptionCallback($e);
            }
        }
    }
}
