<?php

namespace AppSupport\Support\Facades;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class Log
 * @package AppSupport\Support\Facades
 *
 * @method static emergency($message, array $context = array());
 * @method static alert($message, array $context = array());
 * @method static critical($message, $context = array());
 * @method static error($message, array $context = array());
 * @method static warning($message, array $context = array());
 * @method static notice($message, array $context = array());
 * @method static info($message, array $context = array());
 * @method static debug($message, array $context = array());
 * @method static log($level, $message, array $context = array());
 * @method static incTab($level = LogLevel::EMERGENCY);
 * @method static decTab($level = LogLevel::EMERGENCY);
 * @method static backtrace($options = DEBUG_BACKTRACE_PROVIDE_OBJECT, $limit = 0)
 */
class Log extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return LoggerInterface::class;
    }
}
