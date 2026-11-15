<?php

namespace AppSupport\Tests\Config;

use AppSupport\Config\Repository;
use AppSupport\Support\Facades\Config;
use AppSupport\Tests\AppTestCase;

final class RepositoryTest extends AppTestCase
{
    public function testSetAndGetWithDots(): void
    {
        $repository = new Repository();

        $repository->set('app.info.name', 'Test');
        $repository->set('app.info.version', '1.0');
        $repository->set('level', 3);

        $this->assertSame('Test', $repository->get('app.info.name'));
        $this->assertSame(['name' => 'Test', 'version' => '1.0'], $repository->get('app.info'));
        $this->assertSame(3, $repository->get('level'));
    }

    public function testDefaultsOfWhatIsNotThere(): void
    {
        $repository = new Repository(['a' => ['b' => 1]]);

        $this->assertNull($repository->get('a.c'));
        $this->assertSame('x', $repository->get('a.c', 'x'));
        $this->assertSame('x', $repository->get('z.y.w', 'x'));
        $this->assertFalse($repository->isset('a.c'));
        $this->assertTrue($repository->isset('a.b'));
    }

    public function testFalseAndEmptyValuesAreSet(): void
    {
        $repository = new Repository();
        $repository->set('flag', false);
        $repository->set('empty', '');
        $repository->set('zero', '0');

        $this->assertFalse($repository->get('flag', true));
        $this->assertSame('', $repository->get('empty', 'x'));
        $this->assertSame('0', $repository->get('zero', 'x'));
        $this->assertTrue($repository->isset('flag'));
    }

    public function testNullIsNotSet(): void
    {
        $repository = new Repository();
        $repository->set('nothing', null);

        $this->assertFalse($repository->isset('nothing'));
        $this->assertSame('x', $repository->get('nothing', 'x'));
    }

    public function testUnset(): void
    {
        $repository = new Repository(['a' => ['b' => 1, 'c' => 2]]);

        $repository->unset('a.b');
        $repository->unset('a.missing');

        $this->assertFalse($repository->isset('a.b'));
        $this->assertSame(['c' => 2], $repository->get('a'));
    }

    public function testSetReplacesValues(): void
    {
        $repository = new Repository(['a' => ['b' => 1]]);

        $repository->set('a.b', 2);
        $repository->set('a', 'text');

        $this->assertSame('text', $repository->get('a'));
    }

    public function testSettingAnEmptyKeyAppends(): void
    {
        $repository = new Repository();

        $repository->set('', 'first');
        $repository->set('', 'second');

        $this->assertSame('first', $repository->get('0'));
        $this->assertSame('second', $repository->get('1'));
    }

    public function testArrayAccess(): void
    {
        $repository = new Repository();

        $repository['a.b'] = 5;

        $this->assertSame(5, $repository['a.b']);
        $this->assertTrue(isset($repository['a.b']));
        unset($repository['a.b']);
        $this->assertFalse(isset($repository['a.b']));
    }

    public function testReferenceGivesTheSubtree(): void
    {
        $repository = new Repository(['app' => ['args' => ['cmd' => ['n' => '5']]]]);

        $reference = $repository->reference('app.args.cmd');

        $this->assertSame('5', $reference->get('n'));
    }

    public function testReferenceCreatesTheBranch(): void
    {
        $repository = new Repository();

        $reference = $repository->reference('app.args.cmd');

        $this->assertNull($reference->get('n'));
        $this->assertTrue($repository->isset('app.args.cmd'));
    }

    public function testReferenceOfAValueThatIsNotAnArray(): void
    {
        $repository = new Repository(['a' => 'text']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid path for reference');

        $repository->reference('a');
    }

    public function testLoadReadsAConfigurationFile(): void
    {
        $this->boot(['commands' => []]);
        file_put_contents($this->base . '/config/extra.php', '<?php return ["colors" => ["red", "green"], "size" => 3];');

        $loaded = Config::load('extra');
        $aliased = Config::load('extra', 'other.place');

        $this->assertSame(['colors' => ['red', 'green'], 'size' => 3], $loaded);
        $this->assertSame($loaded, $aliased);
        $this->assertSame(3, Config::get('extra.size'));
        $this->assertSame(['red', 'green'], Config::get('other.place.colors'));
    }

    public function testLoadOfAMissingFile(): void
    {
        $this->boot(['commands' => []]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Configuration file '{$this->base}/config/missing.php' not found");

        Config::load('missing');
    }
}
