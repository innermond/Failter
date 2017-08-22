<?php

class Def {
	
	private $unconsumed, $def, $args;
	
	public function __construct($def) {
    $def = $this->prepareDef($def);

    $this->unconsumed = $def;
		$this->def = $def;
		$this->messages = $this->makeMessages();
		$this->rounds = $this->makeRounds();
  }

  private function prepareDef($def) {
    foreach($def as $k => &$v) {
      if (self::isFilter($v)) continue;
      if ( ! self::array_is_num($v)) {
        $def[$k] = new self($v);
        continue;
      }
    }
    return $def;    
  } 

	public function getMessages() {
		return $this->messages;	
	}

	public function undef(string $k) {
		$this->args[$k] = null;
	}
	
	private  function runRound($i, $data) {
		$this->args = $this->rounds[$i];
		$partial=[];
		foreach($this->args as $k => $v) {
			if ($v instanceOf Def) {
				$partial[$k] = $v->run($data[$k] ?? []);
				// remove this definition because args in filter_var_array do not know objects
				$this->undef($k);
			}
		}
		$curr = filter_var_array($data, $this->args);
		$this->args = null;
		return ($partial + $curr);
	}

  private $data;

  public function getData() {
    return $this->data;
  }

	private function run($data) {
    $this->data = $data;

		$out=[];
		foreach($this->rounds as $i => $args) {
			$out[] = $this->runRound($i, $data);
    }
		return array_merge_recursive(...$out);
	}

  private function unjoin($arr, $size) {
    if ( ! is_array($arr)) $arr = [$arr];
    $k = array_chunk($arr, $size);
    $m = array_map(null, ...$k);
    return $m; 
  }

  private function chunk($filtered=[]) {
    foreach($this->unconsumed as $k => $elem) {
      $elems = $elem;
      if ( 
        self::isFilter($elems) or 
        ! is_array($elem)
      ) 
      $elems = [$elem];

      if ( ! isset($filtered)) continue;
      $elf = &$filtered[$k];
      if ( ! isset($this->data[$k])) continue;
      $size = count($this->data[$k]);
      foreach($elems as $el) {
        // deal with a filter
        $filterInNeed = 
          self::isFilter($el) && 
          isset($el['flags']) && 
          $this->needChunking($el['flags']);
        if ($filterInNeed) {
          // chunking
          $elf = $this->unjoin($elf, $size);
        }
        // deal with a def
        else if ($el instanceOf self) {
          $elf = $el->chunk($elf);
        }
      }
    }
    return $filtered;
  }

	private $errors = [];

	public function getErrors() {
		return $this->errors;
	}

  public function check($data) {
    $filtered = $this->run($data);
    $chunked = $this->chunk($filtered);
		$errors = self::array_substitute($chunked, $this->messages);
		$msg = self::array_filter_recursive($errors, function($el) { return ! is_null($el); });
		$msg = self::array_filter_recursive($errors, function($el) { return ! ( is_array($el) && empty($el));});
		$this->errors = $msg;
		$fail = ! empty($msg);
		if ($fail) return false;
    return $chunked;
  }

	private $rounds=[];

	private function makeRounds() {
		$source = &$this->def;
    $cut = [];
		while (count($source)) {
			// definition collector
			$el = [];
			foreach($source as $k => &$v) { // &$v need to be reference for array_shift 
				// no more definitions
				if (empty($v)) {
					// shorten life of while cycle here
					unset($source[$k]);
					continue;
				}
        $isfilter = self::isFilter($v);
				if (
          $isfilter
					or 
					$v instanceOf Def
				) 
        {
					$el[$k] = $v;
					$v = [];
				} else if (is_array($v) and ! self::isFilter($v)) {
					// collect only first
					if ( ! empty($v)) $el[$k] = array_shift($v); // instead &$v we can use $this->def[$k] which is a reference by itself;
				}
			}
			if (! empty($el)) $cut[] = $el;
		}
		return $cut;
	}

	private $messages=[];

	private function makeMessages() {
		$out=[];
		foreach($this->def as $k => $v) {
			$out[$k] = null;
			if ($v instanceOf Def) {
				$out[$k] = empty($v->messages) ? $v->makeMessages() : $v->messages;
			} else if (self::isFilter($v)) {
						$out[$k] = $v['message'] ?? null;
			} else if (is_array($v)) {// array of filters
				if (array_key_exists('message', $v)) // boss message
					$out[$k] = $v['message'];
				else {
					foreach($v as $vv) {
						if (is_array($vv) and array_key_exists('message', $vv))
							$out[$k][] = $vv['message'];
						else
							$out[$k][] = null;
					}
				}
			}
		}
		return $out;
	}

  public function needChunking($flag) {
    $require = FILTER_REQUIRE_ARRAY == (FILTER_REQUIRE_ARRAY & $flag);
    $force = FILTER_FORCE_ARRAY == (FILTER_FORCE_ARRAY & $flag);
    return ($require || $force);
  }
	
	public static function filter_ids() {
		static $out;
		if (is_array($out)) return $out;
		$filters = filter_list(); 
		foreach($filters as $filter_name) { 
			$out[] = filter_id($filter_name);
		} 
		return $out;
	}

	public static function isFilter($filter) {
		if (
			is_int($filter) 
			and 
			in_array($filter, self::filter_ids())
		) 
		return true;
		
		if (
			is_array($filter)
			and 
			array_key_exists('filter', $filter)
			and
			in_array($filter['filter'], self::filter_ids())
		) 
		return true;
		
		return false;
	}

	public static function array_substitute(array $original, $substitute) {
		foreach ($original as $key => $value) { 
			if (is_array($value)) { 
				if (is_numeric($key))
				{ 
					$isIndexed = self::array_is_num($substitute);
					if ($isIndexed) {
						$original[$key] = self::array_substitute($original[$key], $substitute); 
						continue;
					}
				}
				$original[$key] = self::array_substitute($original[$key], $substitute[$key]); 
			}

			else { 
				$original[$key] = null;
				if ($value === null or $value === false) {
					$msg = ($value === null) ? 'required' : 'invalid';
					if (is_array($substitute)) {
						$msg = $substitute[$key] ?? $msg;
					} else if (is_string($substitute)) {
						$msg = $substitute;
					}
					else if (is_object($substitute)) {
						$msg = $substitute;
					}	
					$original[$key] = $msg;
				}
			} 
		} 
		// Return the joined array 
		return $original; 
	}

	public static function array_filter_recursive(&$input, $callback = null) { 
    foreach ($input as $key => &$value) { 
			if (is_array($value)) { 
        $value = self::array_filter_recursive($value, $callback); 
      } 
    } 
    
    return array_filter($input, $callback); 
  }

  public static function array_is_num($arr) {
    return count(array_filter(array_keys($arr), 'is_string')) == 0;
  }

}


$data = [
  'component'     => [1, 20, 10, 'a', 0],
  'user' 					=> [
    'span' => 1, 
    'ttl' => 'aaa',
    'money' => ['borrowed' => 250, 'from' => 'gbmob.ro'],
  ],
  'testscalar'    => [2, 'a', '12'],
  'testarray'     => ['2', 2],
  'step' => ['one' => ['0.5', 1], 'two' => ['three' => 'b', 'five' => ['six' => [1, 2.5, 'one'] ]], 'four' => 'c'],
];
$paranoy=[
  [
    'filter'    => FILTER_VALIDATE_INT,
    'flags' => FILTER_REQUIRE_ARRAY,
    'options'   => ['min_range' => 1, 'max_range' => 10],
    'message' => (object) ['paranoy limited',  [1, 10]],
  ],
  ['filter' => FILTER_CALLBACK, 
   'options' => function($el){
      return (is_numeric($el) and $el%2) ? $el : false; 
    },
   'message' => 'iparanoya',
  ],
];

/*$args = [
    'step' => new Def(
      ['one' => 
        [
          ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY, 'message' => 'integering'], 
          ['filter' => FILTER_VALIDATE_FLOAT, 'flags' => FILTER_REQUIRE_ARRAY, 'message' => 'floating'],
        ],

      'two' => new Def(
				['three' => ['filter' => FILTER_VALIDATE_EMAIL, 'flags' => FILTER_REQUIRE_ARRAY, 'message' => 'twerror'],
				'five' => new Def(['six' => 
				[
					['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY],
					['filter' => FILTER_VALIDATE_FLOAT, 'flags' => FILTER_REQUIRE_ARRAY],
					['filter' => FILTER_CALLBACK, 'options' => function($el) {
						return substr($el, 0, 0) == 1 ? false : $el;
					}],
				]
			])
		]
       ),

        'four' => [
          ['filter' => FILTER_VALIDATE_EMAIL, 'message'=> 'fourrer'], 
          FILTER_UNSAFE_RAW,
          [FILTER_UNSAFE_RAW, FILTER_UNSAFE_RAW, FILTER_UNSAFE_RAW, 'message' => 'dumpit'],
          ['filter' => FILTER_VALIDATE_INT, 'message' => 'needmore'],
        ]
      ]
      ),

      'component'  => $paranoy,

    'user' => new Def(
      [
        'span' => ['filter' => FILTER_VALIDATE_BOOLEAN, 'message' => 'spanerr'],
        'ttl' 	=> ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY],
        'money' => new Def(
          [
            'borrowed' => ['filter' => FILTER_VALIDATE_FLOAT, 'flags' => FILTER_FORCE_ARRAY],
            'from' => FILTER_VALIDATE_EMAIL
          ]
        ),
      ]
    ),

  'doesnotexist' => FILTER_VALIDATE_INT,

  'testscalar'   => [
    [
    'filter' => FILTER_VALIDATE_INT,
    'flags'  => FILTER_REQUIRE_ARRAY,
    'message' => 'only int',
    ],
    ['filter' => FILTER_CALLBACK, 
       'options' => function($el){
          return (is_numeric($el) and $el%2) ? $el : false; 
        },
       'message' => 'uneven',
    ]
  ],

  'testarray'    => [
    'filter' => FILTER_VALIDATE_INT,
    'flags'  => FILTER_FORCE_ARRAY,
    'flags'  => FILTER_REQUIRE_ARRAY //| FILTER_FORCE_ARRAY,
  ]
]*/;

$raws = [
    'step' => 
      ['one' => 
        [
          ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY, 'message' => 'integering'], 
          ['filter' => FILTER_VALIDATE_FLOAT, 'flags' => FILTER_REQUIRE_ARRAY, 'message' => 'floating'],
        ],

      'two' => 
				['three' => ['filter' => FILTER_VALIDATE_EMAIL, 'flags' => FILTER_REQUIRE_ARRAY, 'message' => 'twerror'],
				'five' => ['six' => 
				[
					['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY],
					['filter' => FILTER_VALIDATE_FLOAT, 'flags' => FILTER_REQUIRE_ARRAY, 'message' => (object) ['whaaat??', [999]]],
					['filter' => FILTER_CALLBACK, 'options' => function($el) {
						return substr($el, 0, 0) == 1 ? false : $el;
					}],
				]
			]
		]
       ,

        'four' => [
          ['filter' => FILTER_VALIDATE_EMAIL, 'message'=> 'fourrer'], 
          FILTER_UNSAFE_RAW,
          [FILTER_UNSAFE_RAW, FILTER_UNSAFE_RAW, FILTER_UNSAFE_RAW, 'message' => 'dumpit'],
          ['filter' => FILTER_VALIDATE_INT, 'message' => 'needmore'],
        ]
      ]
      ,

      'component'  => $paranoy,

    'user' => 
      [
        'span' => ['filter' => FILTER_VALIDATE_BOOLEAN, 'message' => 'spanerr'],
        'ttl' 	=> ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY],
        'money' => 
          [
            'borrowed' => ['filter' => FILTER_VALIDATE_FLOAT, 'flags' => FILTER_FORCE_ARRAY],
            'from' => FILTER_VALIDATE_EMAIL
          ]
        ,
      ]
    ,

  'doesnotexist' => FILTER_VALIDATE_INT,

  'testscalar'   => [
    [
    'filter' => FILTER_VALIDATE_INT,
    'flags'  => FILTER_REQUIRE_ARRAY,
    'message' => 'only int',
    ],
    ['filter' => FILTER_CALLBACK, 
       'options' => function($el){
          return (is_numeric($el) and $el%2) ? $el : false; 
        },
       'message' => 'uneven',
    ]
  ],

  'testarray'    => [
    'filter' => FILTER_VALIDATE_INT,
    'flags'  => FILTER_FORCE_ARRAY,
    'flags'  => FILTER_REQUIRE_ARRAY //| FILTER_FORCE_ARRAY,
  ]
];

/*$args = 
	[
		'testarray'    => 
		[
		'filter' => FILTER_VALIDATE_INT,
		'flags'  => FILTER_FORCE_ARRAY,
		'flags'  => FILTER_REQUIRE_ARRAY,
		'message' => (object) ['only int, stupid!', 100],
		]
	]
	;*/
//$data = ['testarray' => [1, 'a2']];
$def = new Def($raws);
$checked = $def->check($data);
var_export($def->getErrors());
