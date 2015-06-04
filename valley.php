<?php
/**
 * xts
 * File: apple.php
 * User: TingSong-Syu <rek@rek.me>
 * Date: 2014-08-05
 * Time: 01:42
 */

namespace xts;

/**
 * Class Valley
 * @package xts
 *
 * @property bool $isValid
 * @property bool $isEmpty
 * @property array $messages
 * @property mixed $safeVar
 */
class Valley extends Component {
    protected static $_conf = array(
        'encoding' => 'UTF-8',
    );

    protected $validated = false;
    /**
     * @var bool
     */
    protected $isValid = true;

    /**
     * @var bool
     */
    protected $isEmpty = false;

    /**
     * the wrapped var
     *
     * @var mixed
     */
    protected $unsafeVar = null;

    /**
     * contains the validated var
     *
     * @var mixed
     */
    protected $safeVar = null;

    /**
     * @var bool
     */
    protected $onErrorSet = false;

    /**
     * @var mixed
     */
    protected $onErrorSetValue = null;

    /**
     * @var bool
     */
    protected $onEmptySet = false;

    /**
     * @var mixed
     */
    protected $onEmptySetValue = null;

    /**
     * $shell always ref to the most outside Valley
     *
     * @var Valley
     */
    protected $shell = null;

    /**
     * Contains the sub valleys for each key.
     *
     * @var array
     */
    private $subValleys = array();

    /**
     * @var string
     */
    private $errorMessage = '';

    /**
     * @var string
     */
    private $emptyMessage = '';

    /**
     * @var string
     */
    private $message = '';

    /**
     * Contains the message reported by sub valleys
     *
     * @var array
     */
    private $messages = array();

    /**
     * @param array $var
     * @param Valley $shell
     */
    public function __construct($var, $shell=null) {
        $this->unsafeVar = $var;
        if($shell instanceof Valley)
            $this->shell = $shell;
        else
            $this->shell = $this;
    }

    /**
     * @param $key
     * @return \xts\ValleyString
     */
    public function str($key) {
        return $this->shell->subValleys[$key]
                = new ValleyString($this->shell->unsafeVar[$key], $this->shell);
    }

    /**
     * @param $key
     * @return \xts\ValleyDate
     */
    public function date($key) {
        return $this->shell->subValleys[$key]
            = new ValleyDate($this->shell->unsafeVar[$key], $this->shell);
    }

    /**
     * @param string $key
     * @return \xts\ValleyEmail
     */
    public function email($key) {
        return $this->shell->subValleys[$key]
            = new ValleyEmail($this->shell->unsafeVar[$key], $this->shell);
    }

    /**
     * @param string $key
     * @return \xts\ValleyUrl
     */
    public function url($key) {
        return $this->shell->subValleys[$key]
            = new ValleyUrl($this->shell->unsafeVar[$key], $this->shell);
    }

    /**
     * @param $key
     * @return \xts\ValleyTel
     */
    public function tel($key) {
        return $this->shell->subValleys[$key]
            = new ValleyTel($this->shell->unsafeVar[$key], $this->shell);
    }

    /**
     * @param $key
     * @return \xts\ValleyNumber
     */
    public function num($key) {
        return $this->shell->subValleys[$key]
            = new ValleyNumber($this->shell->unsafeVar[$key], $this->shell);
    }

    /**
     * @param $key
     * @return \xts\ValleyFloat
     */
    public function float($key) {
        return $this->shell->subValleys[$key]
            = new ValleyFloat($this->shell->unsafeVar[$key], $this->shell);
    }

    /**
     * @param $key
     * @return \xts\ValleyInteger
     */
    public function int($key) {
        return $this->shell->subValleys[$key]
            = new ValleyInteger($this->shell->unsafeVar[$key], $this->shell);
    }

    /**
     * @param $key
     * @return \xts\ValleyDecimal
     */
    public function dec($key) {
        return $this->shell->subValleys[$key]
            = new ValleyDecimal($this->shell->unsafeVar[$key], $this->shell);
    }

    /**
     * @param $key
     * @return \xts\ValleyHexadecimal
     */
    public function hex($key) {
        return $this->shell->subValleys[$key]
            = new ValleyHexadecimal($this->shell->unsafeVar[$key], $this->shell);
    }

    /**
     * @param string $key
     * @return \xts\ValleyBoolean
     */
    public function bool($key) {
        return $this->shell->subValleys[$key]
            = new ValleyBoolean($this->shell->unsafeVar[$key], $this->shell);
    }

    /**
     * @param string $key
     * @return \xts\ValleyArray
     */
    public function arr($key) {
        return $this->shell->subValleys[$key]
            = new ValleyArray($this->shell->unsafeVar[$key], $this->shell);
    }

    /**
     * Report $message if the wrapped var is invalid.
     *
     * @param string $message
     * @return $this
     */
    public function onErrorReport($message) {
        $this->errorMessage = $message;
        return $this;
    }

    /**
     * Tell Valley to use $value instead of wrapped var if it's invalid.
     *
     * @param mixed $value
     * @return $this
     */
    public function onErrorSet($value) {
        $this->onErrorSet = true;
        $this->onErrorSetValue = $value;
        return $this;
    }

    /**
     * Report $message if the wrapped var is empty.
     *
     * @param string $message
     * @return $this
     */
    public function onEmptyReport($message) {
        $this->emptyMessage = $message;
        return $this;
    }

    /**
     * Use $value when wrapped var is empty.
     * This method won't change the isValid property.
     *
     * @param mixed $value
     * @return $this
     */
    public function onEmptySet($value) {
        $this->onEmptySet = true;
        $this->onEmptySetValue = $value;
        return $this;
    }

    public function getIsValid() {
        return $this->isValid;
    }

    public function getIsEmpty() {
        return $this->isEmpty;
    }

    public function getMessages() {
        return $this->messages;
    }

    public function getSafeVar() {
        return $this->safeVar;
    }

    /**
     * Start validate the valley itself
     *
     * @return void
     */
    protected function selfValidate() {
        if(empty($this->unsafeVar) && !isset($this->safeVar)) {
            if($this->onEmptySet) {
                $this->safeVar = $this->onEmptySetValue;
                $this->isValid = true;
            } else if(!empty($this->emptyMessage)) {
                $this->isValid = false;
                $this->message = $this->emptyMessage;
                $this->isEmpty = true;
            } else {
                $this->isEmpty = true;
            }
        } else if(!$this->isValid) {
            if($this->onErrorSet) {
                $this->safeVar = $this->onErrorSetValue;
                $this->isValid = true;
            } else if(!empty($this->errorMessage)) {
                $this->message = $this->errorMessage;
            }
        } else if(!isset($this->safeVar)) {
            $this->safeVar = $this->unsafeVar;
        }

        $this->validated = true;
    }
    /**
     * Start validate the whole chain
     * include the sub valleys
     *
     * @return Valley
     */
    public function startValidate() {
        $shell = $this->shell;
        if(count($shell->subValleys)) {
            /**
             * @var Valley $valley
             * @var string $key
             */
            foreach($shell->subValleys as $key => $valley) {
                $valley->selfValidate();
                if($valley->isValid) {
                    $shell->safeVar[$key] = $valley->safeVar;
                } else if(!empty($valley->message)) {
                    $shell->messages[$key] = $valley->message;
                    $shell->isValid = false;
                } else {
                    $shell->messages[$key] = "$key is invalid";
                    $shell->isValid = false;
                }
            }
            $shell->validated = true;
        } else {
            $this->selfValidate();
            if(!$this->isValid)
                $this->messages[] = $this->message;
        }
        return $this->shell;
    }

    /**
     * @param IAssignable $target
     * @return \xts\IAssignable $target
     */
    public function assignTo($target) {
        if(!$this->validated)
            trigger_error('You should never call assignTo before startValidate', E_WARNING);
        if($this->shell->isValid && is_array($this->shell->safeVar))
            $target->assign($this->shell->safeVar);
        return $target;
    }
}

class ValleyString extends Valley {

    /**
     * @param array $var
     * @param Valley $shell
     */
    public function __construct($var, $shell=null) {
        parent::__construct($var, $shell);
        if(!is_string($var)) {
            $this->isValid = false;
        }
    }

    /**
     * check the length of the string in range of $min and $max
     * won't check the maximum length if $max is null
     *
     * @param int $min
     * @param null|int $max
     * @return $this
     */
    public function length($min, $max=null) {
        if(!$this->isValid)
            return $this;
        $length = mb_strlen($this->unsafeVar, $this->conf['encoding']);
        if($length < $min || !is_null($max) && $length > $max) {
            $this->isValid = false;
        }
        return $this;
    }

    /**
     * The wrapped var MUST contains $needle.
     *
     * @param string $needle
     * @return $this
     */
    public function contain($needle) {
        if(!$this->isValid)
            return $this;
        if(mb_strpos($this->unsafeVar, $needle, null, $this->conf['encoding']) === false)
            $this->isValid = false;
        return $this;
    }

    /**
     * The wrapped var MUST contains $needle, case-insensitive.
     *
     * @param string $needle
     * @return $this
     */
    public function containCI($needle) {
        if(!$this->isValid)
            return $this;
        if(mb_stripos($this->unsafeVar, $needle, null, $this->conf['encoding']) === false)
            $this->isValid = false;
        return $this;
    }

    /**
     * The wrapped var MUST start with $needle.
     *
     * @param string $needle
     * @return $this
     */
    public function startWith($needle) {
        if(!$this->isValid)
            return $this;
        if(mb_strpos($this->unsafeVar, $needle, null, $this->conf['encoding']) !== 0)
            $this->isValid = false;
        return $this;
    }

    /**
     * The wrapped var MUST start with $needle, case-insensitive.
     *
     * @param string $needle
     * @return $this
     */
    public function startWithCI($needle) {
        if(!$this->isValid)
            return $this;
        if(mb_stripos($this->unsafeVar, $needle, null, $this->conf['encoding']) !== 0)
            $this->isValid = false;
        return $this;
    }

    /**
     * The wrapped var MUST end with $needle.
     *
     * @param string $needle
     * @return $this
     */
    public function endWith($needle) {
        if(!$this->isValid)
            return $this;
        $rPos = mb_strrpos($this->unsafeVar, $needle, null, $this->conf['encoding']);
        if($rPos === false) {
            $this->isValid = false;
        } else {
            $varLength = mb_strlen($this->unsafeVar, $this->conf['encoding']);
            $needleLength = mb_strlen($needle, $this->conf['encoding']);
            if($varLength != $rPos + $needleLength) {
                $this->isValid = false;
            }
        }
        return $this;
    }

    /**
     * The wrapped var MUST end with $needle, case-insensitive.
     *
     * @param string $needle
     * @return $this
     */
    public function endWithCI($needle) {
        if(!$this->isValid)
            return $this;
        $rPos = mb_strripos($this->unsafeVar, $needle, null, $this->conf['encoding']);
        if($rPos === false) {
            $this->isValid = false;
        } else {
            $varLength = mb_strlen($this->unsafeVar, $this->conf['encoding']);
            $needleLength = mb_strlen($needle, $this->conf['encoding']);
            if($varLength != $rPos + $needleLength) {
                $this->isValid = false;
            }
        }
        return $this;
    }

    /**
     * The wrapped var MUST in the specified enum.
     *
     * @param array $enum
     * @return $this
     */
    public function inEnum($enum) {
        if(!$this->isValid)
            return $this;
        if(!in_array($this->unsafeVar, $enum)) {
            $this->isValid = false;
        }
        return $this;
    }

    /**
     * The wrapped var MUST match the specified regular expression
     *
     * @param string $pattern
     * @return $this
     */
    public function match($pattern) {
        if(!$this->isValid)
            return $this;
        if(!preg_match($pattern, $this->unsafeVar))
            $this->isValid = false;
        return $this;
    }

    /**
     * The wrapped var MUST NOT match the specified regular expression
     *
     * @param string $pattern
     * @return $this
     */
    public function notMatch($pattern) {
        if(!$this->isValid)
            return $this;
        if(preg_match($pattern, $this->unsafeVar))
            $this->isValid = false;
        return $this;
    }

    /**
     * Pass the wrapped var to the callback $func and assign $isValid as its return value
     *
     * @param callable $func
     * @return $this
     */
    public function callback($func) {
        if(!$this->isValid)
            return $this;
        $this->isValid = boolval($func($this->unsafeVar));
        return $this;
    }
}

class ValleyEmail extends ValleyString {
    public function __construct($var, $shell=null) {
        parent::__construct($var, $shell);
        if(filter_var($var, FILTER_VALIDATE_EMAIL) === false)
            $this->isValid = false;
    }
}

class ValleyDate extends ValleyString {
    public function __construct($var, $shell=null) {
        parent::__construct($var, $shell);
        $parsed = date_parse($var);
        if($parsed === false)
            $this->isValid = false;
        else if($parsed['warning_count'] || $parsed['error_count'])
            $this->isValid = false;
    }
}

class ValleyUrl extends ValleyString {
    public function __construct($var, $shell=null) {
        parent::__construct($var, $shell);
        if(filter_var($var, FILTER_VALIDATE_URL) === false)
            $this->isValid = false;
    }
}
class ValleyTel extends ValleyString {
    public function __construct($var, $shell=null) {
        parent::__construct($var, $shell);
        $this->match('/^[()+\-\d ]*$/');
    }
}

class ValleyNumber extends Valley {

    /**
     * @param mixed $var
     * @param null $shell
     */
    public function __construct($var, $shell=null) {
        parent::__construct($var, $shell);
        if(!is_numeric($var) && !ctype_xdigit($var))
            $this->isValid = false;
    }

    /**
     * The wrapped var MUST greater than $num
     *
     * @param int|float $num
     * @param bool $orEqual
     * @return $this
     */
    public function gt($num, $orEqual=false) {
        if(!$this->isValid)
            return $this;

        if($this->unsafeVar < $num)
            $this->isValid = false;
        else if($this->unsafeVar == $num && !$orEqual)
            $this->isValid = false;
        return $this;
    }

    /**
     * The wrapped var MUST less than $num
     *
     * @param int|float $num
     * @param bool $orEqual
     * @return $this
     */
    public function lt($num, $orEqual=false) {
        if(!$this->isValid)
            return $this;

        if($this->unsafeVar > $num)
            $this->isValid = false;
        else if($this->unsafeVar == $num && !$orEqual)
            $this->isValid = false;
        return $this;
    }

    /**
     * The wrapped var MUST equal $num
     *
     * @param int|float $num
     * @return $this
     */
    public function eq($num) {
        if(!$this->isValid)
            return $this;

        if($this->unsafeVar != $num)
            $this->isValid = false;
        return $this;
    }

    /**
     * The wrapped var MUST NOT equal $num
     *
     * @param int|float $num
     * @return $this
     */
    public function ne($num) {
        if(!$this->isValid)
            return $this;

        if($this->unsafeVar == $num)
            $this->isValid = false;
        return $this;
    }

    /**
     * The wrapped var MUST between $min and $max
     *
     * @param int|float $min
     * @param int|float $max
     * @param bool $includeMin
     * @param bool $includeMax
     * @return $this
     */
    public function between($min, $max, $includeMin=true, $includeMax=true) {
        if(!$this->isValid)
            return $this;

        if($this->unsafeVar < $min)
            $this->isValid = false;
        else if($this->unsafeVar > $max)
            $this->isValid = false;
        else if($this->unsafeVar == $min && !$includeMin)
            $this->isValid = false;
        else if($this->unsafeVar == $max && !$includeMax)
            $this->isValid = false;
        return $this;
    }

    /**
     * The wrapped var MUST in the specified enum
     *
     * @param array $enum
     * @return $this
     */
    public function inEnum($enum) {
        if(!$this->isValid)
            return $this;
        if(!in_array($this->unsafeVar, $enum))
            $this->isValid = false;
        return $this;
    }
}

class ValleyInteger extends ValleyNumber {
    public function __construct($var, $shell=null) {
        if($shell instanceof Valley)
            $this->shell = $shell;
        else
            $this->shell = $this;

        if(ctype_digit($var)) {
            $this->unsafeVar = intval($var);
        } else if($var[0] == '-' && ctype_digit(substr($var, 1))) {
            $this->unsafeVar = intval($var);
        } else if(ctype_xdigit($var)) {
            $this->unsafeVar = intval($var, 16);
        } else if(is_int($var)) {
            $this->unsafeVar = $var;
        } else if(strpos($var, '0x') === 0 && ctype_xdigit($var = substr($var, 2))){
            $this->unsafeVar = intval($var, 16);
        } else {
            $this->unsafeVar = $var;
            $this->isValid = false;
        }
    }
}

class ValleyDecimal extends ValleyInteger {
    public function __construct($var, $shell=null) {
        if($shell instanceof Valley)
            $this->shell = $shell;
        else
            $this->shell = $this;

        if(ctype_digit($var) || is_int($var) || $var[0] == '-' && ctype_digit(substr($var, 1))) {
            $this->unsafeVar = intval($var);
        } else {
            $this->unsafeVar = $var;
            $this->isValid = false;
        }
    }
}

class ValleyHexadecimal extends ValleyInteger {
    public function __construct($var, $shell=null) {
        if($shell instanceof Valley)
            $this->shell = $shell;
        else
            $this->shell = $this;

        if(ctype_xdigit($var) || is_int($var) || strpos($var, '0x') === 0 && ctype_xdigit(substr($var, 2))) {
            $this->unsafeVar = intval($var, 16);
        } else {
            $this->unsafeVar = $var;
            $this->isValid = false;
        }
    }
}

class ValleyFloat extends ValleyNumber {
    public function __construct($var, $shell=null) {
        $this->unsafeVar = $var;
        if($shell instanceof Valley)
            $this->shell = $shell;
        else
            $this->shell = $this;
        if(!preg_match('/^-?\d*\.?\d+$/', $var) && !is_float($var))
            $this->isValid = false;
        else
            $this->unsafeVar = floatval($var);
    }
}

class ValleyBoolean extends Valley {
    public function __construct($var, $shell=null) {
        parent::__construct($var, $shell);
        $this->safeVar = filter_var($var, FILTER_VALIDATE_BOOLEAN)?1:0;
    }
}

class ValleyArray extends Valley {
    public function __construct($var, $shell=null) {
        parent::__construct($var, $shell);
        if(empty($var))
            $this->unsafeVar = array();
        else if(!is_array($var))
            $this->isValid = false;
    }

    /**
     * The length of wrapped array MUST between $min and $max
     *
     * @param int $min
     * @param int $max [optional]
     * @return $this
     */
    public function length($min, $max=null) {
        if(!$this->isValid)
            return $this;
        $c = count($this->unsafeVar);
        if($c < $min || !is_null($max) && $c >$max)
            $this->isValid = false;
        return $this;
    }
}
