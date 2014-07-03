<?php

namespace Spine\Log;

/**
 * Describes log levels
 *
 * @see http://tools.ietf.org/html/rfc5424
 */
class LogLevel
{
    const EMERGENCY = 0;
    const ALERT     = 1;
    const CRITICAL  = 2;
    const ERROR     = 3;
    const WARNING   = 4;
    const NOTICE    = 5;
    const INFO      = 6;
    const DEBUG     = 7;

    /**
     * @var Array of constant Names
     */
    private static $constants;

    /**
     * Convenience function to get the level name.
     *
     * @param int $level
     *
     * @return String of the Level name
     */
    public static function getName($level)
    {
        if (is_null(self::$constants)) {
            // build constants
            $reflection      = new \ReflectionClass(get_called_class());
            self::$constants = array_flip($reflection->getConstants());
        }
        return self::$constants[$level];
    }
}