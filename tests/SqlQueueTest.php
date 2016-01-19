<?php

namespace yii\queue\tests;

class SqlQueueTest extends TestCase
{
    public function setUp()
    {
        $this->mockApplication([
            'components' => [
                'queue' => [
                    'class' => 'yii\queue\SqlQueue',
                ],
                'db' => [
                    'class' => 'yii\db\Connection',
                    'dsn' => 'sqlite::memory:',
                    'charset' => 'utf8',
                ],
            ],
        ]);
    }

    protected function pushJobToQueue($data = 'test', $delay = 0)
    {
        $job = new TestJob([
            'data' => $data,
        ]);

        return $job->push($delay);
    }

    public function testJobCreation()
    {
        $job = new TestJob([
            'data' => 'test',
        ]);
        $this->assertEquals('test', $job->data);
    }

    public function testJobInsertion()
    {
        $result = $this->pushJobToQueue();
        $resolvedJob = \Yii::$app->queue->pop();
        $this->assertEquals($result, $resolvedJob['id']);
    }

    public function testJobRun()
    {
        $this->pushJobToQueue(__FUNCTION__);
        $job = \Yii::$app->queue->pop();
        $jobObject = call_user_func($job['body']['serializer'][1], $job['body']['object']);
        $this->assertEquals(__FUNCTION__, $jobObject->data);
        $this->assertEquals(__FUNCTION__, $jobObject->run());
    }

    public function testJobDelete()
    {
        $this->pushJobToQueue(__FUNCTION__);
        $job = \Yii::$app->queue->pop();
        \Yii::$app->queue->delete($job);

        $this->assertFalse(\Yii::$app->queue->pop());
    }

    public function testDifferentQueueWontPopJob()
    {
        $this->pushJobToQueue();
        $this->assertFalse(\Yii::$app->queue->pop('hurrdurrImasheep'));
    }

    public function testJobPurge()
    {
        for ($i = 0; $i <= 10; ++$i) {
            $this->pushJobToQueue(time());
        }
        \Yii::$app->queue->purge('test');

        $this->assertFalse(\Yii::$app->queue->pop());
    }

    public function testJobRelease()
    {
        $this->pushJobToQueue(__FUNCTION__);
        $job = \Yii::$app->queue->pop();

        $this->assertEquals(time(), $job['run_at']);

        \Yii::$app->queue->release($job, 200);
        $this->assertFalse(\Yii::$app->queue->pop());
    }

    public function testJobInFutureDoesNotPopNow()
    {
        $this->pushJobToQueue(__FUNCTION__, 200);
        $this->assertFalse(\Yii::$app->queue->pop());
    }
}
