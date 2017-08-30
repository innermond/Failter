<?php  declare(strict_types = 1);

use PHPUnit\Framework\TestCase;
use Innermond\Failter\Failter as Failter;

class FailterTest extends TestCase {

	private $fail;

	public function setUp() {
		$this->fail = new Failter;
	}

  public function emptyArguments() {
    return [
      [[]], 
    ];
  }

  /**
   * @dataProvider emptyArguments
   */
	public function testInitEmpty($param) {
		$filtered = $this->fail->init([])->check($param);
		$this->assertEmpty($filtered);
	}

  public function provideRegularExpressions() {

    $fail = $this->fail;
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

    $data = ['name' => 'UPPERCASE'];
    $checks = ['name' => rx('/^[A-Z]+$/')];
    $want = ['name' => 'UPPERCASE'];

    return [
      [$data, $checks, $want],
      [
        $data = ['name' => '123'],
        ['name' => rx('/\d+/')],
        $data,
      ],

    ];
    /*
    return;
    $rules = [
    'step' => 
      ['one' => 
        [
          must(\FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY, null, 'integering'), 
          must(\FILTER_VALIDATE_FLOAT, \FILTER_REQUIRE_ARRAY, null, 'floating'),
        ],

      'two' => 
        ['three' => must(\FILTER_VALIDATE_EMAIL, \FILTER_REQUIRE_ARRAY, null, 'twerror'),
        'five' => ['six' => 
        [
          must(\FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY),
          must(\FILTER_VALIDATE_FLOAT, \FILTER_REQUIRE_ARRAY, null, ['whaaat??', [999]]),
          ck(function($el) {
            return substr($el, 0, 0) == 1 ? false : $el;
          }),
        ]
      ]
    ]
       ,

        'four' => [
          must(\FILTER_VALIDATE_EMAIL, null, null, 'fourrer'), 
          \FILTER_UNSAFE_RAW,
          [\FILTER_UNSAFE_RAW, \FILTER_UNSAFE_RAW, \FILTER_UNSAFE_RAW, 'message' => 'dumpit'],
          must(\FILTER_VALIDATE_INT, null, null, 'needmore'),
        ]
      ]
      ,

      'component'  => $paranoy,

    'user' => 
      [
        'span' => must(\FILTER_VALIDATE_BOOLEAN, null, null, 'spanerr'),
        'ttl' 	=> must(\FILTER_VALIDATE_INT, \FILTER_FORCE_ARRAY, null, 'time to live more'),
        'money' => 
          [
            'borrowed' => must(\FILTER_VALIDATE_FLOAT, \FILTER_FORCE_ARRAY),
            'from' => \FILTER_VALIDATE_EMAIL
          ]
        ,
      ]
    ,

  'doesnotexist' => \FILTER_VALIDATE_INT,

  'testscalar'   => [
    must(\FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY, null, null,'only int'),
    ck(function($el){
          return (is_numeric($el) and $el%2) ? $el : false; 
        },
       'uneven'
     )
  ],

  'testarray'    => must(\FILTER_VALIDATE_INT, \FILTER_REQUIRE_ARRAY),
  ];

  $data = [
    'component'     => [1, 20, 10, 'a', 0],
    'user' 					=> [
      'span' => 1, 
      'ttl' => ['1', 'a2a', 3],
      'money' => ['borrowed' => 250, 'from' => 'gbmob.ro'],
    ],
    'testscalar'    => [2, 'a', '12'],
    'testarray'     => ['2', 2],
    'step' => ['one' => ['0.5', 1], 'two' => ['three' => 'b', 'five' => ['six' => [1, 2.5, 'one'] ]], 'four' => 'c'],
  ];
    
  yield [$data, $rules];*/
}

	/**
	 * @dataProvider provideRegularExpressions
	 */
	public function testRegularExpressionFilterThatExpects($data, $checks, $want) {
    $filtered = $this->fail->init($checks)->check($data);
    if ($filtered === false) $this->fail();
    $this->assertEquals($filtered, $want);
	}
}

function rx($rx, $msg = null) {
  $msg = is_array($msg) ? (object) $msg : $msg;
  $fl = ['filter' => \FILTER_VALIDATE_REGEXP, 'options' => ['regexp' => $rx]];
  return is_null($msg) ? $fl : $fl + ['message' => $msg];
}

function ck(callable $fn, $msg = null) {
  $msg = is_array($msg) ? (object) $msg : $msg;
  $fl = ['filter' => \FILTER_CALLBACK, 'options' => $fn];
  return is_null($msg) ? $fl : $fl + ['message' => $msg];
}

function must(int $filter,  int $flags = null, $options = null, $message = null) {
  $message = is_array($message) ? (object) $message : $message;
  return array_filter(compact('filter', 'flags', 'options', 'message'));
}
