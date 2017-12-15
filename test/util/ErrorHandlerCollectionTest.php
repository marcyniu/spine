<?php
/**
 * @author Lance Rushing <lance@lancerushing.com>
 * @since  2016-05-12
 */

namespace Spine;

use PHPUnit\Framework\TestCase;

class ErrorHandlerCollectionTest extends TestCase
{

    public function testAddErrorHandler()
    {

        $handlerWasCalled = false;
        $callable = function () use (&$handlerWasCalled) {
            $handlerWasCalled = true;
        };

        $collection = new ErrorHandlerCollection();
        $collection->register();
        $collection->addErrorHandler($callable);

        trigger_error('just at test');

        $this->assertTrue($handlerWasCalled);
    }

    public function testAddExceptionHandler()
    {

        $handlerWasCalled = false;
        $callable = function () use (&$handlerWasCalled) {
            $handlerWasCalled = true;
        };

        $collection = new ErrorHandlerCollection();

        // remove the default printer
        $prop = new \ReflectionProperty($collection, 'exceptionHandlers');
        $prop->setAccessible(true);
        $prop->setValue($collection, []);

        $collection->addExceptionHandler($callable);

        $collection->handleException(new \Exception('test'));

        $this->assertTrue($handlerWasCalled);
    }

}
