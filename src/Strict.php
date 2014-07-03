<?php

namespace Spine;

/**
 * Uses the __set magic function to prevent properties from being set unless they are defined.
 */
class StrictClass
{
    use StrictTrait;
}

/**
 * Uses the __set magic function to prevent properties from being set unless they are defined.
 */
trait StrictTrait
{

    /**
     * Called when the property is not defined.
     *
     * @param string $name  Name of property.
     * @param mixed  $value Value of property.
     *
     * @throws StrictClassException
     * @return void
     */
    public function __set($name, $value)
    {
        $traceMsg = $this->traceMsg();
        throw new StrictClassException(sprintf(
            "Trying to set unknown property named '%s' to '%s' for class '%s'. %s",
            $name,
            $value,
            get_class($this),
            $traceMsg
        ));
    }

    /**
     * Called when the property is not defined.
     *
     * @param string $name Name of property.
     *
     * @throws StrictClassException
     * @return void
     */
    public function __get($name)
    {
        $traceMsg = $this->traceMsg();
        throw new StrictClassException("Trying to get unknown property named '$name' for class '" . get_class(
                $this
            ) . "'.  $traceMsg");
    }

    /**
     * Provides the calling location to the exception message.
     *
     * @return string
     */
    private function traceMsg()
    {
        $backtrace = debug_backtrace();
        $trace     = $backtrace[1];
        return sprintf(
            "Called from %s (%s)",
            isset($trace['file']) ? $trace['file'] : "internal",
            isset($trace['line']) ? $trace['line'] : "internal"
        );
    }

}
