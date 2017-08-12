<?php namespace Innermond\Failter;

class Failter {

  /**
   * $defs = ['name' => [
   *    [$filter, $msg], $filter
   *  ]
   * ]
   */
  public function fromDefinitions($definitions) {
    foreach($definitions as $k => $defs) {
      // operate on field $k
      $this->on($k);
      if ( ! is_iterable($defs)) $defs = [$defs];
      foreach($defs as $def) {
        //  array not asociative
				$sequential = false;
				if (is_iterable($def))
          $sequential = count(array_filter(array_keys($def), 'is_string')) == 0;
        $message = null;
        if ($sequential) {
          [$filter, $message] = $def;
          $this->with($filter, $message);
        } else {
          $filter = $def;
          $this->with($filter);
        }
      }
    }
    // no more field to operate on
    $this->field = null;
    return $this;
  }

  /* hold definition array for filters that is consumed whan call run method
     after run works $def is empty
  */
	private $def=[];

	public function callback($key, callable $fn, ...$msg) {
		$this->def[$key][] = ['filter' => \FILTER_CALLBACK, 'options' => $fn] + ['message' => $msg];
;
		return $this;
	}

	public function regex($key, $rx, ...$msg) {
		$this->def[$key][] = ['filter' => \FILTER_VALIDATE_REGEXP, 'options' => ['regexp' => $rx]] + ['message' => $msg];
		return $this;
	}

	public function filter($key, $filter, ...$msg) {
		if ( ! is_array($filter)) $filter = ['filter' => $filter];
		$this->def[$key][] = $filter + ['message' => $msg];
		return $this;
	}

	private $field;

	public function on(string $name) {
    // check even for long empty string case
    if (empty(trim($name))) {
     throw new \InvalidArgumentException('missing field');
    }
		$this->field = $name;
		return $this;
	}

	public function with($filter, ...$msg) {
		if (is_null($this->field)) {
			throw new \Exception('set field before call ' . __METHOD__);
		}
		$key = $this->field;
		$flatten = array_merge([$key, $filter], $msg);

		switch (true) {
			case is_string($filter) :
				call_user_func_array([$this, 'regex'], $flatten);
			break;
			case is_callable($filter) :
				call_user_func_array([$this, 'callback'], $flatten);
			break;
			default :
				call_user_func_array([$this, 'filter'], $flatten);
			break;
		}
		return $this;
	}

	private $error;
	private $defs;

  public function run($params=[]) {
    if (! is_iterable($params)) return false;
		$this->field = null;
		$this->defs = [];
		// prepare $this->defs from $this->def as such every item of it to be consumable by filter_var_array
		while (count($this->def)) {
			// definition collector
			$el = [];
			foreach($this->def as $k => &$v) {
				// no more definitions
				if (empty($v)) {
					// shorten life of while cycle here
					unset($this->def[$k]);
					continue;
				}
				$el[$k] = array_shift($v);
			}
			if (! empty($el)) $this->defs[] = $el;
		}
		$this->error = [];
		$filtered = array_reduce($this->defs, function($carry, $def) use (&$params) {
      $newcarry = \filter_var_array($params, $def);
      // $params is a reference to keep valid modified values, replacing all invalids (null and false) with their original values to be validated by next iteration
      $params = array_filter($newcarry) + $params;
      // cycle through filtered result
			foreach($newcarry as $key => $val) {
				// no error yet
				$err = null;
				// get error message from definition
				$msg = null;
				if (isset($def[$key]['message']) and !empty($def[$key]['message'])) 
					$msg = $def[$key]['message'];
        // start checking for errors; missing keys are added as NULL - default behaviour of filter_var_array
				if (is_null($val)) {
					$err = $msg ?? 'required';
				} else if (false === $val) {// error detected
					$err = $msg ?? 'invalid';
				} 
				// if an error occured add it
				if ( ! is_null($err)) $this->error[$key][] = $err;
				// 
				unset($def[$key]['message']);
			}
			return array_merge_recursive($carry, $newcarry);
		}, []);

		if (count($this->error)) return false;
		return $this->prepareFiltered($filtered);
	}

	private function prepareFiltered($filtered) {
		foreach($filtered as $k => &$v) {
			if (count($v) > 1) $v = array_pop($v);
		}
		return $filtered;
	}

	public function getError() {
		return $this->error;
	}
}
