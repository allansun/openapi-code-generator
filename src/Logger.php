<?php

namespace OpenAPI\CodeGenerator;

use JetBrains\PhpStorm\Pure;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Logger
{
    /**
     * @var null|Logger
     */
    static private ?Logger $instance = null;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface|NullLogger $logger;

    /**
     * Logger constructor.
     */
    #[Pure] private function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * This function is only intended to be used in unit test
     * You do not normally need to call it
     *
     * @return Logger
     */
    static public function reInitiate(): Logger
    {
        static::$instance = null;

        return self::getInstance();
    }

    static public function getInstance(): Logger
    {
        if (!static::$instance) {
            static::$instance = new Logger();
        }

        return static::$instance;
    }

    /**
     * @param         $message
     * @param  array  $extra
     *
     * @return $this
     */
    public function debug($message, array $extra = []): self
    {
        $this->logger->debug($message, $extra);

        return $this;
    }

    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @param  LoggerInterface  $logger
     *
     * @return $this
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function info($message, $extra = [])
    {
        $this->logger->info($message, $extra);
    }
}