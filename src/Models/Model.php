<?php

namespace Chronos\Models;

use Chronos\Base\App;
use Chronos\Pagination\Engine\DefaultPaginationEngine;
use Chronos\Pagination\Pagination;

class Model extends App
{
    public $namespace = 'chronos';
    protected $name = '';

    protected $useTable = '';
    protected $key = '';
    private $useDbConfig = 'default';

    private $connectionManager;
    private $lastQuery = '';

    public function __construct()
    {
    }

    public function find($type, $options = [])
    {
        $optionsDefault = [
            'conditions' => [],
            'fields' => [],
            'order' => [],
            'limit' => -1,
        ];
        $options = array_merge($optionsDefault, $options);

        if (empty($options['conditions'])) {
            $options['conditions'][] = '1=1';
        }

        if (empty($options['fields'])) {
            $options['fields'][] = $this->name.'.*';
        }

        if ('first' === $type) {
            $options['limit'] = 1;
        }

        $querySQL = sprintf(
            'SELECT %s FROM %s AS %s WHERE %s ',
            implode(', ', $options['fields']),
            $this->useTable,
            $this->name,
            implode(' AND ', $options['conditions'])
        );

        if (!empty($options['order'])) {
            $querySQL .= sprintf('ORDER BY %s ', implode(', ', $options['order']));
        }

        if ((int) ($options['limit']) >= 0) {
            $querySQL .= sprintf('LIMIT %s ', $options['limit']);
        }

        $result = $this->executeQuery($querySQL);
        $result = $this->fetch();

        if ('first' === $type) {
            $result = isset($result[0]) ? $result[0] : [];
        }

        return $result;
    }

    /**
     * Paginate method works similarly to find, but it will
     * returns a pagination object.
     *
     * @param mixed $criteria
     *
     * @return Chronos\Pagination\Pagination
     */
    public function paginate($criteria = [], \Closure $config = null)
    {
        $pagination = new Pagination(new DefaultPaginationEngine());
        $conditions = [];

        // Define some default pagination configuration
        $currentPage = !empty($_GET['page']) ? (int) $_GET['page'] : 1;
        $pagination->setCurrentPage($currentPage);

        // Apply custom configurations to pagination object
        if (isset($config) && $config instanceof \Closure) {
            $config($pagination);
        }

        // Get conditions to obtain the total records
        if (!empty($criteria['conditions'])) {
            $conditions = $criteria['conditions'];
        }

        // Get the total number of records found
        $pages = $this->find('first', [
            'fields' => ['COUNT(1) AS nr_total'],
            'conditions' => $conditions,
        ]);

        // Stores the total number of records found
        if (!empty($pages['nr_total'])) {
            $pagination->setTotalRecords((int) $pages['nr_total']);
        }

        // Define the limit and offset to execute the pagination
        $criteria['limit'] = "{$pagination->getLimit()} OFFSET {$pagination->getOffset()}";

        // Get the records that will be displayed
        $records = $this->find('all', $criteria);
        $records = !empty($records) ? $records : [];

        // Store the records found inside pagination
        $pagination->setRecords($records);

        // Returns the pagination object
        return $pagination;
    }

    public function save($data, $where = [])
    {
        // pr($data);
        $querySQL = '';

        $flINSERT = (empty($data[$this->pk]) && empty($where));

        if ($flINSERT) {
            if (empty($data[$this->pk])) {
                unset($data[$this->pk]);
            }
        }

        foreach ($data as $k => $valor) {
            if (is_numeric($valor)) {
                $data[$k] = $valor;
            } elseif (null === $valor || 0 === strlen(trim($valor))) {
                $data[$k] = 'NULL';
            } elseif (is_bool($valor)) {
                $data[$k] = ($valor) ? 1 : 0;
            } else {
                $data[$k] = "'{$valor}'";
            }
        }

        $campos = array_keys($data);
        $valores = array_values($data);

        if ($flINSERT) {
            $querySQL = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $this->useTable,
                implode(', ', $campos),
                implode(', ', $valores)
            );
        } else {
            $dsWhere = implode(' AND ', $where);
            if (empty($where)) {
                $dsWhere = $this->pk.' = '.$data[$this->pk];
            }

            $camposSet = [];
            foreach ($campos as $k => $campo) {
                $camposSet[] = $campo.' = '.$valores[$k];
            }

            $querySQL = sprintf(
                'UPDATE %s SET %s WHERE '.$dsWhere,
                $this->useTable,
                implode(', ', $camposSet)
            );
        }

        $result = $this->executeQuery($querySQL);
    }

    public function del($id, $where = [])
    {
        $dsWhere = implode(' AND ', $where);
        if (empty($where)) {
            $dsWhere = $this->pk.' = '.$id;
        }

        $querySQL = sprintf(
            'DELETE FROM %s WHERE '.$dsWhere,
            $this->useTable
        );

        $result = $this->executeQuery($querySQL);
    }

    public function getLastQuery()
    {
        return $this->lastQuery;
    }

    public function getLastInsertedId()
    {
        return $this->getConnectionResource()->getLastInsertedId();
    }

    public function setUseDbConfig($configString)
    {
        $this->useDbConfig = $configString;
    }

    private function fetch()
    {
        return $this->getConnectionResource()->fetch();
    }

    private function getConnectionResource()
    {
        if (null === $this->connectionManager) {
            $this->connectionManager = ConnectionManager::getInstance();
            $this->connectionManager->setConfig($this->useDbConfig, $this->namespace);
        }
        $connectionManagerDataSource = $this->connectionManager->getConnection($this->useDbConfig);

        return $connectionManagerDataSource;
    }

    private function executeQuery($querySQL)
    {
        $this->lastQuery = $querySQL;
        // pr('####### querySQL');
        // pr($querySQL);
        return $this->getConnectionResource()->query($querySQL);
        //pr($result);
        // pr([$this->name => $result]);
    }
}
