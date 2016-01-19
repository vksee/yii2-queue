<?php

namespace yii\queue\tests;

use yii\queue\ActiveJob;

class TestJob extends ActiveJob
{
    public $data;

    public function queueName()
    {
        return 'test';
    }

    public function run()
    {
        return $this->data;
    }
}