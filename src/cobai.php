<?php

class Def {
	
	private $unconsumed, $def, $args;
	
	public function __construct($def) {
    $this->unconsumed = $def;

		$this->def = $def;
		$this->messages = $this->makeMessages();
		$this->rounds = $this->makeRounds();
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
        $this->isFilter($elems) or 
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
          $this->isFilter($el) && 
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

  public function check($data) {
    $filtered = $this->run($data);
    $chunked = $this->chunk($filtered);
    return $chunked;
  }

	private $rounds=[];

	private function makeRounds() {
		$source = &$this->def;
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
        $isfilter = $this->isFilter($v);
				if (
          $isfilter
					or 
					$v instanceOf Def
				) 
        {
          /*if ($isfilter) {
            $flags = $v['flags'] ?? false;
            if ( ! $flags) break;
            $need = $this->needChunking($flags);
            if ( ! $need) break;

        }*/
					$el[$k] = $v;
					$v = [];
				} else if (is_array($v) and ! $this->isFilter($v)) {
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
			} else if ($this->isFilter($v)) {
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
	
	static function filter_ids() {
		static $out;
		if (is_array($out)) return $out;
		$filters = filter_list(); 
		foreach($filters as $filter_name) { 
			$out[] = filter_id($filter_name);
		} 
		return $out;
	}

	public function isFilter($filter) {
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


}


$data = [
    'component'     => [1, 20, 10, 'a', 0],
		'user' 					=> [
			'span' => 1, 
		 	'ttl' => 'aaa',
			'money' => ['borrowed' => 250, 'from' => 'gbmob.ro'],
		],
    'testscalar'    => [2, 'a', '12'],
    'testarray'     => ['2', 'a'],
		'step' => ['one' => ['0.5', 1], 'two' => ['three' => 'b'], 'four' => 'c'],
	];
$paranoy=[
      [
        'filter'    => FILTER_VALIDATE_INT,
        'flags' => FILTER_REQUIRE_ARRAY,
        'options'   => ['min_range' => 1, 'max_range' => 10],
      ],
      ['filter' => FILTER_CALLBACK, 
       'options' => function($el){
          return (is_numeric($el) and $el%2) ? $el : false; 
        },
       'message' => 'iparanoya',
      ],
    ];

$args = [
    'step' => new Def(
      ['one' => 
        [
          ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_REQUIRE_ARRAY, 'message' => 'integering'], 
          ['filter' => FILTER_VALIDATE_FLOAT, 'flags' => FILTER_REQUIRE_ARRAY, 'message' => 'floating'],
        ],

      'two' => new Def(
        ['three' => ['filter' => FILTER_VALIDATE_EMAIL, 'flags' => FILTER_REQUIRE_ARRAY, 'message' => 'twerror']]
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
];

$def = new Def($args);
$messages = $def->getMessages();
$checked = $def->check($data);
function array_substitute(array $original, $substitute) { 
  foreach ($original as $key => $value) { 
    if (is_array($value)) { 
      if (is_numeric($key))
      { 
        $isIndexed = count(array_filter(array_keys($substitute), 'is_string')) == 0;
        if ($isIndexed) {
          $original[$key] = array_substitute($original[$key], $substitute); 
          continue;
        }
      }
      $original[$key] = array_substitute($original[$key], $substitute[$key]); 
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
        $original[$key] = $msg;
      }
    } 
  } 
  // Return the joined array 
  return $original; 
} 

$c = var_export($checked, true);
$m = var_export($messages, true);
;
$errors = array_substitute($checked, $messages);
$e = var_export($errors, true);
file_put_contents('./chunked', $c);
file_put_contents('./messages', $m);
file_put_contents('./erros', $e);

