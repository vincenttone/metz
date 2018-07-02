<?php
namespace Metz\app\metz;

use Metz\app\metz\exceptions;

class RenderEngine
{
    const TYPE_TXT = 1;
    const TYPE_JSON = 2;
    const TYPE_XML  = 3;
    const TYPE_HTML = 4;
    const TYPE_JAVASCRIPT = 5;
    const TYPE_JPG = 11;
    const TYPE_JPEG = 12;
    const TYPE_PNG = 13;
    const TYPE_GIF = 14;

    protected static $_content_type_map = [
        self::TYPE_TXT  => 'text/plain',
        self::TYPE_JSON => 'application/json',
        self::TYPE_XML  => 'text/xml',
        self::TYPE_HTML => 'text/html',
        self::TYPE_JAVASCRIPT => 'application/x-javascript',
        self::TYPE_JPEG => 'image/jpeg',
        self::TYPE_JPG  => 'application/x-jpg',
        self::TYPE_PNG  => 'image/png',
        self::TYPE_GIF  => 'image/gif',
    ];
    
    protected static $_instance = null;

    protected $_template_dir = null;
    protected $_template_suffix = 'phtml';
    protected $_buffer = '';
    protected $_render_fmt = self::TYPE_HTML;

    protected function __clone()
    {
        throw new \Exception('clone failed!');
    }

    public static function engine($dir, $globals = [], $suffix = 'phtml')
    {
        if (self::$_instance === null) {
            $kls = get_class();
            self::$_instance = new $kls($dir, $globals, $suffix);
        }
        return self::$_instance;
    }

    public static function format($type)
    {
        return self::engine()->set_render_format($type);
    }

    public static function json($data)
    {
        return self::engine()->render_to_json($data);
    }

    public static function html($template, $var = [], $base = null)
    {
        return self::engine()->render($template, $var, $base);
    }

    protected function __construct($dir, $globals = [], $suffix = 'phtml')
    {
        $this->set_template_dir($dir);
        $this->set_template_suffix($suffix);
        $this->assign_global_vars($globals);
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
     * @param array $template
     * @param array $var
     */
    function render($template, array $var = [], $base_template = null)
    {
        if ($base_template === null) {
            $this->_render_template($template, $var);
        } else {
            $base_var = [];
            foreach ($template as $_name => $_t) {
                $_v = isset($var[$_name]) ? $var[$_name] : [];
                $base_var[$_name] = $this->_render_template($_t, $_v);
            }
            $this->_render_template($base_template, $base_var);
        }
        return $this->_buffer;
    }
    /**
     * @param array $data
     * @param string $format
     */
    function render_to_json($data)
    {
        $this->_buffer = json_encode($data);
        return $this->_buffer;
    }
    
    function display()
    {
        echo $this->_buffer;
        return $this;
    }
    /**
     * @desc set render format
     * @param $type=self::TYPE_HTML
     * @return 
     */
    function set_render_format($type = self::TYPE_HTML)
    {
        if (isset(self::$_content_type_map[$type])) {
            $this->_render_fmt = $type;
            header('Content-Type: ' . self::$_content_type_map[$type]);
        }
    }
    /**
     * @param array $template
     * @return bool|string
     */
    public function get_template_file($template)
    {
        if (empty($this->_template_dir) || !is_dir($this->_template_dir)) {
            throw new exceptions\file\NotExists(
                __METHOD__.'please set correct template dir and check is a dir: '
                .var_export($template, true));
        }
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
        $this->_buffer = ob_get_contents();
        ob_end_clean();
        ob_start();
        return $this;
    }
}