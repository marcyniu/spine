<?php
/**
 * Created by PhpStorm.
 * User: lrushing
 * Date: 2/28/17
 * Time: 1:56 PM
 */

namespace Spine\Web;


use Throwable;

class HtmlExceptionPrinter extends HttpExceptionPrinter
{
    private $errorTemplateDirectory;

    public function __construct(string $errorTemplateDirectory = '')
    {
        if (!empty($errorTemplateDirectory)) {
            if (!is_dir($errorTemplateDirectory)) {
                throw new \InvalidArgumentException("Could not find directory '$errorTemplateDirectory'");
            }

            $this->errorTemplateDirectory = $errorTemplateDirectory;
        }

    }

    public function printThrowable(\Throwable $throwable)
    {
        if (!$this->isHtmlRequest()) {
            return;
        }
        $this->sendHeader();

        if (!$this->iniGetDisplayErrors()) {
            $this->displaySafeError($throwable);
        }
    }

    private function isHtmlRequest()
    {
        if (isset($_SERVER['HTTP_ACCEPT']) && stristr($_SERVER['HTTP_ACCEPT'], 'text/html')) {
            return true;
        }
        return false;
    }

    private function sendHeader()
    {
        if (headers_sent() === false) {
            header("Content-Type: text/html", true);
        }
    }

    private function displaySafeError(Throwable $throwable)
    {
        $fileName = $this->errorTemplateDirectory . DIRECTORY_SEPARATOR . $throwable->getCode() . '.php';
        if (is_file($fileName)) {
            require_once $fileName;
        } else {
            echo "500 - Server Error";
        }
    }
}