<?php
namespace Gaer;

class DaoSet extends \ArrayObject implements \JsonSerializable
{
    public $page = 1;

    protected $_count = 30;
    protected $sort = null;
    protected $_cond = null;

    protected $_dao_class = null;

    public function __construct($dao_class)
    {
        $this->_dao_class = $dao_class;
    }

    
}