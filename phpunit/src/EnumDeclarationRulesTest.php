<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

use PhpParser\Modifiers;
use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

/**
 * @internal
 * @coversNothing
 */
final class EnumDeclarationRulesTest extends PHPUnit\Framework\TestCase
{
    private string $testRoot;

    protected function setUp(): void
    {
        $this->testRoot = sys_get_temp_dir() . '/typephp-enum-rules-' . bin2hex(random_bytes(8));
        mkdir($this->testRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->testRoot)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->testRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->testRoot);
    }

    public function testDuplicateCaseIsRejectedDuringPrepare(): void
    {
        $compiler = $this->compilerFor(<<<'PHP'
<?php
enum Suit { case Hearts; case Hearts; }
function main(): void {}
PHP);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Cannot redefine class constant Suit::Hearts');
        $compiler->prepareFile($this->testRoot . '/program.php');
    }

    /** @dataProvider caseConstantClashProvider */
    public function testCaseAndClassConstantCannotShareAName(string $members): void
    {
        $compiler = $this->compilerFor("<?php\nenum Suit { {$members} }\nfunction main(): void {}\n");

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Cannot redefine class constant Suit::Hearts');
        $compiler->prepareFile($this->testRoot . '/program.php');
    }

    public static function caseConstantClashProvider(): iterable
    {
        yield 'case then constant' => ['case Hearts; public const Hearts = 1;'];
        yield 'constant then case' => ['public const Hearts = 1; case Hearts;'];
    }

    public function testCaseNamesRemainCaseSensitive(): void
    {
        $compiler = $this->compilerFor(<<<'PHP'
<?php
enum Suit { case Hearts; case hearts; }
function main(): void {}
PHP);
        $file = $this->testRoot . '/program.php';
        $compiler->prepareFile($file);
        $compiler->convertFile($file);

        self::assertSame(['Hearts' => null, 'hearts' => null], $compiler->getClassDef('Suit')?->enumCases);
    }

    public function testEnumIsImplicitlyFinal(): void
    {
        $compiler = $this->compilerFor(<<<'PHP'
<?php
enum Suit { case Hearts; }
function main(): void {}
PHP);
        $compiler->prepareFile($this->testRoot . '/program.php');

        $enum = $compiler->getClassDef('Suit');
        self::assertNotNull($enum);
        self::assertNotSame(0, $enum->flags & Modifiers::FINAL);
    }

    public function testClassCannotExtendEnum(): void
    {
        $compiler = $this->compilerFor(<<<'PHP'
<?php
enum Suit { case Hearts; }
class InvalidSuit extends Suit {}
function main(): void {}
PHP);
        $file = $this->testRoot . '/program.php';
        $compiler->prepareFile($file);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('cannot extend final class `Suit`');
        $compiler->convertFile($file);
    }

    private function compilerFor(string $source): CompilerTest
    {
        $file = $this->testRoot . '/program.php';
        file_put_contents($file, $source);

        global $translator;
        $compiler = CompilerTest::create($this->testRoot);
        $translator = $compiler;
        $compiler->addFiles([$file]);
        return $compiler;
    }
}
