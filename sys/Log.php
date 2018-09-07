<?php
namespace Metz\sys;
/**
 * @file log.php
 * @author vincent
 * @date 2014-5-8
 * @description  基于error_log的日志类
 *  使用方法：
 *    1. 进程启动先Log::get_instance()->init($CONFIG);
 *      $CONFIG(level：日志最大等级、path：地址、file_prefix：日志文件前缀)
 *    2. 使用Log::$LOG_LEVEL_KEYWORD
 *      $LOG_LEVEL_KEYWORD("ERROR | WARN | NOTICE | INFO | DEBUG")
 **/
class Log {
    protected static $_instance = null;

    const LEVEL_ERROR = 1;
    const LEVEL_WARN  = 2;
    const LEVEL_NOTICE = 4;
    const LEVEL_MONITOR = 16;
    const LEVEL_RECORD = 31;
    const LEVEL_INFO  = 32;
    const LEVEL_DEBUG = 64;

    protected $_levels = array(
        self::LEVEL_ERROR	=> 'ERROR',
        self::LEVEL_WARN	=> 'WARN',
        self::LEVEL_NOTICE	=> 'NOTICE',
        self::LEVEL_MONITOR => 'MONITOR',
        self::LEVEL_RECORD	=> 'RECORD',
        self::LEVEL_INFO	=> 'INFO',
        self::LEVEL_DEBUG	=> 'DEBUG',
    );

    protected $_level_words = array(
        self::LEVEL_ERROR	=> '--ERROR--',
        self::LEVEL_WARN	=> '--WARNNING--',
        self::LEVEL_NOTICE	=> '--NOTICE--',
        self::LEVEL_MONITOR	=> '--MONITOR--',
        self::LEVEL_RECORD	=> '--RECORD--',
        self::LEVEL_INFO	=> '--INFO--',
        self::LEVEL_DEBUG	=> '--DEBUG--',
    );

    const FILE_INFIX_HIGH_LEVEL = 'errors';
    const FILE_INFIX_LOW_LEVEL = 'normal';
    const FILE_INFIX_MONITOR = 'monitor';
    const FILE_INFIX_RECORD = 'record';

    protected $_file_infixes = array(
        self::LEVEL_ERROR	=> self::FILE_INFIX_HIGH_LEVEL,
        self::LEVEL_WARN	=> self::FILE_INFIX_HIGH_LEVEL,
        self::LEVEL_NOTICE	=> self::FILE_INFIX_HIGH_LEVEL,
        self::LEVEL_MONITOR => self::FILE_INFIX_MONITOR,
        self::LEVEL_RECORD	=> self::FILE_INFIX_RECORD,
        self::LEVEL_INFO	=> self::FILE_INFIX_LOW_LEVEL,
        self::LEVEL_DEBUG	=> self::FILE_INFIX_LOW_LEVEL,
    );

    protected $_config = array(
        'file_prefix'	=> '',
        'path'			=> '/var/log',
        'date_format'	=> 'Y-m-d H:i:s',
        'log_level'		=> self::LEVEL_NOTICE,
    );

    protected $_deepth = -1;

    protected function __construct()
    {
    }
    /**
     * 获取实例的方法
     * @return array
     */
    static public function get_instance()
    {
        if (is_null(self::$_instance)) {
            $class = __CLASS__;
            self::$_instance = new $class;
        }
        return self::$_instance;
    }

    /**
     * @param array $config
     * @return $this
     */
    public function init($config = array())
    {
        isset($config['path']) && $this->set_path($config['path']);
        isset($config['level']) && $this->set_level($config['level']);
        isset($config['file_prefix']) && $this->set_file_prefix($config['file_prefix']);
        return $this;
    }

    /**
     * 禁用对象克隆
     * @throws Exception
     */
    protected function __clone()
    {
        throw new Exception("Could not clone the object from class: ".__CLASS__);
    }

    /**
     * @param $level_word
     * @param $format
     * @param array $values
     * @param null $des
     * @return bool
     */
    public function log($level_word, $format, $values = array(), $prefix = null, $des = null)
    {
        $level = self::LEVEL_ERROR;
        $level_array = array_flip($this->_levels);
        $level_word = strtoupper($level_word);
        if (isset($level_array[$level_word])) {
            $level = $level_array[$level_word];
        }
        if ($this->_level_check($level) === false) {
            
            return true;
        }
        $msg = '';
        if (is_string($format)) {
            is_string($values) && $values = [$values];
            $msg = empty($values) ? $format : vsprintf($format, $values);
        } elseif (is_object($format) && ($format instanceof Closure)) {
            $msg = call_user_func_array($format, $values);
        }
        return $this->_write($msg, $level, $prefix, $des);
    }

    /**
     * @return bool|string
     */
    protected function _log_prefix()
    {
        $prefix = date($this->_config['date_format']);
        $trace = debug_backtrace();
        $file = '';
        $line = '';
        $class = '';
        $func = '';
        if ($this->_deepth < 0) {
            foreach ($trace as $_t) {
                if ((isset($_t['class']) && $_t['class'] == get_called_class())
                    || (isset($_t['file']) && $_t['file'] == __FILE__)
                    || (isset($_t['function']) && $_t['function'] == '{closure}')
                ) {
                    $this->_deepth++;
                    continue;
                }
                isset($_t['file']) && $file = $_t['file'];
                isset($_t['line']) && $line = $_t['line'];
                isset($_t['class']) && $class = $_t['class'];
                isset($_t['function']) && $func = $_t['function'];
                break;
            }
        } elseif (isset($trace[$this->_deepth])) {
            isset($trace[$this->_deepth]['file']) && $file = $trace[$this->_deepth]['file'];
            isset($trace[$this->_deepth]['line']) && $line = $trace[$this->_deepth]['line'];
            isset($trace[$this->_deepth]['class']) && $class = $trace[$this->_deepth]['class'];
            isset($trace[$this->_deepth]['function']) && $func = $trace[$this->_deepth]['function'];
        }
        $prefix .= sprintf(
            "\tFile: [%s]\tLine: [%s]\tPid: [%d]\t",
            strval($file), strval($line), posix_getpid()
        );
        return $prefix;
    }

    /**
     * @return array
     */
    protected function _get_levels()
    {
        return $this->_levels;
    }

    /**
     * @param $level
     * @return $this
     */
    public function set_level($level)
    {
        $this->_config['log_level'] = $level;
        return $this;
    }

    /**
     * @return int
     */
    public function get_level()
    {
        $log_level = isset($this->_config['log_level'])
            ? $this->_config['log_level'] : self::LEVEL_NOTICE;
        return $log_level;
    }

    /**
     * @param $path
     * @return $this
     */
    public function set_path($path)
    {
        $this->_config['path'] = $path;
        return $this;
    }

    /**
     * @return string
     */
    public function get_path()
    {
        $log_path = isset($this->_config['path'])
            ? $this->_config['path'] : '/var/log';
        return $log_path;
    }

    /**
     * @param $prefix
     * @return $this
     */
    public function set_file_prefix($prefix)
    {
        $this->_config['file_prefix'] = $prefix;
        return $this;
    }

    /**
     * @return string
     */
    public function get_file_prefix()
    {
        return isset($this->_config['file_prefix'])
            ? $this->_config['file_prefix'] : '';
    }

    /**
     * @param $level
     * @return bool
     */
    protected function _level_check($level)
    {
        $log_level = $this->get_level();
        if ($level > $log_level) {
            return false;
        }
        return true;
    }

    /**
     * Logs a message to the log file at the given level
     *
     * @param string $message
     * @param int $level
     * @return bool
     */
    protected function _write($message, $level = self::LEVEL_ERROR, $prefix = null, $des = null) {
        $level_word = null;
        if (isset($this->_level_words[$level])) {
            $level_word = $this->_level_words[$level];
        } else {
            $this->_write_to_sys_log($message);
            return false;
        }
        $file_infix = $level_word;
        if (isset($this->_file_infixes[$level])) {
            $file_infix = strtolower($this->_file_infixes[$level]);
        }
        if (empty($des)) {
            $des = $this->get_path();
        }
        if (!is_dir($des)) {
            @mkdir($des, 0755);
        }
        if(substr($des, -1,1)!='/'){
            $des.='/';
        }
        $filepath = (!isset($this->_config['file_prefix']) || empty($this->_config['file_prefix']))
            ? $des : $des. $this->_config['file_prefix'] . '.';
        $filepath .= $file_infix . '.' . date('YmdH') . '.log';
        $content  = sprintf(
            "%s\t%s\t%s\n",
            $level_word,
            $prefix ? $prefix : $this->_log_prefix(),
            trim($message)
        );
        return @error_log($content, 3, $filepath);
    }

    /**
     * @param $message
     * @return bool
     */
    protected function _write_to_sys_log($message)
    {
        if (is_resource(STDERR)) {
            fprintf(
                STDERR,
                "--STDERR-LOG--\t%s\tmessage: %s",
                $this->_log_prefix(),
                $message
            );
        } else {
            @error_log(
                sprintf(
                    "--NO-LEVEL-LOG--\t%s\tmessage: %s",
                    $this->_log_prefix(),
                    $message
                ));
        }
        return true;
    }

    /**
     * @param $name
     * @param $args
     * @return mixed
     * @throws Exception
     */
    static function __callStatic($name, $args)
    {
        $log = self::get_instance();
        $log_levels = $log->_get_levels();
        if (in_array(strtoupper($name), $log_levels)) {
            array_unshift($args, $name);
            return call_user_func_array(array($log, 'log'),  $args);
        }
        throw new Exception('Method '.$name.' not exists');
    }
}
