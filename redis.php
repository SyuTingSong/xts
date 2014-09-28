<?php
/**
 * xts
 * File: redis.php
 * User: TingSong-Syu <rek@rek.me>
 * Date: 2014-06-21
 * Time: 18:51
 */

namespace xts;

use Redis as PhpRedis;
use Exception;

/**
 * Class Redis
 * @package xts\ext
 */
class Redis extends PhpRedis implements IComponent {
    protected static $_conf = array(
        'host' => 'localhost',
        'port' => 6379,
        'timeout' => 0,
        'persistent' => false,
        'database' => 0,
        'auth' => null,
        'keyPrefix' => '',
    );

    /**
     * @param array $conf
     * @throws \Exception
     */
    public function __construct($conf=array()) {
        parent::__construct();
        static::conf($conf);
        $conf =& static::$_conf;

        if($conf['persistent']) {
            if(!$this->pconnect($conf['host'], $conf['port'], $conf['timeout']))
                throw new Exception("Cannot connect to Redis at {$conf['host']}:{$conf['port']}");
        } else {
            if(!$this->connect($conf['host'], $conf['port'], $conf['timeout']))
                throw new Exception("Cannot connect to Redis at {$conf['host']}:{$conf['port']}");
        }

        // Authenticate when needed
        if($conf['auth'] && !$this->auth($conf['auth'])) {
            throw new Exception("Redis auth failed at {$conf['host']}:{$conf['port']}");
        }
        if($conf['database'] && !$this->select($conf['database'])) {
            throw new Exception("Select Failed in Redis at {$conf['host']}:{$conf['port']}");
        }
        if($conf['keyPrefix']) {
            $this->setOption(PhpRedis::OPT_PREFIX, $conf['keyPrefix']);
        }
    }

    /**
     * @return array
     */
    public function getConf() {
        return static::$_conf;
    }

    /**
     * @return \Redis
     */
    public function getRedis() {
        return $this;
    }

    /**
     * @param array $conf
     * @return void
     */
    public static function conf($conf = array()) {
        Toolkit::override(static::$_conf, $conf);
    }

    public function __get($name) {
        $getter = Toolkit::toCamelCase("get $name");
        if(method_exists($this, $getter)) {
            return $this->$getter();
        }
        return null;
    }

    /**
     * @param $name
     * @return bool
     */
    public function __isset($name) {
        $getter = Toolkit::toCamelCase("get $name");
        return method_exists($this, $getter);
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set($name, $value) {
        $setter = Toolkit::toCamelCase("set $name");
        if(method_exists($this, $setter)) {
            $this->$setter($value);
        } else {
            $this->$name = $value;
        }
    }
}
