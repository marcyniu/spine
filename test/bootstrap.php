<?php

/**
 * Bootstrap the tests.  Set up autoloading.
 *
 * @package    UPTRACS
 * @subpackage Library_Tests
 * @author     Lance Rushing
 * @since      2011-08-03
 *
 */
set_include_path(
    dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src' .
    PATH_SEPARATOR . get_include_path()
);

ini_set('short_open_tag', 'on');

/**
 * Setup Autoloading
 */
function setupAutoLoad()
{
    $start = microtime(true);
    echo "Rescanning for autoLoad...";

    $libSrcPath  = dirname(__DIR__) . '/src';
    $vendorPath  = __DIR__ . '/../../../../../../vendors';
    $libTestPath = __DIR__;

    $pathsToScan = array($libSrcPath, $libTestPath, $vendorPath);

    require_once "$vendorPath/vendor/lancerushing/atomic/AutoLoader.php";
    $autoLoader = new \Atomic\AutoLoader($pathsToScan);
    $autoLoader->register();

    echo sprintf("done. (%0.2f seconds)\n", microtime(true) - $start);
}

$libSrcPath = dirname(__DIR__) . '/src';

setupAutoLoad();

