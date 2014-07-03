<?php

namespace Spine\Log;

/**
 * Class LogLevelTest
 *
 * @package Spine\Log
 */
class LogLevelTest extends \PHPUnit_Framework_TestCase
{

    public function testGetName()
    {
        $name = LogLevel::getName(LogLevel::DEBUG);
        $this->assertEquals('DEBUG', $name);
    }
}
