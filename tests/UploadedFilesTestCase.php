<?php

declare(strict_types=1);

namespace ebln\Symfony1\Psr7Bridge\Tests;

use ebln\Symfony1\Psr7Bridge\ConversionFailed;
use ebln\Symfony1\Psr7Bridge\Internal\UploadedFiles;
use ebln\Symfony1\Psr7Bridge\Tests\Support\Factories;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;

abstract class UploadedFilesTestCase extends TestCase
{
    private string $file = '';

    abstract protected function factories(): Factories;

    protected function setUp(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'upload');
        self::assertIsString($file);
        file_put_contents($file, 'content');
        $this->file = $file;
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testConvertsFlatNestedAndDeepTrees(): void
    {
        $leaf = $this->leaf();

        $tree = $this->convert([
            'one' => $leaf,
            'a' => ['b' => $leaf],
            'l1' => ['l2' => ['l3' => ['l4' => ['l5' => $leaf]]]],
        ]);

        $one = $this->fileAt($tree, 'one');
        $this->fileAt($tree, 'a', 'b');
        $this->fileAt($tree, 'l1', 'l2', 'l3', 'l4', 'l5');
        self::assertSame('content', (string) $one->getStream());
        self::assertSame(7, $one->getSize());
        self::assertSame(\UPLOAD_ERR_OK, $one->getError());
        self::assertSame('a.txt', $one->getClientFilename());
        self::assertSame('text/plain', $one->getClientMediaType());
    }

    public function testFailedUploadWithoutTemporaryFile(): void
    {
        $file = $this->fileAt($this->convert(['f' => ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => '4', 'size' => 0]]), 'f');

        self::assertSame(\UPLOAD_ERR_NO_FILE, $file->getError());
        self::assertSame(0, $file->getSize());
    }

    public function testFailedUploadWithoutTmpNameKey(): void
    {
        $file = $this->fileAt($this->convert(['f' => ['name' => '', 'type' => '', 'error' => 4, 'size' => 0]]), 'f');

        self::assertSame(\UPLOAD_ERR_NO_FILE, $file->getError());
    }

    public function testFailedUploadWithUnreadablePathDoesNotOpenIt(): void
    {
        $file = $this->fileAt($this->convert(['f' => ['tmp_name' => '/nonexistent/upload', 'error' => 4, 'size' => 0]]), 'f');

        self::assertSame(\UPLOAD_ERR_NO_FILE, $file->getError());
    }

    public function testErrorAndSizeGivenAsFloatAreCastToInt(): void
    {
        $file = $this->fileAt($this->convert(['f' => ['tmp_name' => '', 'error' => 4.0, 'size' => 7.0]]), 'f');

        self::assertSame(\UPLOAD_ERR_NO_FILE, $file->getError());
        self::assertSame(7, $file->getSize());
    }

    public function testNameAndTypeCanBeMissing(): void
    {
        $file = $this->fileAt($this->convert(['f' => ['tmp_name' => $this->file, 'error' => 0, 'size' => 7]]), 'f');

        self::assertNull($file->getClientFilename());
        self::assertNull($file->getClientMediaType());
    }

    public function testMissingTemporaryFileOfASuccessfulUploadFails(): void
    {
        try {
            $this->convert(['f' => ['tmp_name' => '/nonexistent/upload', 'error' => 0, 'size' => 7]]);
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertNotNull($e->getPrevious());
        }
    }

    public function testErrorDefaultsToOk(): void
    {
        $file = $this->fileAt($this->convert(['f' => ['tmp_name' => $this->file]]), 'f');

        self::assertSame(\UPLOAD_ERR_OK, $file->getError());
        self::assertSame(7, $file->getSize());
        self::assertSame('content', (string) $file->getStream());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function numericSizes(): array
    {
        return [
            'integer' => [3],
            'float' => [3.0],
            'numeric string' => ['3'],
        ];
    }

    /**
     * @param mixed $size an integer, a float or a numeric string
     *
     * @dataProvider numericSizes
     */
    public function testNumericSizeIsConvertedToInt($size): void
    {
        $file = $this->fileAt($this->convert(['f' => ['tmp_name' => $this->file, 'error' => 0, 'size' => $size]]), 'f');

        self::assertSame(3, $file->getSize());
    }

    public function testErrorThatIsNotNumericCountsAsOk(): void
    {
        $file = $this->fileAt($this->convert(['f' => ['tmp_name' => $this->file, 'error' => 'abc']]), 'f');

        self::assertSame(\UPLOAD_ERR_OK, $file->getError());
        self::assertSame('content', (string) $file->getStream());
    }

    public function testSizeThatIsNotNumericIsIgnored(): void
    {
        $file = $this->fileAt($this->convert(['f' => ['tmp_name' => $this->file, 'error' => 0, 'size' => 'abc']]), 'f');

        self::assertSame(7, $file->getSize());
    }

    public function testValueBeforeAFileDoesNotEndTheConversion(): void
    {
        $tree = $this->convert(['a' => 'x', 'f' => $this->leaf()]);

        self::assertSame('x', $tree['a']);
        self::assertInstanceOf(UploadedFileInterface::class, $tree['f']);
    }

    public function testValueThatIsNotAFileIsKept(): void
    {
        self::assertSame(['a' => ['b' => 'x']], $this->convert(['a' => ['b' => 'x']]));
    }

    public function testSuccessfulUploadWithoutTemporaryPathFails(): void
    {
        try {
            $this->convert(['f' => ['name' => 'a.txt', 'error' => 0]]);
            self::fail('ConversionFailed expected');
        } catch (ConversionFailed $e) {
            self::assertNotNull($e->getPrevious());
        }
    }

    public function testEmptyTree(): void
    {
        self::assertSame([], $this->convert([]));
    }

    /**
     * @param array<array-key, mixed> $tree
     */
    private function fileAt(array $tree, string ...$path): UploadedFileInterface
    {
        $node = $tree;
        foreach ($path as $key) {
            self::assertIsArray($node);
            self::assertArrayHasKey($key, $node);
            $node = $node[$key];
        }
        self::assertInstanceOf(UploadedFileInterface::class, $node);

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function leaf(): array
    {
        return ['name' => 'a.txt', 'type' => 'text/plain', 'tmp_name' => $this->file, 'error' => 0, 'size' => '7'];
    }

    /**
     * @param array<array-key, mixed> $files
     *
     * @return array<array-key, mixed>
     */
    private function convert(array $files): array
    {
        $factories = $this->factories();

        return (new UploadedFiles($factories->uploadedFile, $factories->stream))->convert($files);
    }
}
