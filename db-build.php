<?php
CONST COMMAND_LINE_LENGTH = 1024;
if ($argc < 2){
	echo 'Please enter a directory to search.'.PHP_EOL;
	exit();
} else if (!is_dir($argv[1])) {
	echo 'Please enter a valid directory to search.'.PHP_EOL;
	exit(1);
}

$processors = intval(exec('nproc'));
echo 'Processors available:	'.$processors.PHP_EOL;

$children = $processors - 1;
echo 'Number of Children:	'.$children.PHP_EOL;

$cwd = getcwd();

$hash_name = array();
$child_pid = array();
$child_sock = array();

chdir($argv[1]);
$wd = getcwd();

for ($i = 1; $i <= $children; $i++){
	$socket_pair = stream_socket_pair(STREAM_PF_UNIX,STREAM_SOCK_STREAM,STREAM_IPPROTO_IP);
	$pid = pcntl_fork();
	if ($pid == -1) {
		echo 'Unable to fork.'.PHP_EOL;
		exit(1);
	} else if ($pid) {
		echo 'Created Child '.$i.' with PID '.$pid.PHP_EOL;
		fclose($socket_pair[0]);
		$child_pid[] = $pid;
		$child_sock[] = $socket_pair[1];
	} else {
		fclose($socket_pair[1]);
		$socket = $socket_pair[0];
		$pid = $i; //getmypid();
		stream_set_blocking($socket,1);
		while (($command = stream_get_line($socket,COMMAND_LINE_LENGTH,PHP_EOL)) !== false){
			list($action,$file) = explode(' ',$command,2);
			#echo $pid.':	Received '.basename($file).PHP_EOL;
			$response = NULL;
			switch ($action){
				case 'md5':
					$response = md5_file($file);
					break;
				case 'sha1':
					$response = sha1_file($file);
					break;
				default:
					break 2;
			}
			$response .= ' '.$file.PHP_EOL;
			fwrite($socket,$response,strlen($response));
			#echo $pid.':	Sent '.basename($file).PHP_EOL;
		}
		echo $pid.':	Quitting'.PHP_EOL;
		fclose($socket_pair[0]);
		exit;
	}
}

$files = array();
exec('find . -xdev -type f -printf "%s %p\n"',$files);
$file_count_initial = count($files);
echo 'Processing '.$file_count_initial.' Files'.PHP_EOL;

$idle = $children;
$read = $child_sock;
$write = NULL;
$except = NULL;

function merge_result(){
	global $child_sock, $idle, $read, $write, $except,$hash_name;
	$read = $child_sock;
	$idle += ($streams = stream_select($read, $write, $except, NULL));
	#echo 'Idle Processes:	'.$idle.PHP_EOL;
	foreach ($read as $index => $socket){
		list($hash,$filename) = explode(' ',stream_get_line($socket,COMMAND_LINE_LENGTH,PHP_EOL),2);
		$hash_name[$hash][] = $filename;
		#echo 'Received From '.($index+1).'	'.basename($filename).PHP_EOL;
	}
}

foreach ($files as $file){
	if ($idle === 0) merge_result();
	
	list($size,$filename) = explode(' ',$file,2);
	if ($size === '0') $hash_name[''][] = $filename;
	else {
		$command = 'sha1 '.$filename.PHP_EOL;
		end($read);
		$index = key($read) + 1;
		fwrite(array_pop($read),$command,strlen($command));
		#echo 'Sent To '.$index.'	'.basename($filename).PHP_EOL;
		$idle--;
	}
}
while ($idle < $children) merge_result();

for ($i = 0; $i < $children; $i++){
	fclose($child_sock[$i]);
}
for ($i = 0; $i < $children; $i++){
	$status = NULL;
	pcntl_waitpid($child_pid[$i],$status);
}

$file_count_final = array_reduce($hash_name,function($carry, $item){ return $carry += count($item);},0);

if ($file_count_initial !== $file_count_final){
	echo 'Processed '.$file_count_final.' Out Of '.$file_count_initial.' Files'.PHP_EOL;
	exit(1);
}
echo 'Processed '.$file_count_final.' Files'.PHP_EOL;
file_put_contents($cwd.'/db/hash_name'.strtr($wd,'/','_'),serialize($hash_name));

?>