<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\TusUploadServerBundle\Command\TusCleanupCommand;
use Tourze\TusUploadServerBundle\Service\TusUploadService;
use Tourze\TusUploadServerBundle\Tests\BaseIntegrationTest;

class TusCleanupCommandTest extends BaseIntegrationTest
{
    private TusCleanupCommand $command;
    private TusUploadService $tusUploadService;

    public function test_execute_withNoExpiredUploads_displaysNoUploadsMessage(): void
    {
        $commandTester = new CommandTester($this->command);

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Starting TUS upload cleanup...', $output);
        $this->assertStringContainsString('No expired uploads found.', $output);
    }

    public function test_execute_withExpiredUploads_cleansUpExpiredUploads(): void
    {
        $expiredUpload1 = $this->tusUploadService->createUpload('expired1.txt', 'text/plain', 1024);
        $expiredUpload1->setExpiredTime(new \DateTime('-1 day'));
        $this->entityManager->persist($expiredUpload1);

        $expiredUpload2 = $this->tusUploadService->createUpload('expired2.txt', 'text/plain', 1024);
        $expiredUpload2->setExpiredTime(new \DateTime('-2 days'));
        $this->entityManager->persist($expiredUpload2);

        $validUpload = $this->tusUploadService->createUpload('valid.txt', 'text/plain', 1024);
        $validUpload->setExpiredTime(new \DateTime('+1 day'));
        $this->entityManager->persist($validUpload);

        $this->entityManager->flush();

        $commandTester = new CommandTester($this->command);

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Starting TUS upload cleanup...', $output);
        $this->assertStringContainsString('Cleaned up 2 expired uploads.', $output);
    }

    public function test_execute_withMixedUploads_onlyCleansExpiredOnes(): void
    {
        $expiredUpload = $this->tusUploadService->createUpload('expired.txt', 'text/plain', 1024);
        $expiredUpload->setExpiredTime(new \DateTime('-1 day'));
        $this->entityManager->persist($expiredUpload);

        $validUpload1 = $this->tusUploadService->createUpload('valid1.txt', 'text/plain', 1024);
        $validUpload1->setExpiredTime(new \DateTime('+1 day'));
        $this->entityManager->persist($validUpload1);

        $validUpload2 = $this->tusUploadService->createUpload('valid2.txt', 'text/plain', 1024);
        $validUpload2->setExpiredTime(new \DateTime('+2 days'));
        $this->entityManager->persist($validUpload2);

        $this->entityManager->flush();

        $commandTester = new CommandTester($this->command);

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Cleaned up 1 expired uploads.', $output);
    }

    public function test_command_hasCorrectName(): void
    {
        $this->assertEquals('tus:cleanup', $this->command->getName());
    }

    public function test_command_hasCorrectDescription(): void
    {
        $this->assertEquals('Clean up expired TUS uploads', $this->command->getDescription());
    }

    public function test_execute_returnsSuccessExitCode(): void
    {
        $commandTester = new CommandTester($this->command);

        $exitCode = $commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
    }

    public function test_execute_withVerboseOutput_displaysDetailedInformation(): void
    {
        $expiredUpload = $this->tusUploadService->createUpload('expired.txt', 'text/plain', 1024);
        $expiredUpload->setExpiredTime(new \DateTime('-1 day'));
        $this->entityManager->persist($expiredUpload);
        $this->entityManager->flush();

        $commandTester = new CommandTester($this->command);

        $exitCode = $commandTester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Starting TUS upload cleanup...', $output);
        $this->assertStringContainsString('Cleaned up 1 expired uploads.', $output);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = $this->container->get(TusCleanupCommand::class);
        $this->tusUploadService = $this->container->get(TusUploadService::class);
    }
}