<?php

namespace ChrisComposer\Ext\Log\Monolog\processor;

use app\admin\library\Auth;
use ChrisComposer\Tool\MonologTool;
use Monolog\Processor\ProcessorInterface;

class CommonProcessor implements ProcessorInterface
{
    protected $module;

    public function __construct($module = '')
    {
        $this->setModule($module);
    }

    public function __invoke(array $record)
    {
        $record['app'] = 'PD';
        $record['channel'] = 'common';
        $record['module'] = $this->getModule();
        $record['traceId'] = MonologTool::getInstance()->getTraceId() . '.' . 'pd';

        return $record;
    }

    /**
     * @return mixed
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * @param mixed $module
     */
    public function setModule($module): void
    {
        $this->module = $module;
    }
}

