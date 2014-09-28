<?php
/**
 * xts
 * File: smarty.php
 * User: TingSong-Syu <rek@rek.me>
 * Date: 2014-01-13
 * Time: 15:53
 */
namespace xts;
require_once('view.php');
require_once('smarty/Smarty.class.php');
use \Smarty;

/**
 * Class MySmarty
 * @package xts
 * @property-read array $conf
 */
class MySmarty extends Smarty implements View, IComponent {
    protected static $_conf = array(
        'cacheDir' => '',
        'templateDir' => '',
        'compileDir' => '',
        'cacheDuration' => 3600,
    );

    /**
     * @param array $conf
     */
    public static function conf($conf=array()) {
        Toolkit::override(static::$_conf, $conf);
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        if($name == 'conf') {
            return static::$_conf;
        } else {
            return parent::__get($name);
        }
    }

    private static $counter=0;
    public function init() {
        $this->setCacheDir($this->conf['cacheDir']);
        $this->setCompileDir($this->conf['compileDir']);
        $this->setTemplateDir($this->conf['templateDir']);
    }

    public function __construct() {
        parent::__construct();
        $this->init();
    }

    private $_layout = 'layout.tpl';

    /**
     * @param string $title
     * @return $this
     */
    public function setPageTitle($title) {
        $this->assign('_page_title', $title);
        return $this;
    }

    /**
     * @param array $params
     * @return $this
     */
    public function assignAll($params) {
        if(is_array($params))
            foreach($params as $key => $value)
                $this->assign($key, $value);
        return $this;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function assign($name, $value) {
        parent::assign($name, $value, false);
        return $this;
    }

    /**
     * @param string $tpl
     * @param array $params
     * @param string $cache_id [optional]
     * @return $this
     */
    public function render($tpl, $params=array(), $cache_id=null) {
        $this->assignAll($params);
        if(!strpos($tpl, '.tpl'))
            $tpl .= '.tpl';
        $this->assign('_content_template', $tpl);
        if(!is_null($cache_id)) {
            $this->caching = Smarty::CACHING_LIFETIME_CURRENT;
            $this->cache_lifetime = $this->conf['cacheDuration'];
            $cache_id = "$tpl|$cache_id";
        }
        $this->display($this->_layout, $cache_id);
        return $this;
    }

    /**
     * @param string $tpl
     * @param array $params
     * @param string $cache_id [optional]
     * @return string
     */
    public function renderFetch($tpl, $params=array(), $cache_id=null) {
        $this->assignAll($params);
        if(!strpos($tpl, '.tpl'))
            $tpl .= '.tpl';
        $this->assign('_content_template', $tpl);
        if(!is_null($cache_id)) {
            $this->caching = Smarty::CACHING_LIFETIME_CURRENT;
            $this->cache_lifetime = $this->conf['smarty']['cacheDuration'];
            $cache_id = "$tpl|$cache_id";
        }
        return $this->fetch($this->_layout, $cache_id);
    }

    /**
     * @param string $layout
     * @return $this
     */
    public function setLayout($layout) {
        if(!strpos($layout, '.tpl'))
            $layout .= '.tpl';
        $this->_layout = $layout;
        return $this;
    }

    /**
     * @return string
     */
    public function getLayout() {
        return $this->_layout;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset($name) {
        $allowed = array(
            'template_dir' => 'getTemplateDir',
            'config_dir' => 'getConfigDir',
            'plugins_dir' => 'getPluginsDir',
            'compile_dir' => 'getCompileDir',
            'cache_dir' => 'getCacheDir',
        );
        return array_key_exists($name, $allowed);
    }

    /**
     * @return array
     */
    public function getConf() {
        return static::$_conf;
    }
}
