<?php
class IRCBot_Commands_Notice extends IRCBot_Types_MessageCommand
{
    public function  __construct($target = null, $message = null) {
        $this->target = $target;
        $this->message = $message;
    }
    public function fromRawData($rawData)
    {
        sscanf($rawData, ':%s NOTICE %s :%[ -~]', $this->sender, $this->target,
            $this->message);
        $mask = new IRCBot_Types_Mask();
        $mask->fromMask($this->sender);
        $this->sender = $mask->nickname;
        $this->mask = $mask;
    }
    public function  __toString() {
        return sprintf('NOTICE %s :%s', $this->target, $this->message) . "\n\r";
    }
}
?>