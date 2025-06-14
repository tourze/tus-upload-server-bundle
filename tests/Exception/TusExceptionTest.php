<?php

declare(strict_types=1);

namespace Tourze\TusUploadServerBundle\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\TusUploadServerBundle\Exception\TusException;

class TusExceptionTest extends TestCase
{
    public function test_constructor_withDefaults_setsDefaultValues(): void
    {
        $exception = new TusException();

        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function test_constructor_withMessage_setsMessage(): void
    {
        $message = 'Test exception message';
        $exception = new TusException($message);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
    }

    public function test_constructor_withMessageAndCode_setsMessageAndCode(): void
    {
        $message = 'Test exception message';
        $code = 404;
        $exception = new TusException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function test_constructor_withAllParameters_setsAllValues(): void
    {
        $message = 'Test exception message';
        $code = 500;
        $previous = new \Exception('Previous exception');
        $exception = new TusException($message, $code, $previous);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
        $this->assertEquals($previous, $exception->getPrevious());
    }

    public function test_extendsException_isInstanceOfException(): void
    {
        $exception = new TusException();

        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function test_inheritance_canBeCaughtAsException(): void
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