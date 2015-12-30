<?php

namespace yii\queue;

use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\db\Query;
use yii\db\Expression;
use yii\base\Component;
use Yii;

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

    private $_query;

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
            'id' => 'pk COMMENT "1.0.0"',
            'queue' => 'string(255)',
            'run_at' => 'timestamp default CURRENT_TIMESTAMP NOT NULL',
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
            'queue' => $this->getQueue($queue),
            'payload' => $payload,
            'run_at' => new Expression('FROM_UNIXTIME(:unixtime)', [
                ':unixtime' => time() + $delay,
            ])
        ]);
        return $this->connection->lastInsertID;
    }

    private function getQuery($queue)
    {
        if ($this->_query) {
            return $this->_query;
        }

        $this->_query=new Query;
        $this->_query->select('id, payload')
            ->from($this->getTableName())
            ->where(['queue'=>$queue])
            ->andWhere('run_at <= NOW()')
            ->limit(1);

        return $this->_query;
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
        $row=$this->getQuery($this->getQueue($queue))->one($this->connection);
        if ($row) {
            return $row['payload'];
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
            ['run_at' => new Expression('DATE_ADD(NOW(), INTERVAL :delay SECOND)', ['delay' => $delay])],
            'id = :id',
            ['id' => $message['id']]
        )->execute();
    }
}
