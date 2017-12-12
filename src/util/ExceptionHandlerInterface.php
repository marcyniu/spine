<?php
/**
 * @author Lance Rushing <lance@lancerushing.com>
 * @since  2016-05-13
 */
namespace Spine\Web;



interface ExceptionHandlerInterface
{
    /**
     * @param \Exception|\Throwable $exception
     */
    public function handleException($exception);
}