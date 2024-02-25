<?php

namespace ChrisComposer\Tool\Email;

use ChrisComposer\Tool\FastAdmin\Email;
use ChrisComposer\Tool\MonologTool;
use think\Config;
use think\Env;
use think\Lang;

class EmailTool
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

    protected $config;

    /**
     * 配置信息
     * @var array[]
     */
    protected $configCommon = [];

    /**
     * 配置信息
     * @var array[]
     */
    protected $configDeveloperMap = [];

    /**
     * @param array|array[] $configCommon
     */
    public function __construct()
    {
        $this->config = Env::get('chris_composer.email');
        if (empty($config)) {
            throw new \Exception('缺少邮件配置');
        }

        $this->configCommon = $this->config['common'];
        $this->configDeveloperMap = $this->config['developer']['map'];
    }

    public function send($subject, $content, $to = '')
    {
        $config = $this->configCommon;

        $email = Email::instance($config);
        $res = $email->subject($this->config['site_name'] . '-' . $subject)
            ->message($content)
            ->to($to ?: $config['mail_to'])
            ->send();
        if (!$res) {
            MonologTool::error("邮件发送失败。主题：$subject, 内容：$content, 邮箱：{$to}。错误: " . $email->getError(), [__METHOD__ . '[' . __LINE__ . ']'], true, true);
            return false;
        }
    }

    /**
     * 发送给开发人员
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/2/27
     */
    public function sendToDeveloper($subject, $content, $to = '', $developer = '')
    {
        if (!$this->config['developer']['enable']) {
            return false;
        }

        if ($developer) {
            $config = $this->configDeveloperMap[$developer];
        }
        else {
            $config = $this->configDeveloperMap[$this->config['developer']['default']];
        }

        $email = Email::instance($config);
        $res = $email->subject($this->config['site_name'] . '-' . $subject)
            ->message($content)
            ->to($to ?: $config['mail_to'])
            ->send();
        if (!$res) {
            MonologTool::error("开发人员邮件发送失败。主题：$subject, 内容：$content, 邮箱：{$to}。error: " . $email->getError(), [__METHOD__ . '[' . __LINE__ . ']'], true, true);
            return false;
        }
    }

    /**
     * 发送邮箱：异常信息
     *
     * @param $exceptionInput \Throwable|array
     * @param $subject string
     * @param $to string
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/2/24
     */
    public function sendException($exceptionInput, $subject = '', $to = '')
    {
        if (!$this->config['developer']['enable']) {
            return false;
        }

        $exceptionContent = ExceptionsEmailContentHandler::getInstance()->getContent($exceptionInput);
        $this->sendToDeveloper($subject ?: '异常提醒', $exceptionContent);
    }
}
