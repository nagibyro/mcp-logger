<?php

namespace MCP\Logger\Service;

use MCP\Logger\Exception;
use MCP\Logger\LogLevelInterface;
use MCP\Logger\MessageInterface;
use MCP\Logger\RendererInterface;
use MCP\Logger\ServiceInterface;

/**
 * Logging service for sending logs to Syslog
 */
class SyslogService implements ServiceInterface, LogLevelInterface
{
    // Configuration Keys
    const CONFIG_SILENT = 'silent';
    const CONFIG_IDENT = 'ident';
    const CONFIG_FACILITY = 'facility';
    const CONFIG_OPTIONS = 'options';

    // Configuration Defaults
    const DEFAULT_SILENT = true;
    const DEFAULT_IDENT = '';
    const DEFAULT_FACILITY = LOG_USER;
    const DEFAULT_OPTIONS = LOG_ODELAY | LOG_CONS;

    // Error Messages
    const ERR_OPEN = 'Unable to open syslog connection.';
    const ERR_SEND = 'Unable to send message to syslog connection. %s';

    /**
     * @var RendererInterface
     */
    private $renderer;

    /**
     * @var array
     */
    private $configuration;

    /**
     * @var ServiceInterface
     */
    private $backup;

    /**
     * @var bool
     */
    private $status;

    /**
     * @param RendererInterface $renderer
     * @param array $configuration
     * @param ServiceInterface|null $backup
     */
    public function __construct(RendererInterface $renderer, array $configuration = [], ServiceInterface $backup = null)
    {
        $this->configuration = array_merge([
            self::CONFIG_SILENT => self::DEFAULT_SILENT,
            self::CONFIG_IDENT => self::DEFAULT_IDENT,
            self::CONFIG_FACILITY => self::DEFAULT_FACILITY,
            self::CONFIG_OPTIONS => self::DEFAULT_OPTIONS
        ], $configuration);

        $this->renderer = $renderer;
        $this->backup = $backup;
        $this->status = false;
    }

    /**
     * @param MessageInterface $message
     * @return void
     */
    public function send(MessageInterface $message)
    {
        if ($this->status === false) {
            $this->connect();
        }

        $data = call_user_func($this->renderer, $message);
        $this->status = syslog($this->priority($message->level()), $data);

        if ($this->status === false) {
            if ($this->backup instanceof ServiceInterface) {
                $this->backup->send($message);
            } else {
                $this->error(sprintf(self::ERR_SEND, $data));
            }
        }
    }

    /**
     * Attempt to connect to open syslog connection
     */
    private function connect()
    {
        $this->status = openlog(
            $this->configuration[self::CONFIG_IDENT],
            $this->configuration[self::CONFIG_OPTIONS],
            $this->configuration[self::CONFIG_FACILITY]
        );

        if ($this->status === false) {
            return $this->error(self::ERR_OPEN);
        }
    }

    /**
     * Handle an error
     *
     * @param string $message
     * @throws Exception
     * @return bool
     */
    private function error($message)
    {
        if ($this->configuration[self::CONFIG_SILENT]) {
            // @codeCoverageIgnoreStart
            return error_log($message);
            // @codeCoverageIgnoreEnd
        }

        throw new Exception($message);
    }

    /**
     * Convert from Core error levels to Syslog priority
     *
     * @param $level
     * @return int
     */
    private function priority($level)
    {
        switch ($level) {
            case self::DEBUG:
                return LOG_DEBUG;
            case self::INFO:
                return LOG_INFO;
            case self::WARN:
                return LOG_WARNING;
            case self::ERROR:
                return LOG_ERR;
            case self::FATAL:
                return LOG_CRIT;
            case self::AUDIT:
                return LOG_NOTICE;
            default:
                return LOG_WARNING;
        }
    }

}