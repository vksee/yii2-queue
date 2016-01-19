<?php

namespace yii\queue;

/**
 *
 */
class WorkerThread extends Thread
{
    private $_job;

    public function __construct(ActiveJob $job)
    {
        $this->_job = $job;
    }

    public function run()
    {
        $job->run();
    }
}
