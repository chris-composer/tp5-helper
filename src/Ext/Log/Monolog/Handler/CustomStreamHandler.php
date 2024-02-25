<?php

namespace ChrisComposer\Ext\Log\Monolog\Handler;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class CustomStreamHandler extends StreamHandler
{
    public function write(array $record): void
    {
        $this->handleWriteBefore($record);
        parent::write($record);
        $this->handleWriteAfter($record);
    }

    /**
     * 写前操作
     * 在这里执行写入完毕后的回调操作
     * 可以根据需要进行相应的处理
     * 例如发送通知、记录日志等等
     * $record 包含当前写入的日志记录信息
     * ...
     * @param array $record
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/29
     */
    private function handleWriteBefore(array $record): void
    {

    }

    /**
     * 写完操作
     * @param array $record
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/29
     */
    private function handleWriteAfter(array $record): void
    {

    }
}

