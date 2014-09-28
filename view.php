<?php
/**
 * xts-init
 * File: view.php
 * User: TingSong-Syu <rek@rek.me>
 * Date: 2014-03-25
 * Time: 14:01
 */
namespace xts;

/**
 * Interface View
 * @package xts
 * @property string $_layout
 * @property-write string $pageTitle
 */
interface View {
    /**
     * @param string $title
     * @return $this
     */
    public function setPageTitle($title);

    /**
     * @param string $layout
     * @return $this
     */
    public function setLayout($layout);

    /**
     * @return string
     */
    public function getLayout();

    /**
     * @param string $tpl
     * @param array $params [optional]
     * @param string $cacheId [optional]
     * @return $this
     */
    public function render($tpl, $params=array(), $cacheId=null);

    /**
     * @param string $tpl
     * @param array $params [optional]
     * @param string $cacheId [optional]
     * @return string
     */
    public function renderFetch($tpl, $params=array(), $cacheId=null);

    /**
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function assign($name, $value);

    /**
     * @param array $params
     * @return $this
     */
    public function assignAll($params);
}
