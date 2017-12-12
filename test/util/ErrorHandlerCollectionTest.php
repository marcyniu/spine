<?php
/**
 * @author Lance Rushing <lance@lancerushing.com>
 * @since  2016-05-12
 */

namespace Spine;

require_once __DIR__ . '/../../src/util/ErrorHandlerCollection.php';

class ErrorHandlerCollectionTest extends \PHPUnit_Framework_TestCase
{

    public function testAddErrorHandler() {

        $handlerWasCalled = false;
        $callable = function()  use (&$handlerWasCalled) {
            $handlerWasCalled = true;
        };

        $collection = new ErrorHandlerCollection();
        $collection->register();
        $collection->addErrorHandler($callable);

        trigger_error('just at test');

        $this->assertTrue($handlerWasCalled);
    }

    public function testAddExceptionHandler() {

        $handlerWasCalled = false;
        $callable         = function()  use (&$handlerWasCalled) {
            $handlerWasCalled = true;
        };

        $collection = new ErrorHandlerCollection();
        $collection->register();
        $collection->addExceptionHandler($callable);

        $collection->handleException(new \Exception('test'));

        $this->assertTrue($handlerWasCalled);
    }

}
