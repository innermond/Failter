<?php namespace Innermond\Failter;

/**
 * Class: Failter
 *
 */
class Failter {

  public const
    REQUIRED = 'required',
    INVALID = 'invalid';

	
  private 
    $unconsumed, // save here definition
    $def, // definition that will be consumed by rounds
    $args; // slice of definition, also consumable
	
  public function __construct(array $def = null) {
    if (is_null($def)) return;
    $this->init($def);
  }

  public function init(array $def) {
    $def = $this->prepareFailter($def);

    $this->unconsumed = $def;
		$this->def = $def;
		$this->messages = $this->makeMessages();
		$this->rounds = $this->makeRounds();

    return $this;
  }

  /**
   * prepareFailter
   * transform every branch of associative array $def into a class Failter
   * used internally to provide a proper argument for methods [run, runRound]
   *
   * @param array $def
   * @return array
   */
  private function prepareFailter(array $def) : array {
    foreach($def as $k => &$v) {
      if (static::isFilter($v)) continue;
      if ( ! static::array_is_num($v)) {
        $def[$k] = new static($v);
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
			if ($v instanceOf static) {
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
		return empty($out) ? [] : array_merge_recursive(...$out);
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
        static::isFilter($elems) or 
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
          static::isFilter($el) && 
          isset($el['flags']) && 
          $this->needChunking($el['flags']);
        if ($filterInNeed) {
          // chunking
          $elf = $this->unjoin($elf, $size);
        }
        // deal with a def
        else if ($el instanceOf static) {
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
		$errors = static::array_substitute($chunked, $this->messages);
    // remove null values, just keep errors as null values in errors means no error
		$msg = static::array_filter_recursive($errors, function($el) { return ! is_null($el); });
    // keep out empty arrays
		$msg = static::array_filter_recursive($msg, function($el) { return ! ( is_array($el) && empty($el));});
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
        $isfilter = static::isFilter($v);
				if (
          $isfilter
					or 
					$v instanceOf static
				) 
        {
					$el[$k] = $v;
					$v = [];
				} else if (is_array($v) and ! static::isFilter($v)) {
					// collect only first
					if ( ! empty($v)) $el[$k] = array_shift($v); // instead &$v we can use $this->def[$k] which is a reference by itstatic;
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
			if ($v instanceOf static) {
				$out[$k] = empty($v->messages) ? $v->makeMessages() : $v->messages;
			} else if (static::isFilter($v)) {
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
			is_int($filter) and 
			in_array($filter, static::filter_ids())
		) 
		return true;
		
		if (
			is_array($filter) and 
			array_key_exists('filter', $filter) and
			in_array($filter['filter'], static::filter_ids())
		) 
		return true;
		
		return false;
	}

	public static function array_substitute(array $original, $substitute) {
		foreach ($original as $key => $value) { 
			if (is_array($value)) { 
				if (is_numeric($key)) { 
					$isIndexed = static::array_is_num($substitute);
					if ($isIndexed) {
						$original[$key] = static::array_substitute($original[$key], $substitute); 
						continue;
					}
				}
				$original[$key] = static::array_substitute($original[$key], $substitute[$key]); 
			}

			else { 
				$original[$key] = null;
				if ($value === null or $value === false) {
					$msg = ($value === null) ? static::REQUIRED : static::INVALID; 
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
        $value = static::array_filter_recursive($value, $callback); 
      } 
    } 
    
    return array_filter($input, $callback); 
  }

  public static function array_is_num($arr) {
    return count(array_filter(array_keys($arr), 'is_string')) == 0;
  }

}
