<?php
namespace Terranet\Log\Formatter;

class Growl extends \Zend_Log_Formatter_Abstract
{
    /**
     * Factory for Growl class
     *
     * @param array|Zend_Config $options useless
     * @return Growl
     */
    public static function factory($options)
    {
        return new self;
    }

    /**
     * This method formats the event for the Growl writer.
     *
     * The default is to just send the message parameter, but through
     * extension of this class and calling the
     * {@see \Terranet\Log\Writer\Growl::setFormatter()} method you can
     * pass as much of the event data as you are interested in.
     *
     * @param  array    $event    event data
     * @return mixed              event message
     */
    public function format($event)
    {
        return $event['message'] . PHP_EOL;
    }
}
