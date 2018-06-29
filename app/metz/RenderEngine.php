<?php
namespace Metz\app\metz;

use Metz\app\metz\exceptions;

class RenderEngine
{
    protected static $_instance = null;

    protected $_template_dir = null;
    protected $_template_suffix = 'phtml';

    protected function __clone()
    {
        throw new \Exception('clone failed!');
    }

    public static function engine($dir, $suffix = 'phtml')
    {
        if (self::$_instance === null) {
            $kls = get_class();
            self::$_instance = new $kls($dir, $suffix);
        }
        return self::$_instance;
    }

    protected function __construct($dir, $suffix = 'phtml')
    {
        $this->set_template_dir($dir);
        $this->set_template_suffix($suffix);
    }

    public function set_template_dir($template_dir)
    {
        $this->_template_dir = $template_dir;
        return $this;
    }

    public function set_template_suffix($suffix)
    {
        $this->_template_suffix = $suffix;
        return $this;
    }
    /**
     * @desc nest render templates base on $base_template
     * @param $children
     * @param $base_template
     * @return void
     */
    public function nest_render($children, $base_template)
    {
        if (!is_array($children)) {
            throw exceptions\common\UnexpectParamsType('nest render params err: ' . json_encode($children));
        }
        $template_data = [];
        foreach ($children as $_name => $_temp_info) {
            $var = [];
            if (!isset($_temp_info)) {
                continue;
            }
            $temp = $_temp_info[0];
            isset($_temp_info[1]) && $var = $_temp_info[1];
            $_temp_data = $this->_render_template($temp, $var);
            if ($_temp_data === false ) {
                continue;
            }
            $template_data[$_name] = $_temp_data;
        }
        $this->render($base_template, $template_data);
    }

    /**
     * @param array $template
     * @param array $var
     */
    function render($template, array $var = [])
    {
        if (empty($this->_template_dir) || !is_dir($this->_template_dir)) {
            throw new exceptions\file\NotExists(
                __METHOD__.'please set correct template dir and check is a dir: '
                .var_export($template, true));
        }
        echo $this->_render_template($template, $var);
    }

    /**
     * @param array $data
     * @param string $format
     */
    function output($data, $format = 'json')
    {
        header("Content-Type: application/json");
        switch ($format) {
            case 'json':
            default:
                $data = json_encode($data);
                break;
        }
        echo $data;
    }
    /**
     * @param array $template
     * @return bool|string
     */
    public function get_template_file($template)
    {
        $template_file = $this->_template_dir . '/' . $template . '.' . $this->_template_suffix;
        if (is_file($template_file)) {
            return $template_file;
        } else {
            throw new exceptions\file\NotExists('template file [' . $template_file . '] not exists!');
        }
    }

    /**
     * @param array $vars
     * @return $this
     */
    public function assign_global_vars(array $vars)
    {
        $this->_vars = $vars;
        return $this;
    }

    /**
     * @param array $template
     * @param array $var
     * @return string
     */
    protected function _render_template($template, array $var = [])
    {
        $template_file = $this->get_template_file($template);
        if (empty($template_file)) {
            return '';
        }
        $var = array_merge($var, $this->_vars);
        extract($var);
        //ob_end_flush();
        ob_end_clean();
        ob_start();
        require $template_file;
        $content = ob_get_contents();
        ob_end_clean();
        ob_start();
        return $content;
    }
}