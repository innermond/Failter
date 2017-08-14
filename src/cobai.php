<?php

class Def {
	
	private $def, $args;
	
	public function __construct($def) {
		$this->def = $def;
		$this->messages = $this->makeMessages();
		$this->rounds = $this->makeRounds();
	}

	public function getMessages() {
		return $this->messages;	
	}

	public function def() {
		return $this->args;
	}
	
	public function undef(string $k) {
		$this->args[$k] = null;
	}
	
	public function runRound($i, $data) {
		$this->args = $this->rounds[$i];
		$partial=[];
		foreach($this->args as $k => $v) {
			if ($v instanceOf Def) {
				$partial[$k] = $v->run($data[$k] ?? []);
				// remove this definition
				$this->undef($k);
			}
		}
		$curr = filter_var_array($data, $this->args);
		$this->args = null;
		return ($partial + $curr);
	}

	public function run($data) {
		$out=[];
		foreach($this->rounds as $i => $args) {
			$out[] = $this->runRound($i, $data);
		}

		return array_merge_recursive(...$out);
	}

	private $rounds=[];

	private function makeRounds() {
		$source = &$this->def;
		while (count($source)) {
			// definition collector
			$el = [];
			foreach($source as $k => &$v) { // &$v need to be reference for array_shift 
				// no more definitions
		//var_dump($v);exit;
				if (empty($v)) {
					// shorten life of while cycle here
					unset($source[$k]);
					continue;
				}
				if (
					$this->isFilter($v) 
					or 
					$v instanceOf Def
				) 
				{
					$el[$k] = $v;
					$v = [];
				} else if (is_array($v) and ! $this->isFilter($v)) {
					// collect only first
					$el[$k] = array_shift($v); // instead &$v we can use $this->def[$k] which is a reference by itself;
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
			} else if (is_array($v)) {
				if (array_key_exists('message', $v))
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
			'span' => true, 
		 	'ttl' => 'aaa',
			'money' => ['borrowed' => 250, 'from' => 'gbmob.ro'],
		],
    'testscalar'    => ['2', '23', '12'],
    'testarray'     => ['2', 3, 'a'],
		'step' => ['one' => '0.5', 'two' => ['three' => 'b'], 'four' => 'c'],
	];

$args = [
	'step' => new Def(
		['one' => [FILTER_VALIDATE_INT, FILTER_VALIDATE_FLOAT, 'message' => 'eroare!'],
		'two' => new Def(
			['three' => ['filter' => FILTER_UNSAFE_RAW, 'message' => 'twerror']]
		), 
		'four' => [['filter' => FILTER_VALIDATE_EMAIL, 'message'=> 'fourrer'], FILTER_UNSAFE_RAW]]
		),
    'component'  => ['filter'    => FILTER_VALIDATE_INT,
							'flags'     => FILTER_FORCE_ARRAY, 
							'options'   => ['min_range' => 0, 'max_range' => 10]
						 ],
		'user' => new Def(
			[
				'span' => ['filter' => FILTER_VALIDATE_BOOLEAN, 'flags' => FILTER_NULL_ON_FAILURE, 'message' => 'spanerr'],
				'ttl' 	=> ['filter' => FILTER_VALIDATE_INT, 'flags' => FILTER_FORCE_ARRAY],
				'money' => new Def(
					['borrowed' => ['filter' => FILTER_VALIDATE_FLOAT, 'flags' => FILTER_FORCE_ARRAY], 'from' => FILTER_VALIDATE_EMAIL]
				),
			]
		),
    'doesnotexist' => FILTER_VALIDATE_INT,
    'testscalar'   => [
											'filter' => FILTER_VALIDATE_INT,
											'flags'  => FILTER_REQUIRE_SCALAR,
										 ],
    'testarray'    => [
											'filter' => FILTER_VALIDATE_INT,
											//'flags'  => FILTER_FORCE_ARRAY,
											'flags'  => FILTER_REQUIRE_ARRAY //| FILTER_FORCE_ARRAY,
										 ]

		 ];
$def = new Def($args);
$messages = $def->getMessages();
$filtered = $def->run($data);
$errors = array_merge_recursive($filtered, $messages);
var_dump($errors);
exit;
array_walk_recursive($errors, function(&$el, $key) {
	echo $key, PHP_EOL;
	if ($el === false or is_null($el)) $el = 'error';
});
