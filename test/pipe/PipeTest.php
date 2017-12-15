<?php
/**
 * Created by PhpStorm.
 * User: lrushing
 * Date: 12/15/17
 * Time: 12:36 AM
 */

namespace Spine;


class PipeTest extends \PHPUnit\Framework\TestCase
{

    public function testAddAndExecute()
    {
        $pipe = new Pipe();
        $filter = new TestFilter();

        $pipe->add($filter);

        $result = $pipe->execute();

        $this->assertTrue($filter->executed);
        $this->assertTrue($result);
    }


}



class TestFilter implements Filter {

    public $executed = false;
    /**
     * @return bool
     */
    public function execute()
    {
        $this->executed = true;

        return $this->executed;
    }
}