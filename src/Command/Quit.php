<?php

namespace Ircbot\Command;

class Quit extends \Ircbot\Type\Command
{
    public $mask;
    public $message;
    public function  __construct($message = null)
    {
        $this->message = $message;
    }

    public function getEventName()
    {
        return 'onQuit';
    }
    public function  __toString()
    {
        return sprintf('QUIT :%s', $this->message) . "\r\n";
    }
}

