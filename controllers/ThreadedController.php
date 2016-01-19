<?php

namespace yii\queue\controllers;

use yii\queue\WorkerThread;

/**
 *
 */
class ThreadedController extends QueueController
{
    protected function process($queueName, $queueObjectName)
    {
        $queue = Yii::$app->{$queueObjectName};
        $job = $queue->pop($queueName);

        if ($job) {
            try {
                $jobObject = call_user_func($job['body']['serializer'][1], $job['body']['object']);
                $worker = new WorkerThread($jobObject);

                $worker->run();
                $queue->delete($job);

                //$jobObject->run();

                return true;
            } catch (\Exception $e) {
                Yii::error($e->getMessage(), __METHOD__);
            }
        }

        return false;
    }
}
