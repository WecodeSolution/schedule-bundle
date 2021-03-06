<?php

namespace Zenstruck\ScheduleBundle\Tests\Schedule\Task\Runner;

use PHPUnit\Framework\TestCase;
use Zenstruck\ScheduleBundle\Schedule\Task\ProcessTask;
use Zenstruck\ScheduleBundle\Schedule\Task\Runner\ProcessTaskRunner;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class ProcessTaskRunnerTest extends TestCase
{
    /**
     * @test
     */
    public function can_create_successful_result()
    {
        $result = (new ProcessTaskRunner())(new ProcessTask('$(which php) -v'));

        $this->assertTrue($result->isSuccessful());
        $this->assertStringContainsString('PHP', $result->getOutput());
        $this->assertStringContainsString(PHP_VERSION, $result->getOutput());
    }

    /**
     * @test
     */
    public function can_create_failed_result()
    {
        $result = (new ProcessTaskRunner())(new ProcessTask('sdfsdfsdf'));

        $this->assertTrue($result->isFailure());
        $this->assertSame('Exit 127: Command not found', $result->getDescription());
        $this->assertSame("sh: 1: sdfsdfsdf: not found\n", $result->getOutput());
    }
}
