<?php

$source = [
	'one' => [1.0, 1.1, 1.2],
	'two' => [2.0, 2.1, 2.2, 2.3],
	'three' => [3.0, 3.1],
	'four' => [4.0]
];

$cut = [
	[
	'one' => [1.0],
	'two' => [2.0],
	'three' => [3.0],
	'four' => [4.0],
 ],
[
	'one' => [1.1],
	'two' => [2.1],
	'three' => [3.1],
],
[
	'one' => [1.2],
	'two' => [2.2],
],
[
	'two' => [2.3],
 ]
];


function array_cut($source) {
	$keys = array_keys($source);
	$arr = array_values($source);
	$cut = array_map(null, ...$arr);
	foreach($cut as &$el) {
		$el = array_combine($keys, $el);
		$el = array_filter($el, function($v) {return ! is_null($v);});
	}
	return $cut;
	$cut=[];
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
				// collect only first
				$el[$k] = array_shift($v); // instead &$v we can use $this->def[$k] which is a reference by itself;
			}
			if (! empty($el)) $cut[] = $el;
}
return $cut;
}

$out = array_cut($source);
var_dump($out);

function filter_ids() {
	static $out;
	if (is_array($out)) return $out;
	$filters = filter_list(); 
	foreach($filters as $filter_name) { 
		$out[] = filter_id($filter_name);
	} 
	return $out;
}

function is_filter($filter) {
	if (
		is_int($filter) 
		and 
		in_array($filter, filter_ids())
	) 
	return true;
	
	if (
		is_array($filter)
		and 
		array_key_exists('filter', $filter)
		and
		in_array($filter['filter'], filter_ids())
	) 
	return true;
	
	return false;
}

var_dump(is_filter(['filter' => FILTER_VALIDATE_EMAIL]));


function array_vertical($arr, $size) {
  $k = array_chunk($arr, $size);
  $m = array_map(null, ...$k);
  return $m; 
}
