<?php
namespace Metz\Lib;

class RandomStartIterator implements Iterator
{
    private $_max_loop_deep = 1;

    private $_elements = [];
    private $_is_valid = false;
    private $_element_count = 0;

    private $_cursor = 0;
    private $_current_count = 0;

    /**
     * @param $deep
     * @return $this
     */
    public function set_max_loop_deep($deep)
    {
        $this->_max_loop_deep = $deep;
        return $this;
    }

    /**
     * @param $elements
     * @return $this
     */
    public function set_elements($elements)
    {
        $this->_elements = array_values($elements);
        $this->_element_count = count($this->_elements);
        return $this;
    }

    /**
     * @param $element
     * @return $this
     */
    public function add_element($element)
    {
        array_push($this->_elements, $element);
        $this->_element_count++;
        return $this;
    }

    /**
     * @return $this
     */
    private function _init_cursor()
    {
        $this->_cursor = rand(0, $this->_element_count - 1);
        $this->_current_count = 1;
        return $this;
    }

    /**
     * @return $this
     */
    private function _incr_cursor()
    {
        $this->_cursor++;
        $this->_cursor = $this->_cursor % $this->_element_count;
        $this->_current_count++;
        return $this;
    }

    /**
     * @return $this
     */
    private function _check_element_count()
    {
        if ($this->_element_count < 1) {
            $this->_is_valid = false;
        } else {
            $this->_is_valid = true;
        }
        return $this;
    }

    /**
     *
     */
    public function rewind()
    {
        $this->_check_element_count();
        $this->_init_cursor();
    }

    /**
     * @return mixed
     */
    public function current()
    {
        if ($this->_current_count >= $this->_max_loop_deep) {
            $this->_is_valid = false;
        }
        return $this->_elements[$this->_cursor];
    }

    /**
     * @return int
     */
    public function key()
    {
        return $this->_current_count;
    }


    public function next()
    {
        $this->_incr_cursor();
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return $this->_is_valid;
    }
}