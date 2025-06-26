<?php
use PHPUnit\Framework\TestCase;
use NuclearEngagement\Core\Container;

class ContainerTest extends TestCase {
    protected function setUp(): void {
        Container::getInstance()->reset();
    }

    public function test_singleton_returns_same_instance(): void {
        $a = Container::getInstance();
        $b = Container::getInstance();
        $this->assertSame($a, $b);
    }

    public function test_register_and_get_service(): void {
        $container = Container::getInstance();
        $obj = new stdClass();
        $container->register('foo', static function () use ($obj) {
            return $obj;
        });
        $this->assertTrue($container->has('foo'));
        $this->assertSame($obj, $container->get('foo'));
    }

    public function test_get_throws_for_unknown_service(): void {
        $this->expectException(\RuntimeException::class);
        Container::getInstance()->get('missing');
    }

    public function test_reset_clears_registered_services(): void {
        $c = Container::getInstance();
        $c->register('foo', static function () {
            return new stdClass();
        });
        $c->get('foo');
        $c->reset();
        $this->assertFalse($c->has('foo'));
        $this->expectException(\RuntimeException::class);
        $c->get('foo');
    }
}
