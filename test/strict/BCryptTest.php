<?php
/**
 * Created by PhpStorm.
 * User: lrushing
 * Date: 12/15/17
 * Time: 12:48 AM
 */

namespace Spine;


class BCryptTest extends \PHPUnit\Framework\TestCase
{

    public function test__construct_throwsException()
    {
        $this->expectException('\\InvalidArgumentException');
        $obj = new BCrypt(1);


    }

    public function testHash()
    {
        $obj = new BCrypt(4);
        $hash = $obj->hash('input');

        $this->assertStringStartsWith('$2a$04$', $hash);
    }

    public function testVerify()
    {
        $obj = new BCrypt(4);
        $hash = $obj->hash('input');
        $result = $obj->verify('input', $hash);
        $this->assertTrue($result);

    }

    public function testVerify_Bad()
    {
        $obj = new BCrypt(4);
        $hash = $obj->hash('input');
        $result = $obj->verify('input2', $hash);
        $this->assertFalse($result);

    }
}
