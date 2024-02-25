<?php

namespace ChrisComposer\Ext\Service;

use MQ\MQClient;

class MqClientService
{
    protected $config = [];
    /**
     * @var MQClient
     */
    protected $client;

    protected static $ins;

    public function __construct()
    {
        $this->init();
    }

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

    protected function init()
    {
        $this->config = config('mq');
        if (empty($this->config)) {
            throw new \Exception('缺少消息队列配置');
        }
        $this->client = new MQClient(
            // 设置HTTP协议客户端接入点，进入消息队列RocketMQ版控制台实例详情页面的接入点区域查看。
            $this->config['end_point'],
            // 请确保环境变量ALIBABA_CLOUD_ACCESS_KEY_ID、ALIBABA_CLOUD_ACCESS_KEY_SECRET已设置。
            // AccessKey ID，阿里云身份验证标识。
            $this->config['access_id'],
            // AccessKey Secret，阿里云身份验证密钥。
            $this->config['access_key']
        );
    }

    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return \MQ\MQClient
     * @author Chris Yu <chrisyu@crabapple.top> 2023/10/19
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * @return \MQ\MQConsumer
     * @author Chris Yu <chrisyu@crabapple.top> 2023/10/19
     */
    public function getCustomer($topic = '', $groupId = '', $messageTag = null, $instanceId = '')
    {
        // 消息所属的Topic，在消息队列RocketMQ版控制台创建。
        $topic = $topic ?: "common_topic";
        // 您在消息队列RocketMQ版控制台创建的Group ID。
        $groupId = $groupId ?: "GID_test_php_mq";
        // Topic所属的实例ID，在消息队列RocketMQ版控制台创建。
        // 若实例有命名空间，则实例ID必须传入；若实例无命名空间，则实例ID传入null空值或字符串空值。实例的命名空间可以在消息队列RocketMQ版控制台的实例详情页面查看。
        $instanceId = $instanceId ?: $this->config["instance_id"];

        return $this->client->getConsumer($instanceId, $topic, $groupId, $messageTag);
    }

    /**
     * @return \MQ\MQProducer
     * @author Chris Yu <chrisyu@crabapple.top> 2023/10/19
     */
    public function getProducer($topic = '', $instanceId = '')
    {
        // 消息所属的Topic，在消息队列RocketMQ版控制台创建。
        $topic = $topic ?: "common_topic";
        // Topic所属的实例ID，在消息队列RocketMQ版控制台创建。
        // 若实例有命名空间，则实例ID必须传入；若实例无命名空间，则实例ID传入null空值或字符串空值。实例的命名空间可以在消息队列RocketMQ版控制台的实例详情页面查看。
        $instanceId = $instanceId ?: $this->config["instance_id"];;

        return $this->client->getProducer($instanceId, $topic);
    }

}