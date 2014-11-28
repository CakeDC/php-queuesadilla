<?php

namespace josegonzalez\Queuesadilla;

use josegonzalez\Queuesadilla\Engine\NullEngine;
use josegonzalez\Queuesadilla\Job\Base as BaseJob;
use josegonzalez\Queuesadilla\Worker\SequentialWorker;
use josegonzalez\Queuesadilla\TestCase;
use Psr\Log\LoggerInterface;

function fail_method()
{
    return false;
}
function null_method()
{
    return true;
}
function true_method()
{
    return true;
}
class MyJob
{
    public function performTrue()
    {
        return true;
    }
    public function performFail()
    {
        return false;
    }
    public function performNull()
    {
        return null;
    }
    public function performException()
    {
        throw new \Exception("Exception");
    }
    public function perform($job)
    {
        return $job->data('return');
    }
    public function performStatic()
    {
        return true;
    }
}

class SequentialWorkerTest extends TestCase
{
    public function setUp()
    {
        $this->Engine = new NullEngine;
        $this->Worker = new SequentialWorker($this->Engine);
        $this->Item = [
            'class' => ['josegonzalez\Queuesadilla\MyJob', 'perform'],
            'vars' => ['return' => true],
        ];
        $this->ItemFail = [
            'class' => ['josegonzalez\Queuesadilla\MyJob', 'performFail'],
            'vars' => ['return' => true],
        ];
        $this->ItemException = [
            'class' => ['josegonzalez\Queuesadilla\MyJob', 'performException'],
            'vars' => ['return' => true],
        ];
        $this->Job = new BaseJob($this->Item, $this->Engine);
    }

    public function tearDown()
    {
        unset($this->Engine);
        unset($this->Worker);
    }

    /**
     * @covers josegonzalez\Queuesadilla\Worker\Base::__construct
     * @covers josegonzalez\Queuesadilla\Worker\SequentialWorker::__construct
     */
    public function testConstruct()
    {
        $Worker = new SequentialWorker($this->Engine);
        $this->assertInstanceOf('\josegonzalez\Queuesadilla\Worker\Base', $Worker);
        $this->assertInstanceOf('\josegonzalez\Queuesadilla\Worker\SequentialWorker', $Worker);
        $this->assertInstanceOf('\Psr\Log\LoggerInterface', $Worker->logger());
        $this->assertInstanceOf('\Psr\Log\NullLogger', $Worker->logger());
    }

    /**
     * @covers josegonzalez\Queuesadilla\Worker\SequentialWorker::work
     */
    public function testWork()
    {
        $Engine = new NullEngine;
        $Engine->return = false;
        $Worker = new SequentialWorker($Engine);
        $this->assertFalse($Worker->work());

        $Engine = $this->getMock('josegonzalez\Queuesadilla\Engine\NullEngine', ['pop']);
        $Engine->expects($this->at(0))
                ->method('pop')
                ->will($this->returnValue(true));
        $Engine->expects($this->at(1))
                ->method('pop')
                ->will($this->returnValue($this->Item));
        $Engine->expects($this->at(2))
                ->method('pop')
                ->will($this->returnValue($this->ItemFail));
        $Engine->expects($this->at(3))
                ->method('pop')
                ->will($this->returnValue($this->ItemException));
        $Engine->expects($this->at(4))
                ->method('pop')
                ->will($this->returnValue(false));
        $Worker = new SequentialWorker($Engine, null, ['maxIterations' => 5]);
        $this->assertTrue($Worker->work());
        $this->assertEquals([
            'seen' => 5,
            'empty' =>1,
            'exception' => 1,
            'invalid' => 1,
            'success' => 1,
            'failure' => 2,
        ], $Worker->stats());
    }

    /**
     * @covers josegonzalez\Queuesadilla\Worker\SequentialWorker::connect
     */
    public function testConnect()
    {
        $this->assertTrue($this->Worker->connect());

        $Engine = new NullEngine;
        $Engine->return = false;
        $Worker = new SequentialWorker($Engine);
        $this->assertFalse($Worker->connect());
    }

    /**
     * @covers josegonzalez\Queuesadilla\Worker\SequentialWorker::perform
     */
    public function testPerform()
    {
        $this->assertFalse($this->Worker->perform([
            'class' => 'josegonzalez\Queuesadilla\nonexistent_method'
        ], null));
        $this->assertFalse($this->Worker->perform([
            'class' => 'josegonzalez\Queuesadilla\fail_method'
        ], null));
        $this->assertTrue($this->Worker->perform([
            'class' => 'josegonzalez\Queuesadilla\null_method'
        ], null));
        $this->assertTrue($this->Worker->perform([
            'class' => 'josegonzalez\Queuesadilla\true_method'
        ], null));
        $this->assertFalse($this->Worker->perform([
            'class' => ['josegonzalez\Queuesadilla\MyJob', 'performFail']
        ], null));
        $this->assertTrue($this->Worker->perform([
            'class' => ['josegonzalez\Queuesadilla\MyJob', 'performTrue']
        ], null));
        $this->assertTrue($this->Worker->perform([
            'class' => ['josegonzalez\Queuesadilla\MyJob', 'performNull']
        ], null));
        $this->assertTrue($this->Worker->perform([
            'class' => ['josegonzalez\Queuesadilla\MyJob', 'perform']
        ], $this->Job));
    }
}
