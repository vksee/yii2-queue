<?php

namespace yii\queue\controllers;

use Yii;
use yii\console\Controller;

/**
 * Queue Process Command
 *
 * Class QueueController
 * @package yii\queue\controllers
 */
class QueueController extends Controller
{

    private $_timeout;
    private $_sleep=5;

    /**
     * Process a job
     *
     * @param string $queueName
     * @param string $queueObjectName
     * @throws \Exception
     */
    public function actionWork($queueName = null, $queueObjectName = 'queue')
    {
        $this->process($queueName, $queueObjectName);
    }

    /**
     * Continuously process jobs
     *
     * @param string $queueName
     * @param string $queueObjectName
     *
     * @return bool
     * @throws \Exception
     */
    public function actionListen($queueName = null, $queueObjectName = 'queue')
    {
        while (true) {
            if ($this->_timeout !==null) {
                if ($this->_timeout<time()) {
                    return true;
                }
            }
            if (!$this->process($queueName, $queueObjectName)) {
                sleep($this->_sleep);
            }

        }
    }

    /**
     * Process one unit of job in queue
     *
     * @param $queueName
     * @param $queueObjectName
     *
     * @return bool
     */
    protected function process($queueName, $queueObjectName)
    {
        $queue = Yii::$app->{$queueObjectName};
        $job = $queue->pop($queueName);

        if ($job) {
            try {
                $jobObject = call_user_func($job['body']['serializer'][1], $job['body']['object']);
                $queue->delete($job);
                $jobObject->run();
                return true;
            } catch (\Exception $e) {
                Yii::error($e->getMessage(), __METHOD__);
            }
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (getenv('QUEUE_TIMEOUT')) {
            $this->_timeout=(int)getenv('QUEUE_TIMEOUT')+time();
        }
        if (getenv('QUEUE_SLEEP')) {
            $this->_sleep=(int)getenv('QUEUE_SLEEP');
        }
        return true;
    }
}