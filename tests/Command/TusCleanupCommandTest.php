<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;
use Tourze\TusUploadServerBundle\Command\TusCleanupCommand;
use Tourze\TusUploadServerBundle\Service\TusUploadService;

/**
 * @internal
 */
#[CoversClass(TusCleanupCommand::class)]
#[RunTestsInSeparateProcesses]
final class TusCleanupCommandTest extends AbstractCommandTestCase
{
    private TusCleanupCommand $command;

    private TusUploadService $tusUploadService;

    protected function getCommandClass(): string
    {
        return TusCleanupCommand::class;
    }

    protected function getCommandTester(): CommandTester
    {
        $command = self::getService(TusCleanupCommand::class);
        $this->assertInstanceOf(TusCleanupCommand::class, $command);

        return new CommandTester($command);
    }

    public function testExecuteWithNoExpiredUploadsDisplaysNoUploadsMessage(): void
    {
        $commandTester = new CommandTester($this->command);

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Starting TUS upload cleanup...', $output);
        $this->assertStringContainsString('No expired uploads found.', $output);
    }

    public function testExecuteWithExpiredUploadsCleansUpExpiredUploads(): void
    {
        $expiredUpload1 = $this->tusUploadService->createUpload('expired1.txt', 'text/plain', 1024);
        $expiredUpload1->setExpiredTime(new \DateTimeImmutable('-1 day'));
        self::getEntityManager()->persist($expiredUpload1);

        $expiredUpload2 = $this->tusUploadService->createUpload('expired2.txt', 'text/plain', 1024);
        $expiredUpload2->setExpiredTime(new \DateTimeImmutable('-2 days'));
        self::getEntityManager()->persist($expiredUpload2);

        $validUpload = $this->tusUploadService->createUpload('valid.txt', 'text/plain', 1024);
        $validUpload->setExpiredTime(new \DateTimeImmutable('+1 day'));
        self::getEntityManager()->persist($validUpload);

        self::getEntityManager()->flush();

        $commandTester = new CommandTester($this->command);

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Starting TUS upload cleanup...', $output);
        $this->assertStringContainsString('Cleaned up 2 expired uploads.', $output);
    }

    public function testExecuteWithMixedUploadsOnlyCleansExpiredOnes(): void
    {
        $expiredUpload = $this->tusUploadService->createUpload('expired.txt', 'text/plain', 1024);
        $expiredUpload->setExpiredTime(new \DateTimeImmutable('-1 day'));
        self::getEntityManager()->persist($expiredUpload);

        $validUpload1 = $this->tusUploadService->createUpload('valid1.txt', 'text/plain', 1024);
        $validUpload1->setExpiredTime(new \DateTimeImmutable('+1 day'));
        self::getEntityManager()->persist($validUpload1);

        $validUpload2 = $this->tusUploadService->createUpload('valid2.txt', 'text/plain', 1024);
        $validUpload2->setExpiredTime(new \DateTimeImmutable('+2 days'));
        self::getEntityManager()->persist($validUpload2);

        self::getEntityManager()->flush();

        $commandTester = new CommandTester($this->command);

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Cleaned up 1 expired uploads.', $output);
    }

    public function testCommandHasCorrectName(): void
    {
        $this->assertEquals('tus:cleanup', $this->command->getName());
    }

    public function testCommandHasCorrectDescription(): void
    {
        $this->assertEquals('Clean up expired TUS uploads', $this->command->getDescription());
    }

    public function testExecuteReturnsSuccessExitCode(): void
    {
        $commandTester = new CommandTester($this->command);

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
    }

    public function testExecuteWithVerboseOutputDisplaysDetailedInformation(): void
    {
        $expiredUpload = $this->tusUploadService->createUpload('expired.txt', 'text/plain', 1024);
        $expiredUpload->setExpiredTime(new \DateTimeImmutable('-1 day'));
        self::getEntityManager()->persist($expiredUpload);
        self::getEntityManager()->flush();

        $commandTester = new CommandTester($this->command);

        $exitCode = $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Starting TUS upload cleanup...', $output);
        $this->assertStringContainsString('Cleaned up 1 expired uploads.', $output);
    }

    protected function onSetUp(): void
    {        /** @var TusCleanupCommand $command */
        $command = self::getContainer()->get(TusCleanupCommand::class);
        $this->command = $command;

        /** @var TusUploadService $tusUploadService */
        $tusUploadService = self::getContainer()->get(TusUploadService::class);
        $this->tusUploadService = $tusUploadService;

        // Clean database (commented out to allow schema creation first)
        // $connection = self::getEntityManager()->getConnection();
        // $connection->executeStatement('DELETE FROM tus_uploads');
    }

    protected function onTearDown(): void
    {
        // Clean database (commented out to allow schema creation first)
        // $connection = self::getEntityManager()->getConnection();
        // $connection->executeStatement('DELETE FROM tus_uploads');
    }
}
