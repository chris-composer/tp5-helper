<?php

namespace ChrisComposer\Ext\Log\Monolog\Handler;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Utils;

class CustomRotatingFileHandler extends RotatingFileHandler
{
    const MAX_FILE_SIZE = 1024 * 1024 * 5;

    public function write(array $record): void
    {
        $this->handleWriteBefore($record);

        parent::write($record);

        $this->handleWriteAfter($record);
    }

    /**
     * 写前操作
     * 默认 this->url 每次 write 之前都是生成好的，$this->url 值跟 $fileHandler->setFilenameFormat()有关
     * 所以要把原来的 $this->url 重置成自己需要的
     * @param array $record
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/29
     */
    private function handleWriteBefore(array $record)
    {
        // 时间到第二天
        // 这里处理后，parent::write() 中 $this->nextRotation <= $record['datetime'] 就不会判断进入
        $currentTimestamp = $record['datetime']->getTimestamp();
        $tomorrowTimestamp = $this->nextRotation->getTimestamp();
        if ($tomorrowTimestamp <= $currentTimestamp) {
            $this->mustRotate = true;
            $this->close();

            $baseDir = dirname($this->filename, 3);
            $subDirWithFilename = date('Y/m/d', $currentTimestamp);
            $extension = pathinfo($this->filename, PATHINFO_EXTENSION);
            $this->filename = $baseDir . '/' . $subDirWithFilename . '.' . $extension;
        }
        if (file_exists($this->filename)) {
            if (filesize($this->filename) > self::MAX_FILE_SIZE) {
                // 关闭原文件写入
                $this->mustRotate = true;
                $this->close();
                // 切换新文件
                $pathinfo = pathinfo($this->filename);
                $dir = $pathinfo['dirname'];
                $filename = $pathinfo['filename'];
                $extension = $pathinfo['extension'];
                $separator = '_';
                $filenameArray = explode($separator, $filename);
                $count = 0;
                if (count($filenameArray) > 1) {
                    $count = $filenameArray[1] + 1;
                }
                else {
                    $count += 1;
                }
                $this->filename = $dir . '/' . $filenameArray[0] . $separator . $count . '.' . $extension;
            }
        }

        $this->registerStream($this->filename);

        return true;
    }

    /**
     * 注册流
     * @param $newStream
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/29
     */
    protected function registerStream($newStream)
    {
        if (is_resource($newStream)) {
            $this->stream = $newStream;

            stream_set_chunk_size($this->stream, $this->streamChunkSize);
        } elseif (is_string($newStream)) {
            $this->url = Utils::canonicalizePath($newStream);
        } else {
            throw new \InvalidArgumentException('A stream must either be a resource or a string.');
        }
    }

    /**
     * 写完操作
     * @param array $record
     *
     * @author Chris Yu <chrisyu@crabapple.top> 2023/6/29
     */
    private function handleWriteAfter(array $record): void
    {
        clearstatcache(true, $this->url);
    }
}

