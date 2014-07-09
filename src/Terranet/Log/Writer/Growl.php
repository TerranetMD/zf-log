<?php
namespace Terranet\Log\Writer;

class Growl extends \Zend_Log_Writer_Abstract
{
    const GROWL_PRIORITY_LOW        = -2;
    const GROWL_PRIORITY_MODERATE   = -1;
    const GROWL_PRIORITY_NORMAL     = 0;
    const GROWL_PRIORITY_HIGH       = 1;
    const GROWL_PRIORITY_EMERGENCY  = 2;


    const GROWL_CONNECTION_SOCKET   = 'socket';
    const GROWL_CONNECTION_FSOCK    = 'fsock';

    protected $_connectionType      = self::GROWL_CONNECTION_FSOCK;

    /**
     * Application name.
     *
     * @var string
     */
    protected $appName = 'PHP Growl';

    /**
     * Notifications stack
     *
     * @var array
     */
    protected $notifications;

    protected $connection           = null;

    protected $_priorityStyles = array(
        \Zend_Log::EMERG  => self::GROWL_PRIORITY_EMERGENCY,
        \Zend_Log::ALERT  => self::GROWL_PRIORITY_EMERGENCY,
        \Zend_Log::CRIT   => self::GROWL_PRIORITY_EMERGENCY,
        \Zend_Log::ERR    => self::GROWL_PRIORITY_HIGH,
        \Zend_Log::WARN   => self::GROWL_PRIORITY_NORMAL,
        \Zend_Log::NOTICE => self::GROWL_PRIORITY_MODERATE,
        \Zend_Log::INFO   => self::GROWL_PRIORITY_LOW,
        \Zend_Log::DEBUG  => self::GROWL_PRIORITY_LOW);

    /**
     * The default logging style for un-mapped priorities
     *
     * @var string
     */
    protected $_defaultPriorityStyle = self::GROWL_PRIORITY_LOW;

    /**
     * Flag indicating whether the log writer is enabled
     *
     * @var boolean
     */
    protected $_enabled = true;

    /**
     * All messages are sticky - remains active until closed
     *
     * @var bool
     */
    protected $_isSticky = false;

    /**
     * Class Constructor
     *
     * @param string $appName Application name
     * @param array $connection - connection params
     * @return void
     */
    public function __construct($appName = null, $options = array())
    {
        if (null !== $appName) {
            $this->appName    = utf8_encode($appName);
        }

        if (array_key_exists('sticky', $options)) {
            $this->setSticky($options['sticky']);
            unset($options['sticky']);
        }

        $this->connection = array(
            'address'   => (isset($options['address'])  ? $options['address'] : '127.0.0.1'),
            'password'  => (isset($options['password']) ? $options['password'] : 'password'),
            'port'      => (isset($options['port'])     ? $options['port'] : 9887)
        );

        $this->notifications = array();

        $this->_formatter = new \Terranet\Log\Formatter\Growl();

        $this->_register();
    }

    /**
     * Register application in Growl
     *
     * @return bool
     */
    protected function _register()
    {
        $data         = '';
        $defaults     = '';
        $num_defaults = 0;

        $this->notifications[] = array(
            'name'      => $this->appName,
            'enabled'   => true
        );

        for($i = 0; $i < count($this->notifications); $i++) {
            $data .= pack('n', strlen($this->notifications[$i]['name'])) . $this->notifications[$i]['name'];
            if($this->notifications[$i]['enabled']) {
                $defaults .= pack('c', $i);
                $num_defaults++;
            }
        }

        // pack(Protocol version, type, app name, number of notifications to register)
        $data  = pack('c2nc2', 1, 0, strlen($this->appName), count($this->notifications), $num_defaults) . $this->appName . $data . $defaults;
        $data .= pack('H32', md5($data . $this->connection['password']));

        return $this->_send($data);
    }

    /**
     * Send data to Growl
     *
     * @param $data
     * @return bool
     */
    protected function _send($data)
    {
        if (self::GROWL_CONNECTION_SOCKET == $this->_connectionType && function_exists('socket_create') && function_exists('socket_sendto')) {
            $socket = (strlen(inet_pton($this->connection['address'])) > 4 && defined('AF_INET6'))
                ? socket_create(AF_INET6, SOCK_DGRAM, SOL_UDP) : socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            socket_sendto($socket, $data, strlen($data), 0x100, $this->connection['address'], $this->connection['port']);

            return true;
        } elseif (self::GROWL_CONNECTION_FSOCK == $this->_connectionType && function_exists('fsockopen')) {
            $fp = fsockopen('udp://' . $this->connection['address'], $this->connection['port']);
            fwrite($fp, $data);
            fclose($fp);

            return true;
        }

        return false;
    }

    /**
     * Factory interface for Growl writer
     *
     * @param  array|Zend_Config $config
     * @return Growl
     */
    static public function factory($config)
    {
        $config = self::_parseConfig($config);
        $appName = isset($config['appName']) ? $config['appName'] : $config['address'];

        $config = array_merge(array(
            'address'   => null,
            'password'  => null,
            'port'      => null,
            'appName'   => null
        ), $config);

        return new self(
            $appName,
            $config
        );
    }

    /**
     * Close the stream resource.
     *
     * @return void
     */
    public function shutdown()
    {
        $this->notifications = array();
    }

    /**
     * Write a message to the log.
     *
     * @param  array  $event  event data
     * @return boolean
     */
    protected function _write($event)
    {
        if (!$this->getEnabled()) {
            return false;
        }

        $name  = utf8_encode($this->appName);
        $title = date('Y-m-d H:i:s', strtotime($event['timestamp']));

        $priority = (int) $event['priority'];

        if (array_key_exists($priority, $this->_priorityStyles)) {
            $priority = $this->_priorityStyles[$priority];
        } else {
            $priority = $this->_defaultPriorityStyle;
        }
        $message = $this->_formatter->format($event);

        $flags = ($priority & 7) * 2;
        if ($priority < 0)
            $flags |= 8;

        if ($this->getSticky())
            $flags |= 256;

        // pack(protocol version, type, priority/sticky flags, notification name length, title length, message length. app name length)
        $data = pack('c2n5', 1, 1, $flags, strlen($name), strlen($title), strlen($message), strlen($name));
        $data .= $name . $title . $message . $name;
        $data .= pack('H32', md5($data . $this->connection['password']));

        return $this->_send($data);
    }

    /**
     * Enable or disable the log writer.
     *
     * @param boolean $enabled Set to TRUE to enable the log writer
     * @return boolean The previous value.
     */
    public function setEnabled($enabled)
    {
        $previous       = $this->_enabled;
        $this->_enabled = $enabled;

        return $previous;
    }

    /**
     * Determine if the log writer is enabled.
     *
     * @return boolean Returns TRUE if the log writer is enabled.
     */
    public function getEnabled()
    {
        return $this->_enabled;
    }

    public function setSticky($flag)
    {
        $this->_isSticky = (bool) $flag;
        return $this;
    }

    public function getSticky()
    {
        return (bool) $this->_isSticky;
    }
}
