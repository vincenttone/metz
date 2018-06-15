<?php
trait ClassCommonHooks {
    // hooks array
    private $_hooks = [
        /*
        'hook_type' => [
            'hook_name' => [
                'callback', // callback
                'run_once', // bool
            ],
        ]
        */
    ];

    /**
     * @param string $hook_type
     * @param string $hook_name
     * @param $callback
     * @param bool $run_once
     * @return $this
     */
    function register_hook($hook_type, $hook_name, $callback, $run_once = false)
    {
        isset($this->_hooks[$hook_type]) || ($this->_hooks[$hook_type] = []);
        $this->_hooks[$hook_type][$hook_name] = [$callback, $run_once];
        return $this;
    }

    /**
     * @param string $hook_type
     * @param string $hook_name
     * @return bool
     */
    function hook_exists($hook_type, $hook_name = null)
    {
        if ($hook_name === null) {
            if (isset($this->_hooks[$hook_type])) {
                return true;
            } else {
                return false;
            }
        }
        if (isset($this->_hooks[$hook_type][$hook_name])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param string $hook_type
     * @param string $hook_name
     * @return null|array
     */
    function get_hook($hook_type, $hook_name)
    {
        if (!$this->hook_exists($hook_type, $hook_name)) {
            return null;
        }
        $hook = $this->_hooks[$hook_type][$hook_name];
        if (!isset($hook[1])) {
            Lib_Log::notice(
                "Hook: [%s.%s] [%s] not has run_once flag",
                [strval($hook_type), $hook_name, var_export($hook[1], true)]
            );
            unset($this->_hooks[$hook_type][$hook_name]);
            return null;
        }
        if (!is_callable($hook[0])) {
            Lib_Log::notice(
                "Hook: [%s.%s] [%s] not callable",
                [strval($hook_type), $hook_name, json_encode($hook[0])]
            );
            unset($this->_hooks[$hook_type][$hook_name]);
            return null;
        }
        return $hook;
    }

    /**
     * @param string $hook_type
     * @param string $hook_name
     * @param array $args
     * @return bool
     */
    function run_hook_by_type_and_name($hook_type, $hook_name, $args = [])
    {
        $hook = $this->get_hook($hook_type, $hook_name);
        if ($hook === null) {
            return false;
        }
        call_user_func_array($hook[0], $args);
        // run once, remove
        if ($hook[1] == true) {
            unset($this->_hooks[$hook_type][$hook_name]);
        }
        return true;
    }

    /**
     * @param string $hook_type
     * @param array $args
     * @return bool
     */
    function run_hooks_by_type($hook_type = self::HOOK_TYPE_BEFORE_RUN, $args = [])
    {
        if (!$this->hook_exists($hook_type)) {
            return false;
        }
        foreach ($this->_hooks[$hook_type] as $_k => $_hook) {
            $this->run_hook_by_type_and_name($hook_type, $_k, $args);
        }
        return true;
    }

    /**
     * @param string $prefix
     * @param string $type
     * @return string
     */
    private static function _dynamic_hook_type($prefix, $type)
    {
        return strval($prefix).'_'.$type;
    }
}