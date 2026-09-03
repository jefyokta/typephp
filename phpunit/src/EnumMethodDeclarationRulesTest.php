<?php
/**
 * This file is part of TypePHP(AOT).
 *
 * @link     https://www.swoole.com/aot/
 * @contact  service@swoole.com
 */

use TypePhp\CompilerTest;
use TypePhp\Exception\TestError;

/**
 * Enum method restrictions are declaration rules. They must fail in the
 * TypePHP front end, including methods injected by Trait composition, rather
 * than reaching Zend class registration during module startup.
 * @internal
 * @coversNothing
 */
final class EnumMethodDeclarationRulesTest extends PHPUnit\Framework\TestCase
{
    private string $testRoot;

    protected function setUp(): void
    {
        $this->testRoot = sys_get_temp_dir() . '/typephp-enum-method-' . bin2hex(random_bytes(8));
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
            if ($entry->isDir()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($this->testRoot);
    }

    /**
     * @dataProvider forbiddenMagicMethodProvider
     */
    public function testForbiddenMagicMethodFailsDuringPrepare(string $method): void
    {
        $source = <<<PHP
<?php
enum Suit
{
    case Hearts;
    public function {$method}() {}
}

function main(): void {}
PHP;

        [$compiler, $file] = $this->compilerFor($source);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage("Enum `Suit` cannot include magic method `{$method}`");
        $compiler->prepareFile($file);
    }

    public static function forbiddenMagicMethodProvider(): iterable
    {
        foreach ([
            '__construct',
            '__destruct',
            '__clone',
            '__get',
            '__set',
            '__unset',
            '__isset',
            '__sleep',
            '__wakeup',
            '__set_state',
            '__serialize',
            '__unserialize',
            '__ToString',
            '__debugInfo',
        ] as $method) {
            yield $method => [$method];
        }
    }

    public function testTraitInjectedForbiddenMethodFailsDuringComposition(): void
    {
        $source = <<<'PHP'
<?php
trait Builder
{
    public function __construct() {}
}

enum Suit
{
    use Builder;
    case Hearts;
}

function main(): void {}
PHP;

        [$compiler, $file] = $this->compilerFor($source);
        $compiler->prepareFile($file);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Enum `Suit` cannot include magic method `__construct`');
        $compiler->composeTraitDeclarations([$file]);
    }

    public function testTraitAliasToForbiddenMethodFailsDuringComposition(): void
    {
        $source = <<<'PHP'
<?php
trait Builder
{
    public function cleanup(): void {}
}

enum Suit
{
    use Builder { cleanup as __destruct; }
    case Hearts;
}

function main(): void {}
PHP;

        [$compiler, $file] = $this->compilerFor($source);
        $compiler->prepareFile($file);

        $this->expectException(TestError::class);
        $this->expectExceptionMessage('Enum `Suit` cannot include magic method `__destruct`');
        $compiler->composeTraitDeclarations([$file]);
    }

    public function testCallCallStaticAndInvokeRemainAllowed(): void
    {
        $source = <<<'PHP'
<?php
enum Suit
{
    case Hearts;

    public function __call(string $name, array $arguments): mixed
    {
        return null;
    }

    public static function __callStatic(string $name, array $arguments): mixed
    {
        return null;
    }

    public function __invoke(): string
    {
        return 'Hearts';
    }
}

function main(): void {}
PHP;

        [$compiler, $file] = $this->compilerFor($source);
        $compiler->prepareFile($file);
        $compiler->convertFile($file);

        self::assertFileExists($compiler->getCppFile($file));
    }

    /** @return array{CompilerTest, string} */
    private function compilerFor(string $source): array
    {
        $file = $this->testRoot . '/program.php';
        file_put_contents($file, $source);

        global $translator;
        $compiler = CompilerTest::create($this->testRoot);
        $translator = $compiler;
        $compiler->addFiles([$file]);

        return [$compiler, $file];
    }
}
