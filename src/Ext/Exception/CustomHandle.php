<?php

namespace ChrisComposer\Ext\Exception;

use app\admin\service\redis\RedisLockService;
use app\tool\EmailTool;
use app\tool\ExceptionTool;
use app\tool\MonologTool;
use think\App;
use think\Config;
use think\exception\HttpException;
use think\Log;
use think\Response;

/**
 * 自定义异常处理类
 * 注意：$this->success(), $this->error() 产生的 HttpResponseException 不会被抓取
 * Class CustomHandle
 */
class CustomHandle extends \think\exception\Handle
{
    /**
     * Report or log an exception.
     *
     * @param  \Exception $exception
     * @return void
     */
    public function report(\Exception $exception)
    {
        if (!$this->isIgnoreReport($exception)) {
            // 收集异常数据
            $data = [
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'message' => $this->getMessage($exception),
                'code'    => $this->getCode($exception),
            ];
            $log = "[{$data['code']}]{$data['message']}[{$data['file']}:{$data['line']}]";

            if (Config::get('record_trace')) {
                $log .= "\r\n" . $exception->getTraceAsString();
            }

            Log::record($log, 'error');
        }
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Exception $e
     * @return Response
     */
    public function render(\Exception $e)
    {
        if ($this->render && $this->render instanceof \Closure) {
            $result = call_user_func_array($this->render, [$e]);
            if ($result) {
                return $result;
            }
        }

        // 触发异常回调
        ExceptionTool::getInstance()->triggerExceptionCallback($e);
        RedisLockService::getInstance()->triggerExceptionCallback($e);

        // 记录日志
        MonologTool::error(ExceptionTool::getCommonMsg($e), [], true, true);

        if ($e instanceof HttpException) {
            return $this->renderHttpException($e);
        }
        // 业务异常
        elseif ($e instanceof BusinessException) {
            return $this->renderBusinessException($e);
        }
        else {
            return $this->convertExceptionToResponse($e);
        }
    }

    protected function renderBusinessException($e)
    {
        $data = [
            'code'    => $this->getCode($e),
            'message' => $this->getMessage($e),
        ];

        return json([
            'code' => $data['code'],
            'msg'  => $data['message'],
            'data' => '',
            'url'  => ''
        ]);
    }

    /**
     * @param \Exception $exception
     *
     * @return array|\think\Response
     * @author Chris Yu <chrisyu@crabapple.top> 2023/1/12
     */
    protected function convertExceptionToResponse(\Exception $exception)
    {
        // 调试模式，获取详细的错误信息
        if (App::$debug) {
            $data = [
                'name'    => get_class($exception),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'message' => $this->getMessage($exception),
                'trace'   => $exception->getTrace(),
                'code'    => $this->getCode($exception),
                'source'  => $this->getSourceCode($exception),
                'datas'   => $this->getExtendData($exception),
                'tables'  => [
                    'GET Data'              => $_GET,
                    'POST Data'             => $_POST,
                    'Files'                 => $_FILES,
                    'Cookies'               => $_COOKIE,
                    'Session'               => isset($_SESSION) ? $_SESSION : [],
                    'Server/Request Data'   => $_SERVER,
                    'Environment Variables' => $_ENV,
                    'ThinkPHP Constants'    => $this->getConst(),
                ],
            ];
        } else {
            // 部署模式仅显示 Code 和 Message
            $data = [
                'code'    => $this->getCode($exception),
                'message' => $this->getMessage($exception),
            ];

            if (!Config::get('show_error_msg')) {
                // 不显示详细错误信息
                $data['message'] = Config::get('error_message');
            }
        }

        // 发送给开发人员。todo HttpException 这个不发送，不然邮箱要爆炸了，会经常有爬虫程序访问我们网站
        if (!$exception instanceof HttpException) {
            EmailTool::getInstance()->sendException($exception);
        }

        if ($this->isApi()) {
            if (App::$debug) {
                if (!empty($data['tables']['Server/Request Data'])) {
                    $data['tables']['Server/Request Data'] = $this->array2utf8($data['tables']['Server/Request Data']);
                }
                return json([
                    'code' => $data['code'],
                    'msg'  => $data['message'],
                    'data' => $data,
                    'url'  => ''
                ]);
            }
            else {
                return json([
                    'code' => $data['code'],
                    'msg'  => $data['message'],
                    'data' => '',
                    'url'  => ''
                ]);
            }
        }

        return $this->renderExceptionHtml($exception, $data);
    }

    protected function renderExceptionHtml($exception, $data)
    {
        //保留一层
        while (ob_get_level() > 1) {
            ob_end_clean();
        }

        $data['echo'] = ob_get_clean();

        ob_start();
        extract($data);
        include Config::get('exception_tmpl');
        // 获取并清空缓存
        $content  = ob_get_clean();
        $response = new Response($content, 'html');

        if ($exception instanceof HttpException) {
            $statusCode = $exception->getStatusCode();
            $response->header($exception->getHeaders());
        }

        if (!isset($statusCode)) {
            $statusCode = 500;
        }
        $response->code($statusCode);
        return $response;
    }

    /**
     * 判断是否为 api 请求
     * @author Chris Yu <chrisyu@crabapple.top> 2023/1/12
     */
    protected function isApi()
    {
        $module = request()->module();
        return $module === 'api' || request()->isAjax() || strpos(request()->header('accept'), 'json') !== false;
    }

    /**
     * 获取常量列表
     * @return array 常量列表
     */
    private static function getConst()
    {
        return get_defined_constants(true)['user'];
    }

    protected function array2utf8($array)
    {
        $array = array_map(function ($value) {
            if (is_array($value)) {
                return $this->array2utf8($value);
            }
            else {
                return mb_convert_encoding($value, "UTF-8", "GB2312");
            }
        }, $array);
        return $array;
    }
}