<?php

namespace ChrisComposer\Ext\Traits;

use app\admin\model\Admin;
use app\admin\service\AuthService;
use app\ext\log\monolog\handler\CustomRotatingFileHandler;
use app\ext\log\monolog\processor\AdminProcessor;
use app\tool\LogTool;
use app\tool\MonologTool;
use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;
use think\Env;
use think\Hook;
use think\Session;
use function ChrisComposer\Traits\__;
use function ChrisComposer\Traits\url;
use const ChrisComposer\Traits\RUNTIME_PATH;

trait AdminHelperTrait
{
    /**
     * @var bool 是否需要开发人员权限
     */
    protected $isNeedDeveloper = false;

    /**
     * @var bool 是否是开发者
     */
    protected $isDeveloper = false;

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

    /**
     * @var bool 开启查询缓存
     */
    protected $searchCache = true;

    protected function initHelper()
    {
        // 初始化日志
        $this->initLog();

        // 传参日志
        $logParams = [
            'adminId' => $this->auth->id,
            'url' => $this->request->baseUrl(true),
            'method' => $this->request->method(),
            'ip' => $this->request->ip(),
            'getParams' => $this->request->get(),
            'postParams' => $this->request->post(),
        ];
        MonologTool::info("请求信息", $logParams, true, true);

        if ($this->auth->id) {
            $admin = Admin::get($this->auth->id);
            // 隐藏账号不可使用
            if ($admin['status'] === Admin::STATUS_HIDDEN) {
                $this->auth->logout();
                $this->error('账号被禁用', url('index/login'));
            }
            // 客服账号不可登录
            if ($admin['type'] === Admin::TYPE_CUSTOMER) {
                $this->auth->logout();
                $this->error('客服人员不可登录', url('index/login'));
            }
            // 是否需要强制重新登陆
            if ($admin['is_force_login']) {
                $this->auth->logout();
                Hook::listen("admin_logout_after", $this->request);
                $this->error(__('Please login first'), url('index/login'));
            }

            // 权限检测
            AuthService::getInstance()->registerAuthGroup($this->auth->id);
            $this->checkDeveloper();
        }

        // 查询缓存
        $this->searchCache = Env::get('app.search_cache', true);
    }

    /**
     * 是否有指定角色
     * @author Chris Yu <chrisyu@crabapple.top> 2023/12/7
     */
    public function hasRole($roleCode)
    {

    }

    /**
     * 检测：是否需要开发人员权限
     * @author Chris Yu <chrisyu@crabapple.top> 2023/5/23
     */
    protected function checkDeveloper()
    {
        $this->isDeveloper = AuthService::isDeveloperBySession();
        if ($this->isNeedDeveloper && !$this->isDeveloper) {
            $this->error(__('需要开发者权限'), '');
        }
    }

    /**
     * 初始化日志
     * @author Chris Yu <chrisyu@crabapple.top> 2023/3/6
     */
    protected function initLog($logName = '')
    {
        // 创建 RotatingFileHandler，设置日志文件路径，每个日志文件大小为 10MB，最多保留 5 个日志文件
        $fileHandler = new CustomRotatingFileHandler($this->generateLogPath(), 0, Logger::DEBUG, true, 0644, false);
        // $fileHandler->setFilenameFormat('{date}', 'Y-m');
        // $fileHandler->setFormatter(new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context%" . PHP_EOL, "Y-m-d H:i:s", false, true));
        $fileHandler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true));
        // 创建日志实例
        $logger = new Logger($logName ?: basename(static::class));

        $logger->pushHandler($fileHandler);
        $logger->pushProcessor(new AdminProcessor());

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
        // if (!$this->logSubDir) {
        //     $this->logSubDir = request()->controller() . '/' . request()->action();
        // }
        $dir = RUNTIME_PATH . 'log-admin/' . $this->logSubDir;
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
