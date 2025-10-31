<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\TusUploadServerBundle\Exception\TusException;

/**
 * @internal
 */
#[CoversClass(TusException::class)]
final class TusExceptionTest extends AbstractExceptionTestCase
{
    public function testConstructorWithDefaultsSetsDefaultValues(): void
    {
        $exception = new TusException();

        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testConstructorWithMessageSetsMessage(): void
    {
        $message = 'Test exception message';
        $exception = new TusException($message);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
    }

    public function testConstructorWithMessageAndCodeSetsMessageAndCode(): void
    {
        $message = 'Test exception message';
        $code = 404;
        $exception = new TusException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testConstructorWithAllParametersSetsAllValues(): void
    {
        $message = 'Test exception message';
        $code = 500;
        $previous = new \Exception('Previous exception');
        $exception = new TusException($message, $code, $previous);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertEquals($previous, $exception->getPrevious());
    }

    public function testExtendsExceptionIsInstanceOfException(): void
    {
        $exception = new TusException();

        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testInheritanceCanBeCaughtAsException(): void
    {
        $caught = false;

        try {
            throw new TusException('Test message', 400);
        } catch (\Exception $e) {
            $caught = true;
            $this->assertInstanceOf(TusException::class, $e);
            $this->assertEquals('Test message', $e->getMessage());
            $this->assertEquals(400, $e->getCode());
        }

        $this->assertTrue($caught);
    }
}
