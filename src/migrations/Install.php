<?php

namespace craftsnippets\elementfilters\migrations;

use Craft;
use craft\db\Migration;
use craftsnippets\elementfilters\helpers\DbTables;

class Install extends Migration
{

    public $driver;

    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            Craft::$app->db->schema->refresh();
        }
        return true;
    }

    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();
        return true;
    }

    protected function createTables()
    {
        $tablesCreated = false;

        $tableSchema = Craft::$app->db->schema->getTableSchema(DbTables::FILTERS);
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                DbTables::FILTERS,
                [
                    'id' => $this->primaryKey(),
                    'uid' => $this->uid(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'jsonSettings' => $this->text(),
                    'elementType' => $this->string()->notNull(),
                    'sourceKey' => $this->string()->notNull(),
                    'order' => $this->integer()->notNull(),
                ]
            );
        }    

        return $tablesCreated;
    }

    protected function removeTables()
    {
        $this->dropTableIfExists(DbTables::FILTERS);
    }
}
