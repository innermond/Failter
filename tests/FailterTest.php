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

  public function setUpFailter() {
    $this->fail->fromDefinitions(['email' => [\FILTER_VALIDATE_EMAIL, \FILTER_SANITIZE_EMAIL]]);
  }

  /**
   * @dataProvider emptyArgumentsForRun
   */
	public function testRunEmptyOrNone($params) {
    $this->setUpFailter();
		$filtered = $this->fail->run($params);
		$this->assertEmpty($filtered);
	}

  public function paramsCases() {
    return [
      'email is ok' => [
        ['email' => \FILTER_VALIDATE_EMAIL],
        ['email' => 'gb@mob.ro'],
        ['email' => 'gb@mob.ro'],
      ],
      'email is wrong' => [
        ['email' => \FILTER_VALIDATE_EMAIL],
        ['email' => 'gbmob.ro'],
        false,
      ],
      'length error' => [
         ['len' => function($el) { return mb_strlen($el) < 5 ? false : $el;}],
         ['len' => 'abc'],
         false,
      ],
    ];
  }

  /**
   * @dataProvider paramsCases
   */
  public function testRunParams($def, $params, $want) {
    $filtered = $this->fail->fromDefinitions($def)->run($params);
    $this->assertEquals($filtered, $want);
  }
}
