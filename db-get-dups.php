<?php

if ($argc < 2){
	echo 'Please enter one or more files to analyze.'.PHP_EOL;
	exit();
}

$cwd = getcwd();

$db_files = $argv;
array_shift($db_files);

$dbs = array_map(function($db_file) use ($cwd) {
	$filename = $cwd.'/db/hash_name'.strtr($db_file,'/','_');
	if (!is_file($filename)){
		echo 'Database '.$db_file.' does not exist!'.PHP_EOL;
		exit(1);
	}
	$db = unserialize(file_get_contents($filename));
	if (!is_array($db)){
		echo 'Database '.$db_file.' is not valid!'.PHP_EOL;
		exit(1);
	}
	return $db;
},$db_files);

$db_count = count($dbs);
if ($db_count === 1){
	$db = forward_static_call_array('array_merge_recursive', $dbs);
	$db = array_filter($db,function($data){ return count($data) > 1; });
	print_r($db);
} else if ($db_count === 2) {
	$db = forward_static_call_array('array_intersect_key', $dbs);
	print_r($db);
} else {
	echo 'Does not currently support comparison of more than two files';
	exit(1);
}

?>