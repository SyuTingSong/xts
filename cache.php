<?php
/**
 * xts
 * File: apple.php
 * User: TingSong-Syu <rek@rek.me>
 * Date: 2013-11-30
 * Time: 14:49
 */
namespace xts;
use \Memcache;
use \Redis;

/**
 * Class Cache
 * @package xts
 */
abstract class Cache extends Component {
    /**
     * @param string $key
     * @return mixed
     */
    public abstract function get($key);

    /**
     * @param string $key
     * @param mixed $value
     * @param int $duration
     * @return void
     */
    public abstract function set($key, $value, $duration);

    /**
     * @param string $key
     * @throws MethodNotImplementException
     * @return boolean
     */
    public function remove($key) {
        throw new MethodNotImplementException(__METHOD__.' has not been implemented yet.');
    }

    /**
     * @throws MethodNotImplementException
     * @return void
     */
    public function flush() {
        throw new MethodNotImplementException(__METHOD__.' has not been implemented yet.');
    }

    /**
     * @param string $key
     * @param int $step
     * @throws MethodNotImplementException
     * @return int|boolean
     */
    public function inc($key, $step=1) {
        throw new MethodNotImplementException(__METHOD__.' has not been implemented yet.');
    }

    /**
     * @param string $key
     * @param int $step
     * @throws MethodNotImplementException
     * @return int
     */
    public function dec($key, $step=1) {
        throw new MethodNotImplementException(__METHOD__.' has not been implemented yet.');
    }
}

/**
 * Class MCache
 * @package xts
 * @property-read Memcache $cache
 */
class MCache extends Cache {
    protected $_cache;
    /**
     * @varStatic array
     */
    protected static $_conf = array(
        'host' => 'localhost',
        'port' => 11211,
        'persistent' => false,
        'keyPrefix' => '',
    );

    /**
     * @param string $key
     * @return mixed
     */
    public function get($key) {
        $key = $this->conf['keyPrefix'] . $key;
        $s = $this->cache->get($key);
        if($s === false) {
            Toolkit::trace("MCache miss $key");
            return false;
        } else {
            Toolkit::trace("MCache hit $key");
            return is_numeric($s)?$s:unserialize($s);
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $duration
     * @return void
     */
    public function set($key, $value, $duration) {
        $key = $this->conf['keyPrefix'] . $key;
        Toolkit::trace("MCache set $key");
        $s = is_numeric($value)?$value:serialize($value);
        $this->cache->set($key, $s, 0, $duration);
    }

    /**
     * @param string $key
     * @return boolean
     */
    public function remove($key) {
        $key = $this->conf['keyPrefix'] . $key;
        Toolkit::trace("MCache remove $key");
        return $this->cache->delete($key);
    }

    /**
     * @return void
     */
    public function flush() {
        Toolkit::trace("MCache flush");
        $this->cache->flush();
    }

    /**
     * @param string $key
     * @param int $step
     * @return int
     */
    public function inc($key, $step=1) {
        $key = $this->conf['keyPrefix'] . $key;
        Toolkit::trace("MCache inc $key by $step");
        return $this->cache->increment($key, $step);
    }

    /**
     * @param string $key
     * @param int $step
     * @return int
     */
    public function dec($key, $step=1) {
        $key = $this->conf['keyPrefix'] . $key;
        Toolkit::trace("MCache dec $key by $step");
        return $this->cache->decrement($key, $step);
    }

    /**
     * @return Memcache
     */
    public function getCache() {
        if(!$this->_cache instanceof Memcache) {
            Toolkit::trace("MCache init");
            $this->_cache = new Memcache();
            if(static::$_conf['persistent']) {
                $this->_cache->pconnect(static::$_conf['host'], static::$_conf['port']);
            } else {
                $this->_cache->connect(static::$_conf['host'], static::$_conf['port']);
            }
        }
        return $this->_cache;
    }
}


/**
 * Class CCache
 * @package xts
 */
class CCache extends Cache {
    protected static $_conf = array(
        'cacheDir' => '.',
    );

    public function init() {
        Toolkit::trace('CCache init');
        if(!is_dir(static::$_conf['cacheDir']))
            mkdir(static::$_conf['cacheDir'], 0777, true);
    }

    private function key2file($key) {
        return self::$_conf['cacheDir'].DIRECTORY_SEPARATOR.rawurlencode($key).'.php';
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get($key) {
        $file = $this->key2file($key);
        if(is_file($file)) {
            $r = require($file);
            if(!empty($r) && $key === $r['key'] && ($r['expiration'] == 0 || time() <= $r['expiration'])) {
                Toolkit::trace("CCache hit $key");
                return $r['data'];
            }
        }
        Toolkit::trace("CCache miss $key");
        return false;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $duration
     * @return void
     */
    public function set($key, $value, $duration) {
        Toolkit::trace("CCache set $key");
        $file = $this->key2file($key);
        $content = array(
            'key' => $key,
            'expiration' => $duration>0?time()+$duration:0,
            'data' => $value,
        );
        $phpCode = '<?php return '.Toolkit::compile($content).';';
        if (function_exists('opcache_invalidate'))
            opcache_invalidate($file, true);
        file_put_contents($file, $phpCode, LOCK_EX);
    }

    /**
     * @param string $key
     * @return boolean
     */
    public function remove($key) {
        Toolkit::trace("CCache remove $key");
        $file = $this->key2file($key);
        if(is_file($file)) {
            unlink($file);
            return true;
        }
        return false;
    }
}


/**
 * Class RCache
 * @package xts
 * @property-read Redis $cache
 */
class RCache extends Cache {
    /**
     * @var Redis $_cache
     */
    protected $_cache;
    /**
     * @varStatic array
     */
    protected static $_conf = array(
        'host' => 'localhost',
        'port' => 6379, //int, 6379 by default
        'timeout' => 0, //float, value in seconds, default is 0 meaning unlimited
        'persistent' => false, //bool, false by default
        'database' => 0, //number, 0 by default
        'auth' => null, //string, null by default
        'keyPrefix' => '',
    );

    /**
     * @param string $key
     * @return mixed
     */
    public function get($key) {
        $s = $this->cache->get($key);
        if($s === false) {
            Toolkit::trace("RCache Miss '$key'");
            return false;
        } else {
            Toolkit::trace("RCache Hit '$key'");
            return is_numeric($s)?$s:unserialize($s);
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param int $duration
     * @return void
     */
    public function set($key, $value, $duration=0) {
        Toolkit::trace("RCache Set $key");
        $s = is_numeric($value)?$value:serialize($value);
        $this->cache->set($key, $s, $duration);
    }

    /**
     * @param string $key
     * @return boolean
     */
    public function remove($key) {
        Toolkit::trace("RCache remove '$key'");
        return $this->_cache->delete($key);
    }

    /**
     * @return void
     */
    public function flush() {
        Toolkit::trace("RCache flush");
        $this->_cache->flushDB();
    }

    /**
     * @param string $key
     * @param int $step
     * @return int
     */
    public function inc($key, $step=1) {
        Toolkit::trace("RCache inc '$key' by $step");
        if($step > 1) {
            return $this->cache->incrBy($key, $step);
        } else {
            return $this->cache->incr($key);
        }
    }

    /**
     * @param string $key
     * @param int $step
     * @return int
     */
    public function dec($key, $step=1) {
        Toolkit::trace("RCache dec '$key' by $step");
        if($step > 1) {
            return $this->cache->decrBy($key, $step);
        } else {
            return $this->cache->decr($key);
        }
    }

    /**
     * @return Redis
     */
    public function getCache() {
        if(!$this->_cache instanceof Redis) {
            Toolkit::trace("RCache init");
            $this->_cache = new Redis();
            if(static::$_conf['persistent']) {
                $this->_cache->pconnect(static::$_conf['host'], static::$_conf['port'], static::$_conf['timeout']);
            } else {
                $this->_cache->connect(static::$_conf['host'], static::$_conf['port'], static::$_conf['timeout']);
            }
            if(static::$_conf['auth']) {
                $this->_cache->auth(static::$_conf['auth']);
            }
            if(static::$_conf['database']) {
                $this->_cache->select(static::$_conf['database']);
            }
            if(static::$_conf['keyPrefix']) {
                $this->_cache->setOption(Redis::OPT_PREFIX, static::$_conf['keyPrefix']);
            }
        }
        return $this->_cache;
    }
}