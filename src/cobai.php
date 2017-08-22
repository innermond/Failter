<?php

/**
 * Class: Def
 *
 */
class Def {
	
  private 
    $unconsumed, // save here definition
    $def, // definition that will be consumed by rounds
    $args; // slice of definition, also consumable
	
  public function __construct($def) {
    $def = $this->prepareDef($def);

    $this->unconsumed = $def;
		$this->def = $def;
		$this->messages = $this->makeMessages();
		$this->rounds = $this->makeRounds();
  }

  /**
   * prepareDef
   * transform every branch of associative array $def into a class Def
   * used internally to provide a proper argument for methods [run, runRound]
   *
   * @param array $def
   * @return array
   */
  private function prepareDef(array $def) : array {
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

  /**
   * undef
   *
   * @param string $k
   */
	private function undef(string $k) {
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

  /**
   * chunk
   * Prepare an array to have for every associative key present on definitions
   * an array representing results of all operations done with 
   *
   * @param array $filtered
   */
  private function chunk(array $filtered=[]) {
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

  /**
   * check
   * Run main methods on $data in order to validate accordingly with definition rules
   *
   * @param array $data
   */
  public function check(array $data) {
    $filtered = $this->run($data);
    $chunked = $this->chunk($filtered);
    // build errors
		$errors = self::array_substitute($chunked, $this->messages);
    // remove null values, just keep errors as null values in errors means no error
		$msg = self::array_filter_recursive($errors, function($el) { return ! is_null($el); });
    // keep out empty arrays
		$msg = self::array_filter_recursive($errors, function($el) { return ! ( is_array($el) && empty($el));});
    // convert objects that repesents errors into arrays
    array_walk_recursive($msg, function(&$v, $k) { if (is_object($v)) $v = (array) $v;});
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
    'ttl' => ['1', 'a2a', 3],
    'money' => ['borrowed' => 250, 'from' => 'gbmob.ro'],
  ],
  'testscalar'    => [2, 'a', '12'],
  'testarray'     => ['2', 2],
  'step' => ['one' => ['0.5', 1], 'two' => ['three' => 'b', 'five' => ['six' => [1, 2.5, 'one'] ]], 'four' => 'c'],
];
$paranoy=[
  [
    'filter' => FILTER_VALIDATE_INT,
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

function rx($rx, $msg = null) {
  $msg = is_array($msg) ? (object) $msg : $msg;
  $fn = ['filter' => \FILTER_VALIDATE_REGEXP, 'options' => ['regexp' => $rx]];
  return is_null($msg) ? $fl : $fl + ['message' => $msg];
}

function ck(callable $fn, $msg = null) {
  $msg = is_array($msg) ? (object) $msg : $msg;
  $fl = ['filter' => \FILTER_CALLBACK, 'options' => $fn];
  return is_null($msg) ? $fl : $fl + ['message' => $msg];
}

function fl(int $filter,  int $flags = null, $options = null, $message = null) {
  $message = is_array($message) ? (object) $message : $message;
  return array_filter(compact('filter', 'flags', 'options', 'message'));
}

$raws = [
    'step' => 
      ['one' => 
        [
          fl(FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY, null, 'integering'), 
          fl(FILTER_VALIDATE_FLOAT, FILTER_REQUIRE_ARRAY, null, 'floating'),
        ],

      'two' => 
				['three' => fl(FILTER_VALIDATE_EMAIL, FILTER_REQUIRE_ARRAY, null, 'twerror'),
				'five' => ['six' => 
				[
					fl(FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY),
					fl(FILTER_VALIDATE_FLOAT, FILTER_REQUIRE_ARRAY, null, ['whaaat??', [999]]),
					ck(function($el) {
						return substr($el, 0, 0) == 1 ? false : $el;
					}),
				]
			]
		]
       ,

        'four' => [
          fl(FILTER_VALIDATE_EMAIL, null, null, 'fourrer'), 
          FILTER_UNSAFE_RAW,
          [FILTER_UNSAFE_RAW, FILTER_UNSAFE_RAW, FILTER_UNSAFE_RAW, 'message' => 'dumpit'],
          fl(FILTER_VALIDATE_INT, null, null, 'needmore'),
        ]
      ]
      ,

      'component'  => $paranoy,

    'user' => 
      [
        'span' => fl(FILTER_VALIDATE_BOOLEAN, null, null, 'spanerr'),
        'ttl' 	=> fl(FILTER_VALIDATE_INT, FILTER_FORCE_ARRAY, null, 'time to live more'),
        'money' => 
          [
            'borrowed' => fl(FILTER_VALIDATE_FLOAT, FILTER_FORCE_ARRAY),
            'from' => FILTER_VALIDATE_EMAIL
          ]
        ,
      ]
    ,

  'doesnotexist' => FILTER_VALIDATE_INT,

  'testscalar'   => [
    fl(FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY, null, null,'only int'),
    ck(function($el){
          return (is_numeric($el) and $el%2) ? $el : false; 
        },
       'uneven'
     )
  ],

  'testarray'    => fl(FILTER_VALIDATE_INT, FILTER_REQUIRE_ARRAY),
];

$def = new Def($raws);
$checked = $def->check($data);
var_export(json_encode($def->getErrors(), JSON_PRETTY_PRINT));
