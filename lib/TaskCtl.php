<?php
namespace Metz\lib;

class TaskCtl
{
    private $_callback;
    private $_max_process;
    private $_task_no = 0;

    private $_current_process_count = 0;
    private $_current_pids = [];

    /**
     * @param int $max_process
     * @param null $callback
     */
    public function __construct($max_process = 10, $callback = null)
    {
        $this->set_max_process($max_process);
        $callback === null || $this->set_callback($callback);
    }

    /**
     * @param $max_process
     * @return $this
     */
    public function set_max_process($max_process)
    {
        $this->_max_process = $max_process;
        return $this;
    }

    /**
     * @param $callback
     * @return $this
     */
    public function set_callback($callback)
    {
        $this->_callback = $callback;
        return $this;
    }

    /**
     * @param bool $increasing
     * @return int
     */
    public function get_task_no($increasing = false)
    {
        return $increasing
            ? $this->_task_no
            : ($this->_task_no % $this->_max_process) + 1;
    }

    /**
     * @throws Exception
     */
    public function run_task()
    {
        $this->_task_no++;
        $pid = pcntl_fork();
        if ($pid < 0) {
            throw new Exception('fork task failed!');
        } elseif ($pid) {
            // parent processor
            $this->_current_pids[$pid] = $pid;
            $this->_current_process_count++;
            if ($this->_current_process_count >= $this->_max_process) {
                $childPid = pcntl_wait($status, WUNTRACED);
                unset($this->_current_pids[$childPid]);
                $this->_current_process_count--;
            }
        } else {
            // child processor
            $args = func_get_args();
            call_user_func_array($this->_callback, $args);
            exit;
        }
    }

    /**
     * @return $this
     */
    public function wait_children() {
        while($this->_current_pids) {
            $pid = pcntl_wait($status);
            if($pid > 0) {
                unset($this->_current_pids[$pid]);
                $this->_current_process_count--;
            }
        }
        return $this;
    }
}
