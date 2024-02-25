<?php

namespace ChrisComposer\Ext\Traits;

use app\ext\log\monolog\handler\CustomRotatingFileHandler;
use app\ext\log\monolog\processor\CommonProcessor;
use app\tool\LogTool;
use app\tool\MonologTool;
use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;
use think\App;
use think\Env;
use think\Request;
use function ChrisComposer\Traits\request;
use const ChrisComposer\Traits\ROOT_PATH;

trait ApiHelperTrait
{
    /**
     * 日志保存路径
     *
     * @var string
     */
    protected $logSubDir = '';

    /**
     * 是否记录命令日志
     *
     * @var bool
     */
    protected $logEnable = true;

    /**
     * @var $logger \Monolog\Logger
     */
    protected $logger;

    protected function init()
    {
        $this->initLog();
        // 数据接收日志
        $logParams = [
            'url' => $this->request->baseUrl(true),
            'method' => $this->request->method(),
            'ip' => $this->request->ip(),
            'getParams' => $this->request->get(),
            'postParams' => $this->request->post(),
        ];
        MonologTool::info("请求信息", $logParams, true, true);
    }

    /**
     * 初始化日志
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/6
     */
    protected function initLog($logName = '')
    {
        // 创建 RotatingFileHandler，设置日志文件路径，每个日志文件大小为 10MB，最多保留 5 个日志文件
        $fileHandler = new CustomRotatingFileHandler($this->generateLogPath(), 0, Logger::DEBUG, true, 0644, false);
        // $fileHandler->setFilenameFormat('{date}', 'Y-m-d');
        // $fileHandler->setFormatter(new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context%\n", "Y-m-d H:i:s", false, true));
        $fileHandler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true));
        // 创建日志实例
        $logger = new Logger($logName ?: basename(static::class));

        $logger->pushHandler($fileHandler);
        $logger->pushProcessor(new CommonProcessor('api'));

        $this->logger = $logger;

        // 注册日志处理器
        LogTool::getInstance()->registerLogger($this->logger);
    }

    /**
     * 生成日志路径
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/1/10
     */
    protected function generateLogPath()
    {
        if (!$this->logSubDir) {
            $this->logSubDir = request()->module() . '/' . request()->controller() . '/' . request()->action();
        }
        $dir = ROOT_PATH . 'runtime/log-api/' . $this->logSubDir;
        return MonologTool::generateSubFilePath($dir);
    }

    protected function successPlus($msg = '', $data = null, $code = 0, $type = null, array $header = [])
    {
        $this->success($msg, $data, $code, $type, $header = []);
    }

    protected function errorPlus($msg = '', $data = null, $code = 0, $type = null, array $header = [])
    {
        if ($this->logger) {
            $this->logger->error($msg);
        }
        $this->error($msg, $data, $code, $type, $header = []);
    }
}
