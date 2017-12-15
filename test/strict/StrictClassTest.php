<?php
/**
 * Created by PhpStorm.
 * User: lrushing
 * Date: 12/15/17
 * Time: 12:42 AM
 */

namespace Spine;


class StrictClassTest extends \PHPUnit\Framework\TestCase
{

    function test__set()
    {
        $this->expectException('Spine\\StrictException');
        $obj = new StrictClassExample;
        $obj->dynamic = true;
    }

    function test__get()
    {
        $this->expectException('Spine\\StrictException');
        $obj = new StrictClassExample;
        $foo = $obj->dynamic;
    }

}

class StrictClassExample extends StrictClass
{

}
