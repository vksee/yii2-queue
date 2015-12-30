<?php

namespace yii\queue;

use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\db\Query;
use yii\base\Component;
use Yii;
use yii\helpers\Json;

class SqlQueue extends Component implements QueueInterface
{
    /**
     * @var string Default database connection component name
     */
    public $connection = 'db';

    /**
     * @var string Default queue table namespace
     */
    public $default = 'default';

    public function init()
    {
        parent::init();
        if (is_string($this->connection)) {
            $this->connection = Yii::$app->get($this->connection);
        } elseif (is_array($this->connection)) {
            if (!isset($this->connection['class'])) {
                $this->connection['class'] = Connection::className();
            }
            $this->connection = Yii::createObject($this->connection);
        }

        if (!$this->connection instanceof Connection) {
            throw new InvalidConfigException("Queue::connection must be application component ID of a SQL connection.");
        }

        if (!$this->hasTable()) {
            $this->createTable();
        }
    }

    private function hasTable()
    {
        $schema=$this->connection->schema->getTableSchema($this->getTableName(), true);
        if ($schema==null) {
            return false;
        }
        if ($schema->columns['id']->comment!=='1.0.0') {
            $this->dropTable();
            return false;
        }
        return true;
    }

    private function createTable()
    {
        $this->connection->createCommand()->createTable($this->getTableName(), [
            'id' => 'pk',
            'queue' => 'string(255)',
            'run_at' => 'INTEGER NOT NULL',
            'payload' => 'text',
        ])->execute();
        $this->connection->schema->refresh();
    }

    public function dropTable()
    {
        $this->connection->createCommand()->dropTable($this->getTableName())->execute();
    }

    private function getTableName()
    {
        return $this->default.'_queue';
    }

    /**
     * @inheritdoc
     */
    public function push($payload, $queue = null, $delay = 0)
    {
        $this->connection->schema->insert($this->getTableName(), [
            'queue' => $queue,
            'payload' => Json::encode($payload),
            'run_at' => time() + $delay,
        ]);
        return $this->connection->lastInsertID;
    }

    private function getQuery($queue)
    {
        $query=new Query;
        $query->from($this->getTableName())
            ->andFilterWhere(['queue'=>$queue])
            ->andWhere('run_at <= :timestamp', ['timestamp' => time()])
            ->limit(1);

        return $query;
    }

    /**
     * @inheritdoc
     */
    public function delete(array $message)
    {
        $this->connection->createCommand()->delete($this->getTableName(), 'id=:id', [':id'=>$message['id']])->execute();
    }

    /**
     * @inheritdoc
     */
    public function pop($queue = null)
    {
        $row=$this->getQuery($queue)->one($this->connection);
        if ($row) {
            $row['body'] = Json::decode($row['payload']);
            return $row;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    public function purge($queue)
    {
        $this->connection->createCommand()->delete($this->getTableName(), 'queue=:queue', [':queue'=>$queue])->execute();
    }

    /**
     * @inheritdoc
     */
    public function release(array $message, $delay = 0)
    {
        $this->connection->createCommand()->update(
            $this->getTableName(),
            ['run_at' => time() + $delay],
            'id = :id',
            ['id' => $message['id']]
        )->execute();
    }
}
