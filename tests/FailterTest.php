<?php  declare(strict_types = 1);

use PHPUnit\Framework\TestCase;
use Innermond\Failter as Me;

class FailterTest extends TestCase {

	private $fail;

	public function setUp() {
		$this->fail = new Me\Failter;
	}

  public function invalidArgumentsForOn() {
    return [
      [''],
      ['    '],
    ];
  }

  /**
   * @dataProvider invalidArgumentsForOn
   */
  public function testOnWithInvalidArguments($nil) {
    $this->expectException(\InvalidArgumentException::class);
    $this->fail->on($nil);
  }

  public function testOnWithoutArgument() {
    $this->expectException(\TypeError::class);
    $this->fail->on();
  }

	public function emptyArgumentsForRun() {
		return [
			[null],
			[[]],
			[false],
		];	
	}

  /**
   * @dataProvider emptyArgumentsForRun
   */
	public function testRunEmptyOrNone($params) {
		$filtered = $this->fail->run($params);
		$this->assertEmpty($filtered);
	}
}
