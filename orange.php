<?php
/**
 * xts
 * File: apple.php
 * User: TingSong-Syu <rek@rek.me>
 * Date: 2013-12-01
 * Time: 11:34
 */
namespace xts;
use PDO;
use PDOException;
use Exception;
use Traversable;

class OrangeException extends Exception {}

class MissingPrimaryKeyException extends Exception {}

interface Dependency {

    /**
     * @param mixed $cachedObject
     * @return boolean
     */
    public function isAvailable($cachedObject);
}

/**
 * Class Query
 * @package xts
 * @property-read PDO $pdo
 * @property-read int $affectedRows
 * @property-read int $lastInsertId
 */
class Query extends Component {
    protected static $_conf = array(
        'host' => 'localhost',
        'port' => 3306,
        'user' => 'root',
        'password' => '',
        'schema' => 'test',
        'charset' => 'utf8',
        'persistent' => false,
    );

    /**
     * @var PDO $_pdo
     */
    protected $_pdo;

    public function getPdo() {
        if(empty($this->_pdo)) {
            $conf = $this->conf;
            $this->_pdo = new PDO (
                "mysql:host={$conf['host']};port={$conf['port']};dbname={$conf['schema']};charset={$conf['charset']};",
                $conf['user'],
                $conf['password'],
                array (
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$conf['charset']}",
                    PDO::ATTR_PERSISTENT => $conf['persistent']
                )
            );
        }
        return $this->_pdo;
    }

    /**
     * @return null|string
     */
    public function getSchema() {
        return $this->conf['schema'];
    }

    private function serializeArray($array) {
        $r = '';
        $first = true;
        foreach($array as $key => $value) {
            if($first)
                $first = false;
            else
                $r .= ',';
            $r .= "$key=$value";
        }
        return $r;
    }
    /**
     * run sql and return the result
     * @param string $sql
     * @param array $params
     * @throws OrangeException
     * @return array
     */
    public function query($sql, $params=array()) {
        Toolkit::trace("$sql with params ".$this->serializeArray($params));
        try {
            $st = $this->pdo->prepare($sql);
            if($st === false) {
                $e = $this->pdo->errorInfo();
                throw new OrangeException($e[2], $e[1]);
            }
            if($st->execute($params)) {
                $this->_affectedRows = $st->rowCount();
                $this->_lastInsertId = $this->pdo->lastInsertId();
                return $st->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $e = $st->errorInfo();
                throw new OrangeException($e[2], $e[1]);
            }
        } catch (PDOException $ex) {
            Toolkit::log($ex->getMessage()."\n".$ex->getTraceAsString(), X_LOG_DEBUG, 'xts\Query::query');
            throw new OrangeException($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * run sql and return the affect row num
     * @param string $sql
     * @return int
     */
    public function execute($sql) {
        return $this->pdo->exec($sql);
    }

    /**
     * @var int
     */
    protected $_lastInsertId = 0;

    /**
     * @return int
     */
    public function getLastInsertId() {
        return $this->_lastInsertId;
    }

    /**
     * @var int
     */
    protected $_affectedRows = 0;

    /**
     * @return int
     */
    public function getAffectedRows() {
        return $this->_affectedRows;
    }

    /**
     * @return boolean
     */
    public function startTransaction() {
        return $this->pdo->beginTransaction();
    }

    /**
     * @return boolean
     */
    public function commit() {
        return $this->pdo->commit();
    }

    /**
     * @return boolean
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }
}

/**
 * Class SqlBuilder
 * @package xts
 */
class SqlBuilder {
    /**
     * @var string
     */
    protected $sql='';

    /**
     * @var array
     */
    protected $params=array();

    /**
     * @var Query
     */
    public $query=null;

    /**
     * @param Query $query
     */
    public function __construct(Query $query=null) {
        $this->query = $query;
    }

    /**
     * @param string $column
     * @param string $_ [optional]
     * @return SqlBuilder
     */
    public function select($column, $_=null) {
        $argc = func_num_args();
        $argv = func_get_args();
        $this->params = array();
        if($argc == 1) {
            $this->sql = "SELECT {$column} ";
        } else if($argc > 1) {
            $columns = implode('`,`', $argv);
            $this->sql = "SELECT `{$columns}` ";
        }
        return $this;
    }

    /**
     * @param string $table
     * @return SqlBuilder
     */
    public function update($table) {
        $this->sql = "UPDATE `$table` ";
        $this->params = array();
        return $this;
    }

    /**
     * @return SqlBuilder
     */
    public function delete() {
        $this->sql = "DELETE ";
        $this->params = array();
        return $this;
    }

    /**
     * @param string $table
     * @return SqlBuilder $this
     */
    public function insertInto($table) {
        $this->sql = "INSERT INTO `$table` ";
        $this->params = array();
        return $this;
    }

    /**
     * @param $table
     * @return SqlBuilder $this
     */
    public function insertIgnoreInto($table) {
        $this->sql = "INSERT IGNORE INTO `$table` ";
        $this->params = array();
        return $this;
    }

    /**
     * @param string|array $columns
     * @return SqlBuilder $this
     */
    public function columns($columns=array()) {
        if(is_array($columns)) {
            $this->sql .= '(`'. implode('`, `', $columns) . '`) ';
        } else if(is_string($columns)) {
            $this->sql .= "($columns) ";
        }
        return $this;
    }

    /**
     * @param array $bindings
     * @return SqlBuilder $this
     */
    public function values($bindings=array()) {
        $references = array();
        foreach($bindings as $k => $v) {
            $references[] = ":$k";
        }

        $this->sql .= 'VALUES ('.implode(', ', $references).') ';
        $this->params = array_merge($this->params, array_combine($references, array_values($bindings)));
        return $this;
    }
    /**
     * @param string $table
     * @return SqlBuilder
     */
    public function from($table) {
        $this->sql .= " FROM `$table` ";
        return $this;
    }

    /**
     * @param string $condition
     * @param array $params
     * @return SqlBuilder
     */
    public function where($condition, $params=array()) {
        $this->sql .= " WHERE $condition ";
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * @param array|string $exp
     * @param array $params
     * @return $this
     */
    public function onDupKeyUpdate($exp, $params=array()) {
        if(is_string($exp)) {
            $this->sql .= " ON DUPLICATE KEY UPDATE $exp ";
        } else if(is_array($exp)) {
            $columns = array();
            $params = array();
            foreach($exp as $k => $v) {
                $columns[] = "`$k`=:$k";
                $params[":$k"] = $v;
            }
            $this->sql .= "ON DUPLICATE KEY UPDATE ".implode(', ', $columns);
        }
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * @internal param int $offset
     * @internal param int $length
     * @return SqlBuilder
     */
    public function limit() {
        $argc = func_num_args();
        $argv = func_get_args();
        if($argc == 1) {
            $offset = 0;
            $length = $argv[0];
        } else if($argc == 2){
            $offset = $argv[0];
            $length = $argv[1];
        } else {
            return $this;
        }
        $this->sql .= "LIMIT $offset, $length";
        return $this;
    }

    /**
     * @param string|array $exp
     * @param array $params
     * @return SqlBuilder
     */
    public function set($exp, $params=array()) {
        if(is_string($exp)) {
            $this->sql .= " SET $exp ";
        } else if(is_array($exp)) {
            $columns = array();
            $params = array();
            foreach($exp as $k => $v) {
                $columns[] = "`$k`=:$k";
                $params[":$k"] = $v;
            }
            $this->sql .= "SET ".implode(', ', $columns);
        }
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * @param array $params
     * @return array
     * @throw OrangeException
     */
    public function query($params=array()) {
        $this->params = array_merge($this->params, $params);
        return $this->query->query($this->sql, $this->params);
    }

    /**
     * @return array
     */
    public function export() {
        return array(
            'sql' => $this->sql,
            'params' => $this->params,
        );
    }
}

class Relation implements Compilable {
    public $name;
    public $property;
    public $foreignModelName;
    public $foreignTableName;
    public $foreignTableField;

    public function __construct($array=null) {
        if(is_array($array)) {
            foreach($array as $key => $value) {
                if(property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }
    }

    /**
     * @param array $properties
     * @return \xts\Compilable
     */
    public static function __set_state($properties) {
        return new static($properties);
    }
}
class BelongToRelation extends Relation {}

class HasManyRelation extends Relation {}

class HasOneRelation extends Relation {}

class Column implements Compilable {
    public $name='';
    public $type='int';
    public $defaultValue='';
    public $canBeNull = false;
    public $isPK = false;
    public $isUQ = false;

    public function __construct($array=null) {
        if(is_array($array)) {
            foreach($array as $key => $value) {
                if(property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }
    }

    /**
     * @param array $properties
     * @return \xts\Compilable
     */
    public static function __set_state($properties) {
        return new static($properties);
    }
}

/**
 * Class Table
 * @package xts
 * @property-read array $columns
 * @property-read array $columnNames
 * @property-read array $keys
 * @property-read array $relations
 * @property-read \xts\Cache $cache
 */
class Table extends Component {
    protected static $_conf = array(
        'schemaCacheId' => 'cc',
        'useSchemaCache' => false,
        'schemaCacheDuration' => 0,
    );

    /**
     * @var boolean
     */
    public $useCache=false;

    /**
     * @var array
     */
    protected static $tables = array();
    /**
     * @var Query
     */
    protected $query;
    /**
     * @var string
     */
    protected $name='';

    /**
     * @return array
     */
    public function getColumns() {
        return static::$tables[$this->name]['columns'];
    }

    /**
     * @return array
     */
    public function getColumnNames() {
        return array_keys(static::$tables[$this->name]['columns']);
    }

    /**
     * @return array
     */
    public function getKeys() {
        return static::$tables[$this->name]['keys'];
    }

    /**
     * @return array
     */
    public function getRelations() {
        return static::$tables[$this->name]['relations'];
    }

    public function getCache() {
        return XComponentFactory::getComponent($this->conf['schemaCacheId']);
    }

    public function refresh() {
        $query = $this->query;
        $cols = $query->query(
            "SELECT * FROM `information_schema`.`COLUMNS` WHERE `TABLE_SCHEMA`=:s AND `TABLE_NAME`=:n",
            array(
                ':s' => $query->getSchema(),
                ':n' => $this->name,
            )
        );
        $columns = array();
        $keys = array();
        $relations = array();
        foreach($cols as $col) {
            $column = new Column();
            $column->name = $col['COLUMN_NAME'];
            $column->type = $col['DATA_TYPE'];
            $column->canBeNull = $col['IS_NULLABLE'] == 'YES';
            if(is_null($col['COLUMN_DEFAULT']))
                $column->defaultValue = $column->canBeNull?null:$this->nullToDefault($column->type);
            else
                $column->defaultValue = $col['COLUMN_DEFAULT'];
            if($col['COLUMN_KEY'] == 'PRI') {
                $column->isPK = true;
                $keys['PK'] = $col['COLUMN_NAME'];
            }
            if($col['COLUMN_KEY'] == 'UNI') {
                $column->isUQ = true;
                $keys['UQ'][] = $col['COLUMN_NAME'];
            }
            if($col['COLUMN_KEY'] == 'MUL') {
                $keys['MU'][] = $col['COLUMN_NAME'];
            }
            $columns[$column->name] = $column;
        }
        $rels = $query->query(
            'SELECT * FROM `information_schema`.`KEY_COLUMN_USAGE` WHERE `TABLE_SCHEMA`=:s AND `TABLE_NAME`=:n AND `REFERENCED_TABLE_NAME` IS NOT NULL',
            array(
                ':s' => $query->getSchema(),
                ':n' => $this->name,
            )
        );
        foreach($rels as $rel) {
            $relation = new BelongToRelation();
            $relation->property = $rel['COLUMN_NAME'];
            $relation->foreignTableName = $rel['REFERENCED_TABLE_NAME'];
            $relation->foreignModelName = $rel['REFERENCED_TABLE_NAME'];
            $relation->foreignTableField = $rel['REFERENCED_COLUMN_NAME'];
            if(strrpos($relation->property, '_id')) {
                $relation->name = substr($relation->property, 0, strlen($relation->property) - 3);
            } else {
                $relation->name = $relation->foreignTableName;
            }
            $relations[$relation->name] = $relation;
        }
        $rels = $query->query(
            'SELECT c.TABLE_NAME, c.COLUMN_NAME, c.COLUMN_KEY FROM information_schema.KEY_COLUMN_USAGE AS kcu INNER JOIN information_schema.COLUMNS AS c USING (TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME) WHERE TABLE_SCHEMA=:s AND REFERENCED_TABLE_NAME=:n',
            array(
                ':s' => $query->getSchema(),
                ':n' => $this->name,
            )
        );
        foreach($rels as $rel) {
            if($rel['COLUMN_KEY'] == 'MUL') {
                $relation = new HasManyRelation();
            } else if($rel['COLUMN_KEY'] == 'PRI' || $rel['COLUMN_KEY'] == 'UNI'){
                $relation = new HasOneRelation();
            } else {
                continue;
            }
            $relation->foreignTableName = $rel['TABLE_NAME'];
            $relation->foreignModelName = $rel['TABLE_NAME'];
            $relation->foreignTableField = $rel['COLUMN_NAME'];
            $relation->name = ($relation instanceof HasManyRelation) ?
                Toolkit::pluralize($rel['TABLE_NAME']):$rel['TABLE_NAME'];
            $relations[$relation->name] = $relation;
        }
        static::$tables[$this->name] = array(
            'columns' => $columns,
            'keys' => $keys,
            'relations' => $relations,
        );
        if($this->conf['useSchemaCache']) {
            $key = $this->query->getSchema().'/'.$this->name;
            $this->cache->set($key, static::$tables[$this->name], $this->conf['schemaCacheDuration']);
        }
    }

    public function init() {
        if(!isset(static::$tables[$this->name])) {
            if($this->conf['useSchemaCache']) {
                $key = $this->query->getSchema().'/'.$this->name;
                $tableSchema = $this->cache->get($key);
                if($tableSchema !== false) {
                    static::$tables[$this->name] = $tableSchema;
                    return;
                }
            }
            $this->refresh();
        }
    }

    /**
     * @param string $name
     * @param Query $query
     */
    public function __construct($name, Query $query) {
        $this->name = $name;
        $this->query = $query;
        $this->init();
    }

    private function nullToDefault($type) {
        switch($type) {
            case 'varchar':
            case 'char':
            case 'text':
                return '';
            case 'date':
                return '1970-01-01';
            case 'datetime':
                return '1970-01-01 00:00:00';
            case 'time':
                return '00:00:00';
            case 'int':
            case 'bigint':
            case 'smallint':
            case 'tinyint':
            case 'float':
            case 'decimal':
                return 0;
            default:
                return '';
        }
    }
}

final class OrangeIterator implements \Iterator {

    private $currentKey = '';
    private $keys = array();
    private $orange = null;

    public function __construct($iteratorKeys, &$orange) {
        $this->keys = $iteratorKeys;
        $this->orange =& $orange;
        $this->currentKey = reset($this->keys);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current() {
        return $this->orange[$this->currentKey];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next() {
        $this->currentKey=next($this->keys);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key() {
        return $this->currentKey;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid() {
        return $this->currentKey !== false;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind() {
        $this->currentKey = reset($this->keys);
    }
}

/**
 * Class Orange
 * @package xts
 * @property array $properties
 * @property-read Table $schema
 * @property-read Query $query
 * @property-read string $modelName
 * @property-read bool $isNewRecord
 * @property-read mixed $oldPK
 * @property-read string $tableName
 * @property-read array $modified
 * @property-read array $relations
 * @property-read SqlBuilder $builder
 */
class Orange extends Component implements \ArrayAccess, IAssignable, \IteratorAggregate, \JsonSerializable {
    const INSERT_NORMAL=0;
    const INSERT_IGNORE=1;
    const INSERT_UPDATE=2;
    const INSERT_REPLACE=3;

    /**
     * @var bool
     */
    protected $_isNewRecord=true;

    /**
     * @var mixed
     */
    protected $_oldPK=null;

    /**
     * @var array $_properties
     */
    protected $_properties=array();

    /**
     * @var array
     */
    protected $_modified=array();

    /**
     * @var array $_relationObjects
     */
    protected $_relationObjects=array();

    /**
     * @var Table $_schema
     */
    protected $_schema;

    /**
     * @var string $_modelName
     */
    protected $_modelName;

    /**
     * @staticvar Query
     */
    protected static $_query=null;

    /**
     * @staticvar array
     */
    public static $_conf = array (
        'tablePrefix' => '',
        'queryId' => 'db',
        'modelDir' => '',
        'enableCacheByDefault' => false,
        'schemaConf' => array(
            'schemaCacheId' => 'cc',
            'useSchemaCache' => false,
            'schemaCacheDuration' => 0,
        ),
        'moldyConf' => array(
            'cacheId' => 'cache',
            'duration' => 60,
        ),
    );

    /**
     * @var SqlBuilder
     */
    private $_builder;

    public function __sleep() {
        return array('_modelName', '_properties', '_modified', '_relations');
    }

    public function __wakeUp() {
        $this->_builder = new SqlBuilder($this->query);
    }

    /**
     * @param string $modelName
     * @param string $namespace
     * @return Orange
     */
    public static function pick($modelName, $namespace='\\') {
        $className = Toolkit::toCamelCase($modelName, true);
        if($namespace != '\\')
            $className = $namespace . '\\' . $className;

        $classFile = str_replace('\\', DIRECTORY_SEPARATOR, $className);
        if($classFile[0] != DIRECTORY_SEPARATOR)
            $classFile = DIRECTORY_SEPARATOR.$classFile;
        $classFile = static::$_conf['modelDir']."$classFile.php";
        if(is_file($classFile)) {
            include_once($classFile);
        }

        if(class_exists($className)) {
            return new $className($modelName);
        } else {
            return new Orange($modelName);
        }
    }

    public static function conf($conf=array()) {
        parent::conf($conf);
        MoldyOrange::conf(static::$_conf['moldyConf']);
        Table::conf(static::$_conf['schemaConf']);
    }

    /**
     * @return FreshOrange
     */
    public function noCache() {
        return FreshOrange::getFresh($this);
    }

    /**
     * @param string $condition
     * @param array $params
     * @param boolean $clone
     * @return null|Orange
     */
    public function one($condition, $params=array(), $clone=false) {
        if($this->conf['enableCacheByDefault'])
            return $this->cache()->one($condition, $params, $clone);
        else
            return $this->noCache()->one($condition, $params, $clone);
    }

    /**
     * @param string $condition
     * @param array $params
     * @param null|int $offset
     * @param null|int $limit
     * @return array
     */
    public function many($condition=null, $params=array(), $offset=null, $limit=null) {
        if($this->conf['enableCacheByDefault'])
            return $this->cache()->many($condition, $params, $offset, $limit);
        else
            return $this->noCache()->many($condition, $params, $offset, $limit);
    }

    /**
     * @param string $condition
     * @param array $params
     * @return array
     */
    public function all($condition=null, $params=array()) {
        return $this->many($condition, $params);
    }

    /**
     * @param string $condition
     * @param array $params
     * @return mixed
     */
    public function count($condition=null, $params=array()) {
        if($this->conf['enableCacheByDefault'])
            return $this->cache()->count($condition, $params);
        else
            return $this->noCache()->count($condition, $params);
    }

    /**
     * @param int $scenario [optional]
     * @throws MethodNotImplementException
     * @return $this
     */
    public function save($scenario=Orange::INSERT_NORMAL) {
        if($this->conf['enableCacheByDefault'])
            return $this->cache()->save($scenario);
        else
            return $this->noCache()->save($scenario);
    }

    /**
     * @param int|string $id
     * @return null|Orange
     */
    public function load($id) {
        if($this->conf['enableCacheByDefault'])
            return $this->cache()->load($id);
        else
            return $this->noCache()->load($id);
    }

    /**
     * @param int $id
     */
    public function remove($id=0) {
        if($this->conf['enableCacheByDefault'])
            $this->cache()->remove($id);
        else
            $this->noCache()->remove($id);
    }

    public function setup($properties) {
        $pk = $this->schema->keys['PK'];
        foreach($this->getSchema()->getColumns() as $column) {
            /** @var Column $column */
            if(isset($properties[$column->name])) {
                if(in_array($column->type, array('bigint', 'int', 'mediumint', 'smallint', 'tinyint')))
                    $this->_properties[$column->name] = intval($properties[$column->name]);
                else if(in_array($column->type, array('decimal', 'float', 'real', 'double')))
                    $this->_properties[$column->name] = doubleval($properties[$column->name]);
                else
                    $this->_properties[$column->name] = $properties[$column->name];
            } else if(array_key_exists($column->name, $properties)) {
                $this->_properties[$column->name] = null;
            }
        }
        $this->_modified = array();
        $this->_oldPK = isset($this->_properties[$pk])?$this->_properties[$pk]:null;
        $this->_isNewRecord = !isset($this->_oldPK);
        return $this;
    }

    /**
     * @param null|int $duration
     * @param callback $callback
     * @param null|string $key
     * @return MoldyOrange
     */
    public function cache($duration=null, $callback=null, $key=null) {
        return MoldyOrange::getMoldy($this, $duration, $callback, $key);
    }

    /**
     * @return SqlBuilder
     */
    public function getBuilder() {
        return $this->_builder;
    }

    /**
     * @param string $type
     */
    public function __construct($type) {
        $this->_builder = new SqlBuilder($this->query);
        $this->_modelName = $type;
        $this->initDefaultProperties();
    }
    private function initDefaultProperties() {
        $columns = $this->getSchema()->getColumns();
        foreach($columns as $column) {
            $this->_properties[$column->name] = $column->defaultValue;
        }
        $this->_modified = array();
    }

    /**
     * @return string
     */
    public function getModelName() {
        return $this->_modelName;
    }

    /**
     * @return string
     */
    public function getTableName() {
        return $this->conf['tablePrefix'].$this->_modelName;
    }

    /**
     * @return array
     */
    public function getProperties() {
        return $this->_properties;
    }

    public function setProperties($array) {
        foreach ($array as $key => $value) {
            $this->_propertySet($key, $value);
        }
    }

    /**
     * @return array
     */
    public function getModified() {
        return $this->_modified;
    }

    /**
     * @return bool
     */
    public function getIsNewRecord() {
        return $this->_isNewRecord;
    }

    /**
     * @return mixed
     */
    public function getOldPK() {
        return $this->_oldPK;
    }

    public function getRelations() {
        return $this->schema->relations;
    }

    /**
     * @throws MissingPrimaryKeyException
     * @return Table
     */
    public function getSchema() {
        if(is_null($this->_schema)) {
            /** @var Table $schema */
            $this->_schema = new Table($this->tableName, $this->getQuery());
            $keys = $this->_schema->getKeys();
            if(empty($keys['PK']))
                throw new MissingPrimaryKeyException("Table {$this->_modelName} does not have the Primary Key. It cannot be initialized as an Orange");
        }
        return $this->_schema;
    }

    /**
     * @return Query
     */
    protected function getQuery() {
        return XComponentFactory::getComponent($this->conf['queryId']);
    }

    public function __get($name) {
        if($name == 'conf')
            return static::$_conf;
        $getter = Toolkit::toCamelCase("get $name");
        $snakeName = Toolkit::to_snake_case($name);
        if(method_exists($this, $getter)) {
            return $this->$getter();
        } else if(array_key_exists($name, $this->_properties)) {
            return $this->_properties[$name];
        } else if(array_key_exists($snakeName, $this->_properties)) {
            return $this->_properties[$snakeName];
        } else if(isset($this->relations[$name])
            && !$this->relations[$name] instanceof HasManyRelation) { // Use method to load HasManyRelation
            if(!array_key_exists($name, $this->_relationObjects))
                $this->_relationObjects[$name] = $this->loadRelationObj($name);
            return $this->_relationObjects[$name];
        }
        return null;
    }

    public function __set($name, $value) {
        $setter = Toolkit::toCamelCase("set $name");
        if(method_exists($this, $setter)) {
            $this->$setter($value);
        } else {
            $this->_propertySet($name, $value);
        }
    }

    public function __isset($name) {
        return array_key_exists($name, $this->_properties)
        || array_key_exists($name, $this->relations)
        || ($getter = Toolkit::toCamelCase("get $name")) && method_exists($this, $getter);
    }

    public function __call($name, $args) {
        if(array_key_exists($name, $this->relations)) {
            array_unshift($args, $name);
            return call_user_func_array(array($this, 'loadRelationObj'), $args);
        }
        throw new \BadMethodCallException("Call to undefined method $name");
    }

    /**
     * @param $name
     * @param string $condition
     * @param array $params
     * @param int $offset
     * @param int $limit
     * @return \xts\Orange|array|null
     */
    protected function loadRelationObj($name, $condition=null, $params=array(), $offset=0, $limit=200) {
        /** @var Relation $relation */
        $relation = $this->relations[$name];
        $orange = Orange::pick($relation->foreignModelName);
        switch(true) {
            case $relation instanceof BelongToRelation:
                $pk = $this->_properties[$relation->property];
                Toolkit::trace("Load belonging object {$relation->name} with PK value is {$pk}");
                return $orange->load($pk);
            case $relation instanceof HasOneRelation:
                $c = $relation->foreignTableField.'=:_fid';
                if($condition)
                    $c .= " AND $condition";
                $p = array(
                        ':_fid' => $this->_properties[$this->schema->keys['PK']]
                    ) + $params;
                Toolkit::trace("Load relation object {$relation->name}");
                return $orange->one($c, $p);
            case $relation instanceof HasManyRelation:
                $c = $relation->foreignTableField.'=:_fid';
                if($condition)
                    $c .= " AND $condition";
                $p = array(
                        ':_fid' => $this->_properties[$this->schema->keys['PK']]
                    ) + $params;
                Toolkit::trace("Load relation objects {$relation->name}");
                return $orange->many($c, $p, $offset, $limit);
        }
        return null;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset) {
        return $this->__isset($offset);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset) {
        return $this->__get($offset);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     */
    public function offsetSet($offset, $value) {
        $this->__set($offset, $value);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @throws MethodNotImplementException
     * @return void
     */
    public function offsetUnset($offset) {
        throw new MethodNotImplementException("Orange properties are maintained by database schema. You cannot unset any of them");
    }

    /**
     * @param array|\ArrayAccess $array
     * @return $this
     */
    public function assign($array) {
        if(array_key_exists($this->schema->keys['PK'], $array)) {
            $this->load($array[$this->schema->keys['PK']]);
        }

        foreach($this->_properties as $key => $value) {
            if($key == $this->schema->keys['PK'])
                continue;
            if(array_key_exists($key, $array))
                $this->_propertySet($key, $array[$key]);
        }

        return $this;
    }

    /**
     * @param string $name The name of the property to be set
     * @param mixed $value The value of the property
     * @return int|bool Returns the number of changed properties, or false if $name is invalid
     * @throws OrangeException
     */
    protected function _propertySet($name, $value) {
        if(array_key_exists($name, $this->_properties)) {
            if($this->_properties[$name] !== $value) {
                $this->_properties[$name] = $value;
                $this->_modified[$name] = $value;
                return 1;
            } else {
                return 0;
            }
        }
        return false;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Retrieve an external iterator
     * @link http://php.net/manual/en/iteratoraggregate.getiterator.php
     * @return Traversable An instance of an object implementing <b>Iterator</b> or
     * <b>Traversable</b>
     */
    public function getIterator() {
        return new OrangeIterator($this->getExportProperties(), $this);
    }

    protected function getExportProperties() {
        return array_keys($this->_properties);
    }

    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize() {
        $jsonArray = array();
        foreach ($this as $key => $value) {
            $jsonArray[$key] = $value;
        }
        return $jsonArray;
    }
}

final class FreshOrange {
    private static $singleton;

    /**
     * @param Orange $orange
     * @return \xts\FreshOrange
     */
    public static function getFresh(Orange $orange) {
        if(!self::$singleton instanceof FreshOrange) {
            self::$singleton = new FreshOrange();
        }
        self::$singleton->initFresh($orange);
        return self::$singleton;
    }

    /**
     * @var Orange
     */
    private $orange = null;

    private function __construct() {}

    /**
     * @param \xts\Orange $orange
     * @return $this
     */
    public function initFresh(Orange $orange) {
        $this->orange = $orange;
        return $this;
    }

    public function load($id) {
        $keys = $this->orange->schema->keys;
        $r = $this->orange->builder->select('*')
            ->from($this->orange->tableName)
            ->where("`{$keys['PK']}`=:id")
            ->query(array(':id' => $id))
        ;
        if(!empty($r)) {
            $this->orange->setup(reset($r));
            return $this->orange;
        }
        return null;
    }

    public function one($condition=null, $params=array(), $clone=false) {
        $r = $this->orange->builder->select('*')
            ->from($this->orange->tableName)
            ->where($condition, $params)
            ->limit(1)
            ->query();
        if(empty($r))
            return null;
        $one = $clone ? clone $this->orange : $this->orange;
        $one->setup($r[0]);
        return $one;
    }

    public function many($condition=null, $params=array(), $offset=null, $limit=null) {
        $this->orange->builder
            ->select('*')
            ->from($this->orange->tableName);

        if(!empty($condition))
            $this->orange->builder->where($condition, $params);

        if(!is_null($offset)) {
            if(is_null($limit))
                $this->orange->builder->limit($offset);
            else
                $this->orange->builder->limit($offset, $limit);
        }
        $result = $this->orange->builder->query();
        if(empty($result))
            return array();
        $items = array();
        $pk = $this->orange->schema->keys['PK'];
        foreach($result as $row) {
            $item = clone $this->orange;
            $item->setup($row);
            $items[$item->$pk] = $item;
        }
        return $items;
    }

    public function all($condition=null, $params=array()) {
        return $this->many($condition, $params);
    }

    public function count($condition=null, $params=array()) {
        $this->orange->builder
            ->select('COUNT(*)')
            ->from($this->orange->tableName);
        if(!empty($condition))
            $this->orange->builder->where($condition, $params);
        $r = $this->orange->builder->query();
        return intval(reset($r[0]));
    }

    public function save($scenario=Orange::INSERT_NORMAL) {
        $pk = $this->orange->schema->keys['PK'];
        if($this->orange->isNewRecord) {
            $id = 0;
            switch($scenario) {
                case Orange::INSERT_NORMAL:
                    $this->orange->builder
                        ->insertInto($this->orange->tableName)
                        ->columns($this->orange->schema->columnNames)
                        ->values($this->orange->properties)
                        ->query()
                    ;
                    $id = $this->orange->builder->query->lastInsertId;
                    if(empty($id) && !empty($this->orange->properties[$pk]))
                        $id = $this->orange->properties[$pk];
                    break;
                case Orange::INSERT_IGNORE:
                    $this->orange->builder
                        ->insertIgnoreInto($this->orange->tableName)
                        ->columns($this->orange->schema->columnNames)
                        ->values($this->orange->properties)
                        ->query()
                    ;
                    $id = $this->orange->builder->query->lastInsertId;
                    break;
                case Orange::INSERT_UPDATE:
                    $this->orange->builder
                        ->insertInto($this->orange->tableName)
                        ->columns($this->orange->schema->columnNames)
                        ->values($this->orange->properties)
                        ->onDupKeyUpdate($this->orange->modified)
                        ->query()
                    ;
                    $id = $this->orange->builder->query->lastInsertId;
                    break;
                case Orange::INSERT_REPLACE:
                    throw new MethodNotImplementException('Using REPLACE is dangerous! It may brokes the foreign key constraint. So Orange NOT implement replace support');
            }
            if($id) {
                $this->load($id);
            }
        } else if(!empty($this->orange->modified)){
            $this->orange->builder
                ->update($this->orange->tableName)
                ->set($this->orange->modified)
                ->where("`$pk`=:_table_pk", array(':_table_pk'=> $this->orange->oldPK))
                ->query();
            $this->orange->modified = array();
        }
        return $this->orange;
    }

    public function remove($id=0) {
        $pk = $this->orange->schema->keys['PK'];
        if(!$id)
            $id = $this->orange->properties[$pk];
        if(!$id)
            return;
        $this->orange->builder
            ->delete()
            ->from($this->orange->tableName)
            ->where("`$pk`=:id", array(':id' => $id,))
            ->query();
    }
}

final class MoldyOrange extends Component {
    protected static $_conf = array(
        'cacheId' => 'cache',
        'duration' => 300,
    );
    /**
     * @var int
     */
    private $_duration;

    /**
     * @var string
     */
    private $_key;

    /**
     * @var Orange
     */
    private $_orange;

    /**
     * @var callable
     */
    private $_callback;

    /**
     * @var MoldyOrange
     */
    private static $_singleton;

    /**
     * @param Orange $orange
     * @param null|int $duration
     * @param null|callable $callback
     * @param null|string $key
     * @return \xts\MoldyOrange
     */
    public static function getMoldy(Orange $orange, $duration, $callback, $key) {
        if(!self::$_singleton instanceof MoldyOrange) {
            self::$_singleton = new MoldyOrange();
        }
        self::$_singleton->initMoldy($orange, $duration, $callback, $key);
        return self::$_singleton;
    }
    public function __construct() {}

    /**
     * @param Orange $orange
     * @param null|int $duration
     * @param null|callable $callback
     * @param null|string $key
     */
    public function initMoldy(Orange $orange, $duration, $callback, $key) {
        $this->_orange = $orange;
        $this->_key = $key;
        $this->_duration = is_null($duration)?$this->conf['duration']:$duration;
        $this->_callback = $callback;
    }

    /**
     * @param $condition
     * @param array $params
     * @param bool $clone
     * @return null|\xts\Orange
     */
    public function one($condition, $params=array(), $clone=false) {
        $key = is_null($this->_key) ?
            md5("one|{$this->_orange->tableName}|{$condition}|".serialize($params)): $this->_key;

        $id = $this->cache()->get($key);
        if($id > 0) {
            if($clone) {
                $orange = clone $this->_orange;
                $orange->cache($this->_duration)->load($id);
                return $orange;
            }
            if(is_null($callback = $this->_callback)) {
                return $this->load($id);
            } else if($callback($id) !== false) {
                return $this->load($id);
            }
        }

        $orange = $this->_orange->noCache()->one($condition, $params, $clone);
        $ok = $orange[$orange->schema->keys['PK']];
        $this->cache()->set($key, $ok, $this->_duration);

        return $orange;
    }

    /**
     * @param null|string $condition
     * @param array $params
     * @param null $offset
     * @param null $limit
     * @return array
     */
    public function many($condition=null, $params=array(), $offset=null, $limit=null) {
        $key = is_null($this->_key) ?
            md5("many|{$this->_orange->tableName}|{$condition}|".serialize($params))."|{$offset}|{$limit}": $this->_key;

        $r = $this->cache()->get($key);
        $callback = $this->_callback;
        if(is_array($r)) {
            $oranges = array();
            foreach($r as $id) {
                $orange = clone $this->_orange;
                $orange->cache($this->_duration)->load($id);
                if($orange)
                    $oranges[$id] = $orange;
            }
            if(is_null($callback) || $callback($oranges) !== false)
                return $oranges;
        }
        $oranges = $this->_orange->noCache()->many($condition, $params, $offset, $limit);
        $ok = array_keys($oranges);
        $this->cache()->set($key, $ok, $this->_duration);
        return $oranges;
    }

    /**
     * @param null|string $condition
     * @param array $params
     * @return array
     */
    public function all($condition=null, $params=array()) {
        return $this->many($condition, $params);
    }

    /**
     * @param null|string $condition
     * @param array $params
     * @return mixed
     */
    public function count($condition=null, $params=array()) {
        $key = is_null($this->_key) ?
            md5("count|{$this->_orange->tableName}|{$condition}|".serialize($params)): $this->_key;

        $r = $this->cache()->get($key);
        $callback = $this->_callback;
        if(!is_int($r) || (is_callable($callback) && $callback($r) === false)) {
            $r = $this->_orange->noCache()->count($condition, $params);
            $this->cache()->set($key, $r, $this->_duration);
        }
        return $r;
    }

    public function load($pk) {
        $key = is_null($this->_key) ?
            md5("load|{$this->_orange->tableName}|{$pk}"): $this->_key;
        $r = $this->cache()->get($key);
        if($r instanceof Orange) {
            if(is_null($callback = $this->_callback)) {
                $this->_orange->setup($r->properties);
                return $this->_orange;
            } else if($callback($r) !== false) {
                $this->_orange->setup($r->properties);
                return $this->_orange;
            }
        }

        if($r = $this->_orange->noCache()->load($pk)) {
            $this->cache()->set($key, $this->_orange, $this->_duration);
            return $this->_orange;
        }
        return null;
    }

    public function save($scenario=Orange::INSERT_NORMAL) {
        $oldPk = $this->_orange->oldPK;
        $pkName = $this->_orange->schema->keys['PK'];
        $this->_orange->noCache()->save($scenario);
        $pk = $this->_orange->properties[$pkName];
        $key = is_null($this->_key) ?
            md5("load|{$this->_orange->tableName}|{$pk}"): $this->_key;
        $this->cache()->set($key, $this->_orange, $this->_duration);
        if(is_null($this->_key) && $oldPk != $pk) {
            $oldKey = md5("load|{$this->_orange->tableName}|{$oldPk}");
            $this->cache()->remove($oldKey);
        }
        return $this->_orange;
    }

    public function remove($id) {
        $pkName = $this->_orange->schema->keys['PK'];
        $pk = $this->_orange->properties[$pkName];
        $key = is_null($this->_key) ?
            md5("load|{$this->_orange->tableName}|{$pk}"): $this->_key;
        $this->cache()->remove($key);
        $this->_orange->noCache()->remove($id);
    }

    /**
     * @return Cache
     */
    public function cache() {
        return XComponentFactory::getComponent($this->conf['cacheId']);
    }
}