<?php

namespace Chronos\Models\DataSources\Dbo;

use Chronos\Models\DataSources\DataSource;

class DboSqlite extends DataSource
{
    public $conn;
    private $description = 'Driver DboSqlite';
    private $extension = 'sqlite3';
    private $config = [];

    private $connResource;
    private $results = [];

    public function setConfig($newConfig = [])
    {
        // make validations on $newConfig
        $this->config = $newConfig;
    }

    public function getDescription()
    {
        return sprintf('[%s] %s', $this->extension, $this->description);
    }

    public function enable()
    {
        return extension_loaded($this->extension);
    }

    public function connect()
    {
        try {
            if (!file_exists($this->config['database'])) {
                //new \SQLite3($this->config['database']);
                trigger_error('File is missing ['.$this->config['database'].']'.PHP_EOL, E_USER_ERROR);
            }
            $this->connResource = new \SQLite3($this->config['database'], SQLITE3_OPEN_READWRITE);
            $this->setConnected(true);
        } catch (Exception $e) {
            $this->setConnected(false);
        }
    }

    public function disconnect()
    {
        if (null !== $this->connResource) {
            $this->connResource->close();
        }
    }

    public function query($querySql)
    {
        $this->results = $this->connResource->query($querySql);
    }

    public function fetch()
    {
        $return = [];
        // change this
        if (false !== $this->results) {
            $cols = $this->results->numColumns();
            while ($row = $this->results->fetchArray(SQLITE3_ASSOC)) {
                $return[] = $row;
            }
        } else {
            // just for the moment
            pr($this->connResource->lastErrorMsg());
            die("Error in query: <span style='color:red;'>{$querySql}</span>");
        }

        return $return;
    }

    public function getLastInsertedId()
    {
        $this->query('SELECT last_insert_rowid() AS last_insert_rowid;');
        $returnResult = $this->fetch();

        if (!empty($returnResult)) {
            return (int) $returnResult[0]['last_insert_rowid'];
        }

        return 0;
    }
}
