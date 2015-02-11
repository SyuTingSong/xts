<?php
/**
 * xts
 * File: apple.php
 * User: TingSong-Syu <rek@rek.me>
 * Date: 2014-01-10
 * Time: 18:22
 */
namespace xts;
require_once('CJSON.php');
use ReflectionFunction;
use Exception;
use CJSON;

class AppExitException extends Exception {}
class InvalidActionException extends Exception {}
/**
 * Class Apple
 * @package xts
 */
class Apple extends Component {
    protected static $_conf = array(
        'actionDir' => '',
        'defaultAction' => '/index',
        'actionPrefix' => 'action_',
        'preAction' => '',
        'preActionFile' => '', // the filename to include before calling preAction
    );

    /**
     * Start the router and call the action function
     */
    public function run() {
        Toolkit::trace("Apple Start: ".$_SERVER['REQUEST_URI']);
        if(ini_get('display_errors')) {
            $this->_run();
        } else {
            try {
                $this->_run();
            } catch(Exception $e) {
                Toolkit::log(sprintf(
                    "%s is thrown at %s(%d) with message: %s\nCall stack:\n%s",
                    get_class($e),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getMessage(),
                    $e->getTraceAsString()
                ), X_LOG_ERROR, 'xts\Apple::run');
            }
        }
        Toolkit::trace("Apple Exit: ".$_SERVER['REQUEST_URI']);
    }
    private function _run() {
        try {
            if(preg_match('#^(/[a-zA-Z0-9/_\.\-]*)($|\?\S*$)#', $_SERVER['REQUEST_URI'], $m)) {
                $action = $m[1];
                if($action == '/')
                    $action = $this->conf['defaultAction'];
            } else {
                header('HTTP/1.1 400 Bad Request');
                echo 'Bad Request';
                return;
            }
            if(!empty($this->conf['preAction'])) {
                if(!empty($this->conf['preActionFile'])) {
                    require_once($this->conf['preActionFile']);
                }
                if(is_callable($this->conf['preAction'])) {
                    call_user_func($this->conf['preAction'], $action);
                }
            }
            $p = pathinfo($action);
            $action_function = $p['filename'];
            $dirname = $p['dirname'] == '/'?'':$p['dirname'];
            $pArgs = array();
            while(true) {
                $action_file = $this->conf['actionDir'].$dirname.'/'.$action_function.'.php';
                if(is_file($action_file)) {
                    require_once($action_file);
                    break;
                } else {
                    array_unshift($pArgs, $action_function);
                    $action_function = basename($dirname);
                    if(empty($action_function)) {
                        $action_function = $p['filename'];
                        break;
                    }
                    $dirname = dirname($dirname);
                    if($dirname == '/')
                        $dirname = '';
                }
            }
            $prefixed_action_function = $this->conf['actionPrefix'].$action_function;
            if(function_exists($func = $prefixed_action_function) || function_exists($func = $action_function)) {
                $rf = new ReflectionFunction($func);
                if($rf->isInternal()) {
                    throw new InvalidActionException('Cannot invoke PHP built-in function by web app action');
                }
                if(!empty($pArgs)) {
                    if(count($pArgs) < $rf->getNumberOfParameters()) {
                        header('HTTP/1.1 400 Bad Request');
                        echo 'Position based parameter count mismatch the action function';
                        return;
                    }
                    $rf->invokeArgs($pArgs);
                    return;
                }
                $params = $rf->getParameters();
                $args = array();
                foreach ($params as $param) {
                    $name = $param->getName();
                    if(isset($_GET[$name])) {
                        $args[] = $_GET[$name];
                    } else if($param->isOptional()) {
                        $args[] = $param->getDefaultValue();
                    } else {
                        header('HTTP/1.1 400 Bad Request');
                        echo "missing query parameter $name";
                        return;
                    }
                }
                $rf->invokeArgs($args);
            } else if(function_exists('fallback_action')){
                $rf = new ReflectionFunction('fallback_action');
                $rf->invoke($action);
            } else {
                header('HTTP/1.1 404 Not Found');
                echo "404 Not Found";
                return;
            }
        } catch(AppExitException $ex) {
            Toolkit::log($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * @param $msg
     * @param null $data
     * @param null $goto
     * @param int $status
     * @return $this
     */
    public function jsonEcho($msg, $data=null, $goto=null, $status=0) {
        $this->jsonOutput(1, $msg, $data, $goto, $status);
        return $this;
    }

    /**
     * output a json encoded object to report an error
     *
     * @param string $msg [optional] The human readable error message
     * @param mixed $data
     * @param string $goto [optional] The target url to redirect
     * @param int $status [optional] Server response status code, default is 500, and 302 if $goto is not null
     * @return $this
     */
    public function jsonError($msg, $data=null, $goto=null, $status=0) {
        $this->jsonOutput(0, $msg, $data, $goto, $status);
        return $this;
    }

    private function jsonOutput($ok, $msg, $data, $goto, $status) {
        header('Content-Type: application/json');
        if($status)
            header("Status: $status");
        $res = array('OK' => $ok);
        $res['message'] = $msg;
        if(isset($data))
            $res['data'] = $data;
        if(!empty($goto))
            $res['goto'] = $goto;

        echo CJSON::encode($res);
    }

    /**
     * Redirect user to the new url
     *
     * @param string $location
     * @param int $statusCode
     * @return $this
     */
    public function redirect($location, $statusCode=302) {
        header("Location: $location", true, $statusCode);
        return $this;
    }

    /**
     * End application by throwing an AppExitException.
     *
     * Notice: You don't need to catch the AppExitException. It will be catch in xts\Apple::run
     *
     * @param string $msg
     * @param int $level
     * @throws AppExitException
     */
    public function end($msg='App End', $level=X_LOG_NOTICE) {
        throw new AppExitException($msg, $level);
    }
}
