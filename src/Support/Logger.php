<?php
/**
 * Created by PhpStorm.
 * User: ssaez
 * Date: 24/07/19
 * Time: 19:20
 */

namespace AppSupport\Support;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

class Logger extends AbstractLogger
{
    const TABSIZE = 3;
    const EXITLEVEL = LogLevel::CRITICAL;
    public const DEFAULTLEVEL = LogLevel::DEBUG;

    static private array $levels = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 2,
        LogLevel::ERROR => 3,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 5,
        LogLevel::INFO => 6,
        LogLevel::DEBUG => 7
    ];

    static private array $prompts = [
        LogLevel::EMERGENCY => "(!!)",
        LogLevel::ALERT => "(AA)",
        LogLevel::CRITICAL => "(CC)",
        LogLevel::ERROR => "(EE)",
        LogLevel::WARNING => "(WW)",
        LogLevel::NOTICE => "(NN)",
        LogLevel::INFO => "(II)",
        LogLevel::DEBUG => "(DD)"
    ];

    private int $tab = 0;
    private string $currentLevel = self::DEFAULTLEVEL;

    function log($level, $message, array $context = array()): void
    {
        // TODO: Context is currently ignored
        if (static::$levels[$level] <= static::$levels[static::EXITLEVEL]) {
            $this->tab = 0;
        }

        if (static::$levels[$level] <= static::$levels[$this->currentLevel]) {
            $pref = static::$prompts[$level];
            $tabstr = str_repeat(" ", $this->tab * static::TABSIZE);

            $message = str_replace("\n", "\n{$pref} {$tabstr} ", $message);

            fwrite(STDERR, "{$pref} {$tabstr} {$message}\n");
        }

        if (static::$levels[$level] <= static::$levels[static::EXITLEVEL]) {
            fwrite(STDERR, "\n");
            exit (1);
        }
    }

    public function incTab(string $level = LogLevel::EMERGENCY): void
    {
        if (static::$levels[$level] <= static::$levels[$this->currentLevel]) {
            $this->tab++;
        }
    }

    public function decTab(string $level = LogLevel::EMERGENCY): void
    {
        if (static::$levels[$level] <= static::$levels[$this->currentLevel]) {
            if ($this->tab > 0) {
                $this->tab--;
            }
        }
    }

    /**
     * @return string (LogLevel::const)
     */
    public function getLevel(): string
    {
        return $this->currentLevel;
    }

    /**
     * @param string $level
     *
     * @return string Return the previous LogLevel
     *
     * @throws \LogicException
     */
    public function setLevel($level): string
    {
        if (!isset(static::$levels[$level])) {
            throw new \LogicException("Invalid LogLevel '{$level}'");
        }
        $oldLevel = $this->currentLevel;
        $this->currentLevel = $level;

        return $oldLevel;
    }

    /**
     * @return array (LogLevel::const)
     */
    public static function getLevels(): array
    {
        return static::$levels;
    }

    public function backtrace($options = DEBUG_BACKTRACE_PROVIDE_OBJECT, $limit = 0): void
    {
        $trace = debug_backtrace($options, $limit);

        $this->log(LogLevel::ERROR, "---------");
        $this->log(LogLevel::ERROR, "BACKTRACE");
        $this->log(LogLevel::ERROR, "---------");
        foreach ($trace as $traceline) {
            foreach ($traceline as $key => $value) {
                if (!is_scalar($value)) {
                    $value = "<" . gettype($value) . ">";
                } elseif (is_bool($value)) {
                    $value = ($value) ? "<true>" : "<false>";
                }
                $this->log(LogLevel::ERROR, "$key: $value");
            }
            $this->log(LogLevel::ERROR, "---------");
        }
    }

}