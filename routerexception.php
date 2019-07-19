<?php

namespace Fend;

class RouterException extends \Exception
{
    private $data = [];

    public function __construct($msg, $code = 0, $data = array())
    {
        parent::__construct($msg, $code);
        $this->data = $data;
    }

    public function getExtendData()
    {
        return $this->data;
    }
}