<?php
namespace Metz\lib\mongo;
/**
 * mongo cursor的简单封装
 * 为了支持一些自定义的数据调整操作
 */
class Cursor implements Iterator
{
    private $_cursor = null;
    private $_callback = null;

    /**
     * @param $cursor
     */
    function __construct($cursor)
    {
        $this->set_mongo_cursor($cursor);
    }

    /**
     * @param $cursor
     * @return $this
     */
    function set_mongo_cursor($cursor)
    {
        $this->_cursor = $cursor;
        return $this;
    }

    /**
     * @return null
     */
    function get_mongo_cursor()
    {
        return $this->_cursor;
    }

    /**
     * @param $callback
     */
    function set_callback($callback)
    {
        $this->_callback = $callback;
    }

    /**
     * @return mixed
     */
    public function current ()
    {
        $data = $this->_cursor->current();
        if ($this->_callback !== null) {
            $data = call_user_func($this->_callback, $data);
        }
        return $data;
    }

    /**
     * @return mixed
     */
    public function key ()
    {
        return $this->_cursor->key();
    }

    /**
     * @return mixed
     */
    public function next ()
    {
        return $this->_cursor->next();
    }

    /**
     * @return mixed
     */
    public function rewind ()
    {
        return $this->_cursor->rewind();
    }

    /**
     * @return mixed
     */
    public function valid ()
    {
        return $this->_cursor->valid();
    }

    /**
     * @return mixed
     */
    public function count()
    {
        return $this->_cursor->count();
    }

    /**
     * @return mixed
     */
    public function hint()
    {
        return $this->_cursor->hint();
    }

    /**
     * @return mixed
     */
    public function info()
    {
        return $this->_cursor->info();
    }
}