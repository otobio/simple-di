<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Otobio\Exceptions\BindingNotFoundException;

require_once 'vendor/autoload.php';
require_once 'Basic.php';

class SimpleDITest extends \PHPUnit\Framework\TestCase {
	protected $container;

	protected function setUp(): void {
		parent::setUp();
		$this->container = new \Otobio\SimpleDI();
	}

	protected function tearDown(): void {
		$this->container = null;
		parent::tearDown();
	}

    public function testBasicWithoutConcrete()
    {
        $this->container->add('basic');
        $this->assertEquals($this->container->get('basic'), 'basic');
    }

    public function testBasicWithCallableConcrete()
    {
        $this->container->add('basic', [
            'concrete' => function () {
                return 'Hello';
            }
        ]);
        $this->assertEquals($this->container->get('basic'), 'Hello');
    }

    public function testBasicWithReferencedConcrete()
    {
        $this->container->add('basic', [
            'concrete' => function () {
                return 'Hello';
            }
        ])->add('simple', [
            'concrete' => 'basic'
        ]);

        $this->assertEquals($this->container->get('simple'), 'Hello');
    }

    public function testBasicClassWithNoConstructor()
    {
        $this->container->add('basic', [
            'concrete' => NoConstructor::class
        ]);

        $this->assertInstanceOf(NoConstructor::class, $this->container->get('basic'));
    }

    public function testContainerConfigShareBehaviour()
    {
        $this->container->add(InterfaceTest::class, [
            'concrete' => InterfaceTestClass::class,
        ]);

        $this->assertNotSame($this->container->get(InterfaceTest::class), $this->container->get(InterfaceTest::class));

        $this->container->add(InterfaceTest::class, [
            'concrete' => InterfaceTestClass::class,
            'shared' => true
        ]);

        $this->assertSame($this->container->get(InterfaceTest::class), $this->container->get(InterfaceTest::class));
    }

    public function testReflectionClassWithNoConstructor()
    {
        $this->assertInstanceOf(NoConstructor::class, $this->container->get(NoConstructor::class));
    }

	public function testObjectGraphCreation() {
		$a = $this->container->get('A');

		$this->assertInstanceOf('B', $a->b);
		$this->assertInstanceOf('c', $a->b->c);
		$this->assertInstanceOf('D', $a->b->c->d);
		$this->assertInstanceOf('E', $a->b->c->e);
		$this->assertInstanceOf('F', $a->b->c->e->f);
	}

	public function testNotFoundDependency() 
    {
		$this->expectException(BindingNotFoundException::class);
        $this->container->get('GeoThermalEnergyUnderYourHouse');
	}

	public function testUnInstantiableDependency() 
    {
		$this->expectException(BindingNotFoundException::class);
        $this->container->get(InterfaceTest::class);
	}    

}