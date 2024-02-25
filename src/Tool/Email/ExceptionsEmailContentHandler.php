<?php

namespace ChrisComposer\Tool\Email;

use app\tool\LogTool;
use app\tool\MonologTool;
use think\Config;
use think\Env;
use think\Lang;
use think\View;

class ExceptionsEmailContentHandler
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

    /**
     * 获取异常内容
     * @param $exceptionInput \Throwable|array
     * @author Chris Yu <chrisyu@crabapple.top> 2023/2/24
     */
    public function getContent($exceptionInput)
    {
        if ($exceptionInput instanceof \Exception) {
            // 获取详细的错误信息
            $data = [
                'name' => get_class($exceptionInput),
                'file' => $exceptionInput->getFile(),
                'line' => $exceptionInput->getLine(),
                'message' => $this->getMessage($exceptionInput),
                'trace' => $exceptionInput->getTrace(),
                'code' => $this->getCode($exceptionInput),
                'source' => $this->getSourceCode($exceptionInput),
                'datas' => $this->getExtendData($exceptionInput),
                'traceId' => MonologTool::getInstance()->getTraceId(),
                'tables' => [
                    'GET Data' => $_GET,
                    'POST Data' => $_POST,
                    'Files' => $_FILES,
                    'Cookies' => $_COOKIE,
                    'Session' => isset($_SESSION) ? $_SESSION : [],
                    'Server/Request Data' => $_SERVER,
                    'Environment Variables' => $_ENV,
                    'ThinkPHP Constants' => $this->getConst(),
                ],
            ];
        }
        else {
            $data = $exceptionInput;
        }

        $view = View::instance(Config::get('template'), Config::get('view_replace_str'));
        $view->assign($data);
        return $view->fetch(APP_PATH . "tool/html/exception_email.phtml");
    }

    /**
     * 获取错误编码
     * ErrorException则使用错误级别作为错误编码
     *
     * @param \Exception $exception
     *
     * @return integer                错误编码
     */
    protected function getCode(\Exception $exception)
    {
        $code = $exception->getCode();
        if (!$code && $exception instanceof ErrorException) {
            $code = $exception->getSeverity();
        }
        return $code;
    }

    /**
     * 获取错误信息
     * ErrorException则使用错误级别作为错误编码
     *
     * @param \Exception $exception
     *
     * @return string                错误信息
     */
    protected function getMessage(\Exception $exception)
    {
        $message = $exception->getMessage();
        if (IS_CLI) {
            return $message;
        }

        if (strpos($message, ':')) {
            $name = strstr($message, ':', true);
            $message = Lang::has($name) ? Lang::get($name) . strstr($message, ':') : $message;
        }
        elseif (strpos($message, ',')) {
            $name = strstr($message, ',', true);
            $message = Lang::has($name) ? Lang::get($name) . ':' . substr(strstr($message, ','), 1) : $message;
        }
        elseif (Lang::has($message)) {
            $message = Lang::get($message);
        }
        return $message;
    }

    /**
     * 获取出错文件内容
     * 获取错误的前9行和后9行
     *
     * @param \Exception $exception
     *
     * @return array                 错误文件内容
     */
    protected function getSourceCode(\Exception $exception)
    {
        // 读取前9行和后9行
        $line = $exception->getLine();
        $first = ($line - 9 > 0) ? $line - 9 : 1;

        try {
            $contents = file($exception->getFile());
            $source = [
                'first' => $first,
                'source' => array_slice($contents, $first - 1, 19),
            ];
        } catch (\Exception $e) {
            $source = [];
        }
        return $source;
    }

    /**
     * 获取异常扩展信息
     * 用于非调试模式html返回类型显示
     *
     * @param \Exception $exception
     *
     * @return array                 异常类定义的扩展数据
     */
    protected function getExtendData(\Exception $exception)
    {
        $data = [];
        if ($exception instanceof \think\Exception) {
            $data = $exception->getData();
        }
        return $data;
    }

    /**
     * 获取常量列表
     *
     * @return array 常量列表
     */
    private static function getConst()
    {
        return get_defined_constants(true)['user'];
    }
}
