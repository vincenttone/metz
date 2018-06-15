<?php
namespace Metz\lib;

class SourceFile implements Iterator
{
    const MODE_READ = 1;
    const MODE_WRITE = 2;
    const MODE_APPEND = 3;

    const ERRNO_OK = 0;
    const ERRNO_FILE_NOT_EXISTS = 1;
    const ERRNO_DATA_FORMAT = 2;
    const ERRNO_READ_FILE = 3;
    const ERRNO_WRITE_FILE = 4;
    
    public $separator = "\t";
    public $has_header = true;
    public $custom_header = null;

    private $_file_handler = null;
    private $_header_fields = null;
    private $_header_fields_count = 0;
    private $_current_line_no = 0;
    private $_current_data = null;
    private $_is_valid = false;

    /**
     * @param string $file
     * @param int $mode
     * @throws Exception
     */
    function __construct($file, $mode = self::MODE_READ)
    {
        if ($mode === self::MODE_READ) {
            if (file_exists($file)) {
                $this->_file_handler = fopen($file, 'r');
            } else {
                throw new Exception(
                    'File ['.$file.'] not exists!',
                    self::ERRNO_FILE_NOT_EXISTS
                );
            }
            $this->init_header();
        } else {
            $m = 'w';
            $mode === self::MODE_APPEND && $m = 'a';
            if (!is_dir(dirname($file))) {
                if (!mkdir(dirname($file), 0755, true)) {
                    throw new Exception(
                        'Dir ['.dirname($file).'] not exists!',
                        self::ERRNO_FILE_NOT_EXISTS
                    );
                }
            }
            $this->_file_handler = fopen($file, $m);
        }
        if ($this->_file_handler === false) {
            throw new Exception('Open file ['.$file.'] failed!', self::ERRNO_READ_FILE);
        }
        $this->_current_line_no = 0;
    }

    /**
     * @param $data
     * @return array
     */
    function write_data(&$data)
    {
        if ($this->has_header && $this->_current_line_no === 0) {
            $first_data = reset($data);
            if ($first_data === false) {
                return [
                    'errno' => self::ERRNO_WRITE_FILE,
                    'data' => 'write data empty!',
                ];
            }
            $line = implode($this->separator, array_keys($first_data));
            $line .= "\n";
            $write_file = fwrite($this->_file_handler, $line);
            if ($write_file === false) {
                return [
                    'errno' => self::ERRNO_WRITE_FILE,
                    'data' => 'write data faild!',
                ];
            }
        }
        $line = '';
        foreach ($data as $_d) {
            $line .= implode($this->separator, $_d);
            $line .= "\n";
            $this->_current_line_no++;
        }
        $write_file = true;
        if (!empty($line)) {
            $write_file = fwrite($this->_file_handler, $line);
        }
        if ($write_file === false) {
            return [
                'errno' => self::ERRNO_WRITE_FILE,
                'data' => 'write data faild!',
            ];
        }
        return [
            'errno' => self::ERRNO_OK,
            'data' => $this->_current_line_no,
        ];
    }

    /**
     * @param $line
     * @return string
     */
    private static function _trim_line($line)
    {
        return trim($line, "\r\n\0 ");
    }

    /**
     * @return null
     * @throws Exception
     */
    private function init_header()
    {
        if (!$this->has_header) {
            return null;
        }
        if (empty($this->custom_header)) {
            $line = fgets($this->_file_handler);
            if ($line === false) {
                throw new Exception(
                    'fgets faild! check if file ['.$file.'] is ok',
                    self::ERRNO_READ_FILE
                );
            }
        } else {
            $line = $this->custom_header;
        }
        $line = self::_trim_line($line);
        $this->_header_fields = explode($this->separator, $line);
        $this->_header_fields_count = count($this->_header_fields);
    }

    function __destruct()
    {
        is_resource($this->_file_handler)
            && fclose($this->_file_handler);
    }

    function rewind()
    {
        is_resource($this->_file_handler)
            && rewind($this->_file_handler);
        $this->_current_line_no = 0;
        $this->_is_valid = true;
        $this->init_header();
        $this->read_line();
    }

    /**
     * @return array
     */
    function current()
    {
        return $this->_current_data;
    }

    /**
     * @return int
     */
    function current_line_no()
    {
        return $this->_current_line_no;
    }

    /**
     * @return int
     */
    function key()
    {
        return $this->_current_line_no;
    }

    /**
     * @return array|null
     */
    function read_line()
    {
        $this->_current_data = null;
        $line = fgets($this->_file_handler);
        if ($line !== false) {
            $line = self::_trim_line($line);
            $datas = explode($this->separator, $line);
            if ($this->has_header) {
                if ($this->_header_fields_count == count($datas)) {
                    $this->_current_data = [
                        'errno' => self::ERRNO_OK,
                        'data' => array_combine($this->_header_fields, $datas),
                    ];
                } else {
                    $this->_current_data = [
                        'errno' => self::ERRNO_DATA_FORMAT,
                        'data' => $datas,
                    ];
                }
            } else {
                $this->_current_data = [
                    'errno' => self::ERRNO_OK,
                    'data' => $datas,
                ];
            }
            $this->_current_line_no++;
        } else {
            $this->_is_valid = false;
        }
        return $this->_current_data;
    }

    function next()
    {
        $this->read_line();
    }

    /**
     * @return bool
     */
    function valid()
    {
        return $this->_is_valid;
    }
}