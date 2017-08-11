<?php  declare(strict_types = 1);

use PHPUnit\Framework\TestCase;
use Innermond\Failter as Me;

class FailterTest extends TestCase {

  use Test\FailterTrait;

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

  private function len($min, $max) : callable {
    return function($el) use ($min, $max) {
      $len = mb_strlen($el);
      return ($len >= $min and $len <= $max) ? $el : false;
    };
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
         ['len' => $this->len(5, 10)],
         ['len' => 'abc'],
         false,
      ],
      'name has only letters and space, between 5 and 50 characters' => [
        ['name' => [
            '/^[a-z\ ]+$/i',
            $this->len(5, 50),
          ]
        ],
        ['name' => 'ab c'],
        false,
      ],
      [
        ['name' => [
            '/^[a-z\ ]+$/i',
            $this->len(5, 50),
          ]
        ],
        ['name' => 'qwert'],
        ['name' => 'qwert'],
      ],
      [
        // needs to be array
        ['cobai' => [
            // from 1 to 10
            [ 'filter' => \FILTER_VALIDATE_INT, 
              'flags' => \FILTER_REQUIRE_ARRAY,
              'options' => ['min_range' => 1, 'max_range' => 10]],
            // except 5
            function($el) { 
              // $el is stringized
              return $el === '5' ? false : $el;
            },
          ]
        ],
        ['cobai' => ['a', '6']],
        ['cobai' => '6'],
        false,
      ]
    ];
  }

  /**
   * @dataProvider paramsCases
   */
  public function testRunParams($def, $params, $want) {
    $filtered = $this->fail->fromDefinitions($def)->run($params);
    $this->assertEquals($filtered, $want);
  }

  public function syncMessagesCases() {
    return [
     [
      ['name' => [
        [$this->len(1, 2), 'length.abnormal'], // filter one
        '/^\d$/',
        [\FILTER_VALIDATE_EMAIL, 'email.invalid'],
      ]
      ],
      ['name' => [['length.abnormal'], [], ['email.invalid']]],
     ],
    ];
  }
  
  /**
   * @dataProvider syncMessagesCases
   */
  public function testSyncMessagesWithParams($defs, $want) {
    $this->reflect($this->fail);
    $this->fail->fromDefinitions($defs);
    $got = $this->getProperty('errmsg');
    $this->assertEquals($want, $got);
  }

}
