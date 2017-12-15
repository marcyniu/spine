<?php

/**
 * Bootstrap the tests.  Set up autoloading.
 *
 * @package    Spine
 * @author     Lance Rushing
 * @since      2011-08-03
 *
 */


$baseDir = escapeshellarg(dirname(__DIR__) );
exec("export COMPOSER_DISABLE_XDEBUG_WARN=1; composer dump-autoload -d $baseDir");

//
//set_include_path(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' .
//    PATH_SEPARATOR . get_include_path());

