<?php

namespace ChrisComposer\Ext\Log\Monolog\processor;

use app\admin\library\Auth;
use ChrisComposer\Tool\MonologTool;
use Monolog\Processor\ProcessorInterface;

class AdminProcessor implements ProcessorInterface
{
    public function __construct()
    {
    }

    public function __invoke(array $record)
    {
        $record['app'] = 'PD';
        $record['channel'] = 'admin';
        $record['module'] = 'admin';
        $record['adminId'] = Auth::instance()->id;
        $record['traceId'] = MonologTool::getInstance()->getTraceId() . '.' . 'pd';

        return $record;
    }
}

