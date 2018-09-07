<?php
namespace Gaer\exceptions;

class Base extends \Exception implements \JsonSerializable
{
    public $response_msg = 'something wrong happend...';

    public function __construct($msg = '', $response_msg = '', $code = 0, $previous = null)
    {
        is_array($msg) && $msg = json_encode($msg);
        $this->setResponseMsg($response_msg);
        parent::__construct($msg, $code, $previous);
    }

    public function getResponseMsg()
    {
        return $this->response_msg;
    }

    public function setResponseMsg($msg)
    {
        $this->response_msg = $msg;
        return $this;
    }

    public function getArrayCopy()
    {
        return [
            'code' => $this->getCode(),
            'msg' => $this->getMessage(),
            'line' => $this->getLine(),
            'file' => $this->getFile(),
        ];
    }

    public function jsonSerialize()
    {
        return $this->getArrayCopy();
    }

    public function __toString()
    {
        return $this->getResponseMsg() . "\t" . $this->jsonSerialize();
    }
}