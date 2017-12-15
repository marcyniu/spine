<?php
/**
 * A phan runner to add a space in front of the output
 */

$baseDir =  dirname(__DIR__) ;
$phanBin = $baseDir. '/vendor/bin/phan';

passthru("cd $baseDir && $phanBin --color > /tmp/phan.out");

passthru("sed -e 's/^/ /' /tmp/phan.out");