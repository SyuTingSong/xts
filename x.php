<?php
/**
 * xts
 * File: apple.php
 * User: TingSong-Syu <rek@rek.me>
 * Date: 2013-11-29
 * Time: 15:20
 */
namespace xts;
use Exception;
use InvalidArgumentException;
use ReflectionClass;

if(!defined('X_PROJECT_ROOT'))
    trigger_error('X_PROJECT_ROOT MUST be defined', E_USER_ERROR);
if(!defined('X_DEBUG'))
    define('X_DEBUG', true);
if(!defined('X_LIB_ROOT'))
    define('X_LIB_ROOT', __DIR__);
if(!defined('X_RUNTIME_ROOT'))
    define('X_RUNTIME_ROOT', X_PROJECT_ROOT.'/protected/runtime');
if(!defined('X_LOG_LEVEL'))
    define('X_LOG_LEVEL', X_DEBUG?0:1);

require_once(X_LIB_ROOT.'/base.php');
require_once(X_LIB_ROOT.'/apple.php');
require_once(X_LIB_ROOT.'/cache.php');
require_once(X_LIB_ROOT.'/orange.php');

class ComponentNotFoundException extends Exception {}

/**
 * Class XComponentFactory
 * @package xts
 * @method static \xts\MCache cache()
 * @method static \xts\Query db()
 * @method static \xts\CCache cc()
 * @method static \xts\Apple apple()
 * @method static \xts\Valley valley($array)
 */
abstract class XComponentFactory extends Component {
    protected static $_conf = array(
        'component' => array(
            'apple' => array(
                'class' => '\\xts\\Apple',
                'singleton' => true,
                'conf' => array(
                    'actionDir' => 'action',
                    'defaultAction' => '/index',
                    'preAction' => '',
                    'preActionFile' => '', // the filename to include before calling preAction
                ),
            ),
            'orange' => array(
                'class' => '\\xts\\Orange',
                'singleton' => false,
                'conf' => array(
                    'tablePrefix' => '',
                    'queryId' => 'db',
                    'modelDir' => '',
                    'enableCacheByDefault' => false,
                    'moldyConf' => array(
                        'cacheId' => 'cache',
                        'duration' => 3600,
                    ),
                    'schemaConf' => array(
                        'schemaCacheId' => 'cc',
                        'useSchemaCache' => true,
                        'schemaCacheDuration' => 0,
                    ),
                ),
            ),
            'db' => array(
                'class' => '\\xts\\Query',
                'singleton' => true,
                'conf' => array(
                    'host' => 'localhost',
                    'port' => 3306,
                    'schema' => 'test',
                    'charset' => 'utf8',
                    'user' => 'root',
                    'password' => '',
                    'persistent' => false,
                ),
            ),
            'cache' => array(
                'class' => '\\xts\\MCache',
                'singleton' => true,
                'conf' => array(
                    'host' => 'localhost',
                    'port' => 11211,
                    'persistent' => true,
                    'keyPrefix' => '',
                ),
            ),
            'cc' => array(
                'class' => '\\xts\\CCache',
                'singleton' => true,
                'conf' => array(
                    'cacheDir' => 'runtime/cache',
                ),
            ),
        ),
    );
    private static $_singletons;

    /**
     * @param $componentId
     * @return bool|Component
     */
    public static  function getComponent($componentId) {
        return self::__callStatic($componentId, array());
    }

    /**
     * @param string $name
     * @param array $args
     * @return \xts\IComponent
     * @throws \xts\ComponentNotFoundException
     */
    public static function __callStatic($name, $args) {
        if(isset(self::$_conf['component'][$name])) {
            $desc = self::$_conf['component'][$name];
            if(!empty($desc['require']))
                require_once($desc['require']);
            if($desc['singleton'] === false)
                return self::getInstance($desc['class'], $args, $desc['conf']);
            if(empty(self::$_singletons[$name]))
                self::$_singletons[$name] = self::getInstance($desc['class'], $args, $desc['conf']);
            return self::$_singletons[$name];
        }
        throw new ComponentNotFoundException("The specified component $name cannot be found in configurations");
    }

    public static function conf($conf=array()) {
        parent::conf($conf);
        Orange::conf(static::$_conf['component']['orange']['conf']);
    }

    /**
     * @param string $type
     * @return \xts\Orange
     */
    public static function orange($type) {
        return Orange::pick($type);
    }

    /**
     * @param string $class
     * @param array $constructorParams
     * @param array $conf
     * @throws InvalidArgumentException
     * @return \xts\IComponent
     */
    public static function getInstance($class, $constructorParams=array(), $conf=array()) {
        $rfClass = new ReflectionClass($class);
        if($rfClass->isSubclassOf('xts\IComponent')) {
            // I tried to use $rfClass->getMethod("conf")->invoke(null, $conf) but it's not compatible with PHP 5.3.6
            $class::conf($conf);
            return $rfClass->newInstanceArgs($constructorParams);
        }
        throw new InvalidArgumentException("class $class is not an instance of xts\\IComponent");
    }
}
