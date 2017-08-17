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

  private static function len($min, $max) : callable {
    return function($el) use ($min, $max) {
      $len = mb_strlen($el);
      return ($len >= $min and $len <= $max) ? $el : false;
    };
  }

	public function paramsCasesDef() {
	 return	[
			'email is ok' => [
				['email' => \FILTER_VALIDATE_EMAIL],
				['email' => 'gb@mob.ro'],
				['email' => 'gb@mob.ro'],
				[],
			],
			'email is wrong' => [
				['email' => \FILTER_VALIDATE_EMAIL],
				['email' => 'gbmob.ro'],
				false,
				['email' => ['invalid']],
			],
			'length error' => [
				 ['len' => self::len(5, 10)],
				 ['len' => 'abc'],
				 false,
				['len' => ['invalid']],
			 ],
			'name has only letters and space, between 5 and 50 characters' => [
				['name' => [
						'/^[a-z\ ]{5, 50}/i',
						self::len(5, 50),
					]
				],
				['name' => 'ab c'],
				false,
				['name' => ['invalid', 'invalid']],
			],
			[
				['name' => [
						'/^[a-z\ ]+$/i',
						self::len(5, 50),
					]
				],
				['name' => 'qwert'],
				['name' => 'qwert'],
				[],
			],
			[
				// needs to be array
				['cobai' => [
						// from 1 to 10
						[ 'filter' => \FILTER_VALIDATE_INT, 
							'flags' => \FILTER_REQUIRE_ARRAY,
							'options' => ['min_range' => 1, 'max_range' => 10],
							'message' => 'cobai.malformed',
						],
						// except 5
						function($el) { 
							// $el is stringized
							return $el === '5' ? false : $el;
						},
					]
				],
				['cobai' => ['6']],
				['cobai' => '6'],
				[],
			],
			[
				['cobai_1' => [
						// from 1 to 3
						[ 'filter' => \FILTER_VALIDATE_INT, 
							'options' => ['min_range' => 1, 'max_range' => 3],
							'message' => 'cobai.malformed',
						],
						// except 5
						function($el) { 
							// $el is stringized
							return $el === '555' ? false : $el;
						},
						['filter' => \FILTER_VALIDATE_REGEXP, 'options' => ['regexp' => '/^\d{2}$/i'], 'message' => 'cobai.offending'],
					],
					'cobai_2' => \FILTER_VALIDATE_INT,
				],
				['cobai_1' => '555'],
				false,
				['cobai_1' => ['cobai.malformed', 'invalid', 'cobai.offending'], 'cobai_2' => ['required']],
			]
		];
	}

  /**
   * @dataProvider paramsCasesDef
   */
  public function ___testRunParams($def, $params, $want, $msg) {
  	$filtered = $this->fail->fromDefinitions($def)->run($params);
    $this->assertEquals($filtered, $want);
		$this->assertEquals($msg, $this->fail->getError());
  }

	public function fluentDef() {
		// setUp hasn't been run yet
		$fail = new Me\Failter;
		$superstrip = [\FILTER_SANITIZE_STRING, \FILTER_FLAG_STRIP_BACKTICK | \FILTER_FLAG_ENCODE_AMP];
    $between = function($min, $max, $greedy=true) {                                                                                                                                                                          
      return function($val) use ($min, $max, $greedy) {
				$len = is_numeric($val) ? $val : is_array($val) ? count($val) : strlen($val);
				if($greedy)
					$out = ($min <= $len and $max >= $len) ? $val : false;
        else
          $out = ($min < $len and $max > $len) ? $val : false;
			return $out;
			};
		};
		$upper = function($el) {
			return strtoupper($el);
		};
		
		yield [
			$fail
				->on('name')
				->with('/^[a-z\ ]{2,3}$/i', 'shape.bad')
				->var(...$superstrip)
				->with($between(2, 3), 'len.between', [2, 3])
				->with($upper)

				->on('age')
				->needed('longevity', 100)

				->on('choice')
				->var(\FILTER_UNSAFE_RAW , \FILTER_REQUIRE_ARRAY, 'crowd')
				->with($between(2,3), 'len.bad')
				,
			['name' => 'ab12', 'choice' => '1'],
			false,
			['name' => [
					['shape.bad'],
					['len.between', [2,3]],
				],
				'age' => [
					['longevity', 100],
				],
				'choice' => [
					['crowd'],
					['len.bad'],
				],
			],
		];		
	}

	/**
	 * @dataProvider fluentDef
	 */
	public function testRunWithFluentDefinitions($fail, $params, $want, $msg) {
  	$filtered = $fail->run($params);
		var_dump($fail->getError());
    $this->assertEquals($filtered, $want);
		$this->assertEquals($msg, $fail->getError());
	}
}
