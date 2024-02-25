<?php

namespace ChrisComposer\Ext\Base;

use app\admin\model\TaskCommandLog;
use app\admin\service\redis\RedisLockService;
use app\ext\service\MqClientService;
use app\tool\ExceptionTool;
use app\tool\LogTool;
use app\tool\MonologTool;
use app\traits\CommandHelperTrait;
use MQ\MQConsumer;
use think\App;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\Db;
use think\Exception;
use think\queue\Job;

/**
 * rocket mq 消费者基类
 * Class BaseJobs
 * @package crmeb\basic
 */
class BaseRocketMqCustomer extends Command
{
    use CommandHelperTrait;

    const ACK_MODE_WHEN_SUCCESS = 'when_success';
    const ACK_MODE_WHENEVER = 'whenever';
    /**
     * @var string config.php 中 mq.pipeline 配置的 key
     */
    protected $configPipelineKey = 'syncOrder';

    protected $description;
    /**
     * @var Input
     */
    protected $input;
    /**
     * @var Output
     */
    protected $output;

    protected $topic = '';
    protected $messageTag = null;
    protected $groupId = '';
    protected $instanceId = '';
    /**
     * @var bool 是否自动记录日志。
     * 有可能会存在一些特殊的业务，如：一条消息会塞多条数据，这种情况下，一行日志会很难排查数据，还需要生产方配合改代码，比较麻烦，麻烦别人比如麻烦自己快
     */
    protected $autoLog = true;
    /**
     * @var string 消费模式。when_success 成功时确认消费，whenever 无论成功失败都确认消费
     */
    protected $ackMode = self::ACK_MODE_WHENEVER;

    /**
     * @var \MQ\MQClient
     */
    protected $client;
    /**
     * @var \MQ\MQConsumer
     */
    protected $customer = null;
    /**
     * @var array mq 配置
     */
    protected $config = [];

    protected function execute(Input $input, Output $output)
    {
        try {
            $this->initLog();

            $this->taskLogInit($input, $output);

            $this->logForce("执行开始——————");

            $this->client = MqClientService::getInstance()->getClient();
            $this->config = MqClientService::getInstance()->getConfig();

            $this->setConnectConfig();

            // 初始化消费者
            $this->consumer = MqClientService::getInstance()->getCustomer($this->topic, $this->groupId, $this->messageTag, $this->instanceId);
            // 消费者开始监听
            $this->runListener();
        } catch (\Throwable $e) {
            $commonMsg = ExceptionTool::getCommonMsg($e);
            $this->logError($commonMsg, true);
            $this->exceptionEmailNotify($e);
            $this->taskLogEnd(TaskCommandLog::END_STATUS_EXCEPTION, $commonMsg);
        }
    }

    /**
     * 设置连接配置
     * @author Chris Yu <chrisyu@crabapple.top> 2023/11/29
     */
    protected function setConnectConfig()
    {
        $pipeline = $this->getPipeConfig($this->configPipelineKey);
        $this->topic = $pipeline['topic'];
        $this->messageTag = $pipeline['messageTag'];
        $this->groupId = $pipeline['groupId'];
        $this->ackMode = $pipeline['ackMode'] ?? self::ACK_MODE_WHENEVER;
    }

    protected function getPipeConfig($key)
    {
        if (empty($key)) {
            throw new Exception("配置 pipeline key 不能为空");
        }
        $pipelineConfig = $this->config['pipeline'][$key];
        $default = $pipelineConfig['default'];
        return $pipelineConfig['config'][$default];
    }

    /**
     * @param $message \MQ\Model\Message
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/12/6
     */
    protected function ack($message)
    {
        $receiptHandles = [];
        $receiptHandles[] = $message->getReceiptHandle();
        $this->ackMessages($receiptHandles);
        $this->logForce("确认消费");
    }

    public function ackMessages($receiptHandles)
    {
        try {
            $this->consumer->ackMessage($receiptHandles);
        } catch (\Exception $e) {
            if ($e instanceof \MQ\Exception\AckMessageException) {
                // 某些消息的句柄可能超时，会导致消费确认失败。
                $this->logError("Ack Error, RequestId: " . $e->getRequestId());
                foreach ($e->getAckMessageErrorItems() as $errorItem) {
                    $this->logError(sprintf("\tReceiptHandle:%s, ErrorCode:%s, ErrorMsg:%s", $errorItem->getReceiptHandle(), $errorItem->getErrorCode(), $errorItem->getErrorCode()));
                }
            }
        }
    }

    public function runListener()
    {
        // 在当前线程循环消费消息，建议多开个几个线程并发消费消息。
        while (True) {
            try {
                // 长轮询消费消息。
                // 若Topic内没有消息，请求会在服务端挂起一段时间（长轮询时间），期间如果有消息可以消费则立即返回客户端。
                $messages = $this->consumer->consumeMessage(
                    3, // 一次最多消费3条（最多可设置为16条）。
                    3 // 长轮询时间3秒（最多可设置为30秒）。
                );

                // 业务处理
                /* @var $message \MQ\Model\Message */
                foreach ($messages as $message) {
                    if ($this->autoLog) {
                        $this->recordLog($message);
                    }
                    // 重置 traceId
                    LogTool::getInstance()->setTraceId();

                    $this->logForce("开始消费——————");

                    try {
                        if (method_exists(static::class, 'handleBefore')) {
                            $this->handleBefore($message);
                        }
                        $res = $this->handle($message);
                        // 确认消费
                        if ($res && $this->ackMode === self::ACK_MODE_WHEN_SUCCESS) {
                            $this->ack($message);
                        }
                        if (method_exists(static::class, 'handleEnd')) {
                            $this->handleEnd();
                        }
                    } catch (\Throwable $e) {
                        ExceptionTool::getInstance()->triggerExceptionCallback($e);
                        RedisLockService::getInstance()->triggerExceptionCallback($e);

                        $commonMsg = ExceptionTool::getCommonMsg($e);
                        $this->logError("业务处理异常：" . $commonMsg);
                        $this->exceptionEmailNotify($e);
                    }
                    // 确认消费
                    if ($this->ackMode === self::ACK_MODE_WHENEVER) {
                        $this->ack($message);
                    }
                }
            } catch (\MQ\Exception\MessageResolveException $e) {
                // 当出现消息Body存在不合法字符，无法解析的时候，会抛出此异常。
                // 可以正常解析的消息列表。
                $messages = $e->getPartialResult()->getMessages();
                // 无法正常解析的消息列表。
                $failMessages = $e->getPartialResult()->getFailResolveMessages();

                $receiptHandles = array();
                foreach ($messages as $message) {
                    // 处理业务逻辑。
                    $receiptHandles[] = $message->getReceiptHandle();
                    $this->logError("MsgID: " . $message->getMessageId());
                }
                foreach ($failMessages as $failMessage) {
                    // 处理存在不合法字符，无法解析的消息。
                    $receiptHandles[] = $failMessage->getReceiptHandle();
                    $this->logError("Fail To Resolve Message. MsgID: " . $failMessage->getMessageId());
                }
                $this->ackMessages($receiptHandles);
                continue;
            }
            catch (\MQ\Exception\MessageNotExistException $e) {
                // 没有消息可以消费，继续轮询。
                $this->log("No message, contine long polling! RequestId: " . $e->getRequestId());
                continue;
            }
            catch (\Exception $e) {
                $commonMsg = ExceptionTool::getCommonMsg($e);
                $this->logError("存在其他异常：exception = " . get_class($e) . "，" . $commonMsg);

                sleep(3);
                continue;
            }
        }
    }

    /**
     * @param $message \MQ\Model\Message
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/12/5
     */
    protected function recordLog($message)
    {
        $logMessage = [
            'publishTime' => $message->getPublishTime(),
            'nextConsumeTime' => $message->getNextConsumeTime(),
            'consumedTimes' => $message->getConsumedTimes(),
            'properties' => $message->getProperties(),
            'messageId' => $message->getMessageId(),
            // 'messageBodyMD5' => $message->getMessageBodyMD5(),
            'messageBody' => $message->getMessageBody(),
            'messageKey' => $message->getMessageKey(),
            'messageTag' => $message->getMessageTag(),
        ];
        MonologTool::info("参数接收", ['params' => $logMessage], true, true);
    }
}
