<?php

namespace Zenstruck\ScheduleBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\ScheduleBundle\Command\ScheduleListCommand;
use Zenstruck\ScheduleBundle\Schedule;
use Zenstruck\ScheduleBundle\Schedule\Extension\ExtensionHandlerRegistry;
use Zenstruck\ScheduleBundle\Schedule\ScheduleBuilder;
use Zenstruck\ScheduleBundle\Tests\Fixture\MockScheduleBuilder;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class ScheduleListCommandTest extends TestCase
{
    /**
     * @test
     */
    public function no_tasks_defined()
    {
        $runner = (new MockScheduleBuilder())->getRunner();
        $commandTester = new CommandTester(new ScheduleListCommand($runner, new ExtensionHandlerRegistry([])));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No scheduled tasks configured.');

        $commandTester->execute([]);
    }

    /**
     * @test
     */
    public function lists_configured_tasks_and_issues()
    {
        $runner = (new MockScheduleBuilder())
            ->addBuilder(new class() implements ScheduleBuilder {
                public function buildSchedule(Schedule $schedule): void
                {
                    $schedule->emailOnFailure('admin@example.com');
                    $schedule->addCommand('my:command')
                        ->mondays()
                        ->at('1:30')
                        ->emailOnFailure('admin@example.com')
                        ->pingOnFailure('https://example.com/my-command-failed')
                    ;
                }
            })
            ->getRunner()
        ;
        $command = new ScheduleListCommand($runner, new ExtensionHandlerRegistry([]));
        $command->setHelperSet(new HelperSet([new FormatterHelper()]));
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);
        $output = $this->normalizeOutput($commandTester);

        $this->assertStringContainsString('[!] CommandTask my:command 2 Every Monday at 1:30am (30 1 * * 1)', $output);
        $this->assertStringContainsString('[WARNING] 3 task issues:', $output);
        $this->assertStringContainsString('[ERROR] No task runner registered to handle "Zenstruck\ScheduleBundle\Schedule\Task\CommandTask".', $output);
        $this->assertStringContainsString('[ERROR] To use the email extension you must configure a mailer (config path: "zenstruck_schedule.email_handler").', $output);
        $this->assertStringContainsString('[ERROR] No extension handler registered for "Zenstruck\ScheduleBundle\Schedule\Extension\PingExtension: On Task', $output);
        $this->assertStringContainsString('Failure, ping "https://example.com/my-command-failed"".', $output);
        $this->assertStringContainsString('1 Schedule Extension:', $output);
        $this->assertStringContainsString('On Schedule Failure, email output to "admin@example.com"', $output);
        $this->assertStringContainsString('[WARNING] 1 issue with schedule:', $output);
    }

    /**
     * @test
     */
    public function renders_exception_stack_trace_if_verbose()
    {
        $runner = (new MockScheduleBuilder())
            ->addBuilder(new class() implements ScheduleBuilder {
                public function buildSchedule(Schedule $schedule): void
                {
                    $schedule->addCommand('my:command')
                        ->mondays()
                        ->at('1:30')
                    ;
                }
            })
            ->getRunner()
        ;
        $command = new ScheduleListCommand($runner, new ExtensionHandlerRegistry([]));
        $command->setHelperSet(new HelperSet([new FormatterHelper()]));
        $command->setApplication(new Application());
        $commandTester = new CommandTester($command);

        $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $output = $this->normalizeOutput($commandTester);

        $this->assertStringContainsString('[WARNING] 1 task issue:', $output);
        $this->assertStringContainsString('In ScheduleRunner.php line', $output);
        $this->assertStringContainsString('[LogicException]', $output);
        $this->assertStringContainsString('No task runner registered to handle', $output);
        $this->assertStringContainsString('Exception trace:', $output);
    }

    /**
     * @test
     */
    public function lists_configured_tasks_and_issues_in_detail()
    {
        $runner = (new MockScheduleBuilder())
            ->addBuilder(new class() implements ScheduleBuilder {
                public function buildSchedule(Schedule $schedule): void
                {
                    $schedule->emailOnFailure('admin@example.com');
                    $schedule->addCommand('my:command')
                        ->arguments('arg1', '--option1')
                        ->mondays()
                        ->at('1:30')
                        ->emailOnFailure('admin@example.com')
                        ->pingOnFailure('https://example.com/my-command-failed')
                    ;
                }
            })
            ->getRunner()
        ;
        $command = new ScheduleListCommand($runner, new ExtensionHandlerRegistry([]));
        $command->setHelperSet(new HelperSet([new FormatterHelper()]));
        $command->setApplication(new Application());
        $commandTester = new CommandTester($command);

        $commandTester->execute(['--detail' => null]);
        $output = $this->normalizeOutput($commandTester);

        $this->assertStringContainsString('1 Scheduled Tasks Configured', $output);
        $this->assertStringContainsString('(1/1) CommandTask: my:command', $output);
        $this->assertStringContainsString('Every Monday at 1:30am (30 1 * * 1)', $output);
        $this->assertStringContainsString('Mon,', $output);
        $this->assertStringContainsString('Arguments: arg1 --option1', $output);
        $this->assertStringContainsString('2 Task Extensions:', $output);
        $this->assertStringContainsString('On Task Failure, email output to "admin@example.com"', $output);
        $this->assertStringContainsString('On Task Failure, ping "https://example.com/my-command-failed"', $output);
        $this->assertStringContainsString('[WARNING] 3 issues with this task:', $output);
        $this->assertStringContainsString('[ERROR] No task runner registered to handle "Zenstruck\ScheduleBundle\Schedule\Task\CommandTask".', $output);
        $this->assertStringContainsString('[ERROR] To use the email extension you must configure a mailer (config path: "zenstruck_schedule.email_handler").', $output);
        $this->assertStringContainsString('[ERROR] No extension handler registered for "Zenstruck\ScheduleBundle\Schedule\Extension\PingExtension: On Task', $output);
        $this->assertStringContainsString('Failure, ping "https://example.com/my-command-failed"".', $output);
        $this->assertStringContainsString('1 Schedule Extension:', $output);
        $this->assertStringContainsString('On Schedule Failure, email output to "admin@example.com"', $output);
        $this->assertStringContainsString('[WARNING] 1 issue with schedule:', $output);
    }

    private function normalizeOutput(CommandTester $tester): string
    {
        return \preg_replace('/\s+/', ' ', \str_replace("\n", '', $tester->getDisplay(true)));
    }
}