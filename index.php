<?php

setlocale(LC_CTYPE, 'en_US.UTF-8');

$debug_message = '';
$error_message = '';
$output = '';

$__data_prefix = '/mnt/disk/default/Multimedia/phpftp';

function error ($message)
{
	global $error_message;

	if (isset ($_SERVER['HTTP_HOST']))
		$error_message .= $message.'<br />';
	else
		$error_message .= $message.PHP_EOL;
}

function debug ($message)
{
	global $debug_message;

	if (isset ($_SERVER['HTTP_HOST']))
		$debug_message .= $message.'<br />';
	else
		$debug_message .= $message.PHP_EOL;
}

function error_handler($errno, $errstr, $errfile, $errline)
{
	$errortype = array (
		E_ERROR              => 'Error',
		E_WARNING            => 'Warning',
		E_PARSE              => 'Parsing Error',
		E_NOTICE             => 'Notice',
		E_CORE_ERROR         => 'Core Error',
		E_CORE_WARNING       => 'Core Warning',
		E_COMPILE_ERROR      => 'Compile Error',
		E_COMPILE_WARNING    => 'Compile Warning',
		E_USER_ERROR         => 'User Error',
		E_USER_WARNING       => 'User Warning',
		E_USER_NOTICE        => 'User Notice',
		E_STRICT             => 'Runtime Notice',
		E_RECOVERABLE_ERROR  => 'Catchable Fatal Error'
	);

	error ($errfile.':'.$errline.': '.'['.$errortype[$errno].'('.$errno.')] '.$errstr);

	return false;
}

// set to the user defined error handler
$old_error_handler = set_error_handler("error_handler");

function parse_options ($opts)
{
	// $opts = array (
	//     array (<reference of target variable>, <single character option key>, <true if boolean type>, <default value>),
	//     );

	if (isset ($_SERVER['HTTP_HOST']))
	{
		foreach ($opts as $opt)
		{
			if (isset ($_GET[$opt[1]]))
			{
				if ($opt[3])
				{
					if ($_GET[$opt[1]] == 'false')
						$opt[0] = false;
					else
						$opt[0] = true;
				}
				else
					$opt[0] = $_GET[$opt[1]];
			}
			else if (isset ($_GET[$opt[2]]))
			{
				if ($opt[3])
				{
					if ($_GET[$opt[2]] == 'false')
						$opt[0] = false;
					else
						$opt[0] = true;
				}
				else
					$opt[0] = $_GET[$opt[2]];
			}
			else
				$opt[0] = $opt[4];
		}
	}
	else
	{
		$o = '';
		$l = array();

		foreach ($opts as $opt)
		{
			$o .= $opt[1];
			if (!$opt[3])
				$o .= ':';

			if (!$opt[3])
				$l[] .= $opt[2].':';
			else
				$l[] .= $opt[2];
		}

		$options = getopt ($o, $l);

		foreach ($opts as $opt)
		{
			if (isset($options[$opt[1]]))
			{
				// short option
				if ($opt[3])
					$opt[0] = true;
				else
					$opt[0] = $options[$opt[1]];
			}
			else if (isset ($options[$opt[2]]))
			{
				// long option
				if ($opt[3])
					$opt[0] = true;
				else
					$opt[0] = $options[$opt[2]];
			}
			else
				$opt[0] = $opt[4];
		}
	}
}

function l($disp, $addr, $args=array())
{
	$ret = '<a href="'.$addr;

	if (count ($args))
	{
		$ret .= '?';
		foreach ($args as $name => $value)
			$ret .= $name.'='.$value.'&';
	}

	$ret .= '">'.$disp.'</a>';

	return $ret;
}

function array_to_vars ($array, $temps)
{
	// $temps = array (
	//     array (<reference of target variable>, <a key on the $array>, <default value when there is no such a key>),
	//     );

	foreach ($temps as $temp)
	{
		if (isset($array[$temp[1]]))
			$temp[0] = $array[$temp[1]];
		else
			$temp[0] = $temp[2];
	}
}

function ftp_connect_login ()
{
	global $ftp_host, $ftp_port, $ftp_user, $ftp_pass;
	global $pasv;

	$f = ftp_connect ($ftp_host, $ftp_port, 10);
	if ($f && ftp_login ($f, $ftp_user, $ftp_pass) == true)
		ftp_pasv ($f, $pasv);

	return $f;
}

function ftp_list ($f, $p)
{
	$lines = array ();

	$list = ftp_rawlist ($f, $p);
	if ($list == false)
		return $lines;

	foreach ($list as $i)
	{
		$line = preg_split ('/[ ]+/', $i, 9);
		$lines[] = $line;
	}

	return $lines;
}

function ftp_get_size ($f, $p)
{
	$total = 0;

	$lines = ftp_list ($f, $p);
	foreach ($lines as $line)
	{
		if ($line[0][0] == 'd')
		{
			$ret = ftp_get_size ($f, $p . '/' . $line[8]);
			$size = $ret['size'];
		}
		else
			$size = $line[4];
		$total += $size;
	}

	// we always handle 'directory' type now. lets implement a file.
	return array('size'=>$total,'type'=>'directory');
}

function download_status ()
{
	global $url, $pasv, $encoding;

	$out = '';
	$out .= '<div id="status_list"><table class="status_list">';

	for ($a=0; $a<100; $a++)
	{
		$stat_dir = dirname (__FILE__).'/stat/'.$a;
		if (!is_dir ($stat_dir))
			continue;
		if (is_file ($stat_dir.'/no_report'))
			continue;

		$out .= '<tr />';
		$out .= '<td />'.$a;
		$out .= '<td />'.file_get_contents ($stat_dir.'/time_start');
		if (is_file ($stat_dir.'/time_end'))
			$out .= '<td />'.file_get_contents ($stat_dir.'/time_end');
		else
			$out .= '<td />no end';
		$out .= '<td />'.(is_file($stat_dir.'/stat')?file_get_contents ($stat_dir.'/stat'):'');
		$out .= '<td />'.l('log', 'stat/'.$a.'/log');
		if (is_file ($stat_dir.'/pid'))
		{
			$pid = file_get_contents ($stat_dir.'/pid');
			if (is_dir ('/proc/'.$pid))
			{
				$out .= '<td />running';
				$out .= '<td />'.l('kill', '.', array (
					'url' => $url!=false?$url:'false',
					'pasv' => $pasv?'true':'false',
					'encoding' => $encoding,
					'kill' => $pid,
				));
			}
			else
			{
				$out .= '<td />unknown';
				$out .= '<td />'.l('continue', '.', array (
					'url' => $url!=false?$url:'false',
					'pasv' => $pasv?'true':'false',
					'encoding' => $encoding,
					'save' => 'true',
					'continue' => $a,
				));
			}
		}
		else
		{
			$out .= '<td />done';
			$out .= '<td />'.l('del', '.', array (
				'url' => $url!=false?$url:'false',
				'pasv' => $pasv?'true':'false',
				'encoding' => $encoding,
				'remove' => $a,
			));
		}
	}

	if (isset ($_SERVER['HTTP_HOST']))
		$out .= '</table></div>';

	return $out;
}

function ftp_list_directory ($p, $encoding = 'UTF-8')
{
	global $url, $pasv;
	global $ftp_prot, $ftp_user, $ftp_pass, $ftp_host, $ftp_path, $ftp_port;
	global $pasv, $use_cache;

	$cache_file = dirname (__FILE__).'/cache/'.md5($url.' pasv:'.($pasv?'true':'false').' encoding:'.$encoding);
	if (!$use_cache || !is_file ($cache_file))
	{
		$f = ftp_connect_login ();
		if ($f == false)
			error ('failed to connect/login to the server. use cache...');
	}
	else
		$f = false;

	if (isset ($_SERVER['HTTP_HOST']))
	{
		$out = '<div id="ftp_list"><div id="ftp_current">directory "'.$p.'" on '.$ftp_host;
		$out .= ($f==false?(' using cache. '.l('update cache', '.', array(
			'url'=>"$ftp_prot://$ftp_user:$ftp_pass@$ftp_host:$ftp_port/".$p,
			'pasv'=>$pasv?'true':'false',
			'encoding'=>$encoding,
			'use_cache'=>'false',
			))):'').'</div>';

		$out .= '<table class="ftp_list"><tr /><td />&nbsp;<td colspan="5"/>';
		$out .= l('[up to higher level directory]', '.', array(
			'url'=>"$ftp_prot://$ftp_user:$ftp_pass@$ftp_host:$ftp_port".($p==''?'':'/'.$p)."/..",
			'pasv'=>$pasv?'true':'false',
			'encoding'=>$encoding,
		));
	}
	else
		$out = 'path:'.$p.PHP_EOL;

	if ($f)
	{
		$list = ftp_list ($f, iconv ('UTF-8', $encoding, $p));
		file_put_contents ($cache_file, serialize ($list));
	}
	else
		$list = unserialize (file_get_contents ($cache_file));
	foreach ($list as $line)
	{
		if (isset ($_SERVER['HTTP_HOST']))
			$out .= '<tr />';

		$line[8] = iconv ($encoding, 'UTF-8', $line[8]);
		if (isset ($_SERVER['HTTP_HOST']))
		{
			$url_dest = "$ftp_prot://$ftp_user:$ftp_pass@$ftp_host:$ftp_port".($p==''?'':'/'.$p)."/$line[8]";

			$out .= '<td />'.l ('down','.',
				array(
				'url'=>$url_dest,
				'pasv'=>$pasv?'true':'false',
				'encoding'=>$encoding,
				'confirm'=>'true',
				'filesize'=>$line[0][0]=='d'?'-1':$line[4],
			));

			$out .= '<td />';
			if ($line[0][0] == 'd')
				$out .= l ('['.$line[8].']',
					'.',
					array(
						'url'=>$url_dest,
						'pasv'=>$pasv?'true':'false',
						'encoding'=>$encoding,
					));
			else
				$out .= $line[8];

			$out .= '<td class="list_size" />'.number_format ($line[4]);
			$out .= '<td />'.$line[5];
			$out .= '<td />'.$line[6];
			$out .= '<td />'.$line[7];
			//$out .= '<td />'.$line[0];
			//$out .= '<td />'.$line[1];
			//$out .= '<td />'.$line[2];
			//$out .= '<td />'.$line[3];
		}
		else
		{
			$out .= vsprintf ('%s %s %8s %8s %10s %s %s %s %s'.PHP_EOL, $line);
		}
	}

	if (isset ($_SERVER['HTTP_HOST']))
		$out .= '</table></div><hr />';
	$out .= download_status ();

	return $out;
}

function confirm_download ($p, $encoding = 'UTF-8')
{
	global $ftp_prot, $ftp_user, $ftp_pass, $ftp_host, $ftp_path, $ftp_port;
	global $pasv, $its_file;

	$url = "$ftp_prot://$ftp_user:$ftp_pass@$ftp_host:$ftp_port".($p==''?'':'/'.$p);

	if ($its_file < 0)
	{
		$f = ftp_connect_login ();

		$ret = ftp_get_size ($f, iconv ('UTF-8', $encoding, $p));
		$size = $ret['size'];
		$type = $ret['type'];
	}
	else
	{
		$size = $its_file;
		$type = 'file';
	}

	$out = number_format($size).' bytes of '.$type.' from "'.$url.'".';
	if (isset ($_SERVER['HTTP_HOST']))
		$out .= '<br />';
	else
		$out .= PHP_EOL;

	$out .= 'Do you want to download the '.$type.' into the server?';
	if (isset ($_SERVER['HTTP_HOST']))
		$out .= '<br />';
	else
		$out .= PHP_EOL;
	$out .= l ('yes', '.',
		array(
			'url'=>$url,
			'pasv'=>$pasv?'true':'false',
			'encoding'=>$encoding,
			'save'=>'true',
			'filesize'=>$its_file,
		));
	$out .= '('.l ('no report', '.',
		array(
			'url'=>$url,
			'pasv'=>$pasv?'true':'false',
			'encoding'=>$encoding,
			'save'=>'true',
			'filesize'=>$its_file,
			'no_report'=>true,
		)).')';
	$out .= ' or ';
	$out .= l ('no', 'javascript:history.go(-1);');

	return $out;
}

function log_stat ($now, $total, $msg)
{
	global $stat_dir;

	$f = fopen ($stat_dir.'/stat.tmp','w');
	//fwrite ($f, '('.$now.'/'.$total.') '.$msg);
	if (function_exists('gmp_strval'))
		fwrite ($f, '('.number_format(gmp_strval($now)).'/'.number_format($total).')');
	else
		fwrite ($f, '('.number_format($now).'/'.number_format($total).')');
	fclose ($f);
	rename ($stat_dir.'/stat.tmp', $stat_dir.'/stat');
}

function ftp_download_file ($f, $p, $l)
{
	global $downloaded, $total_size, $current_filename;
	global $encoding;

	echo "download file $p to $l:";

	$pos_pre = 0;

	$fp = fopen($l, 'a');

	// Initate the download
	$packcnt_ = 0;
	$packcnt = 0;
	$packeach = 1;
	$markcnt = 0;
	$ret = ftp_nb_fget($f, $fp, iconv ('UTF-8', $encoding, $p), FTP_BINARY, FTP_AUTORESUME);
	while ($ret == FTP_MOREDATA)
	{
		$pos = ftell ($fp);
		if (function_exists ('gmp_add'))
			$downloaded = gmp_add ($downloaded, $pos - $pos_pre);
		else
			$downloaded += $pos - $pos_pre;
		$pos_pre = $pos;

		// print mark, status
		if (++ $packcnt >= $packeach)
		{
			$packcnt = 0;
			echo ".";
			if (++ $markcnt >= 100)
			{
				$markcnt = 0;
				$packeach *= 100;;
				echo '--packeach:'.$packeach.'--';
			}
		}
		if (($packcnt_ ++ % 100) == 0)
			log_stat ($downloaded, $total_size, $current_filename);

		// Continue downloading...
		$ret = ftp_nb_continue($f);
	}
	$pos = ftell ($fp);
	if (function_exists ('gmp_add'))
		$downloaded = gmp_add ($downloaded, $pos - $pos_pre);
	else
		$downloaded += $pos - $pos_pre;

	if ($ret != FTP_FINISHED) {
		echo "There was an error downloading the file...";
		exit(1);
	}

	// close filepointer
	fclose($fp);

	echo " done.\n";
}

function ftp_download_dir ($f, $p, $l, $list = null)
{
	global $do_save, $recursive, $encoding;
	global $current_filename;

	echo 'go  into dir  ', $p, ' to ', $l, PHP_EOL;
	if ($list == null)
		$list = ftp_list ($f, iconv ('UTF-8', $encoding, $p));

	foreach ($list as $line)
	{
		$arg = $line;
		$arg[8] = iconv ($encoding, 'UTF-8', $arg[8]);
		vprintf ("%s %s %8s %8s %10s %s %s %s %s\n", $arg);
	}

	foreach ($list as $line)
	{
		$current_filename = iconv ($encoding, 'UTF-8', $line[8]);
		$full_path = $p . '/' . $current_filename;
		$dest = $l . '/' . $current_filename;
		if ($line[0][0] == 'd')
		{
			if (!is_dir ($dest) && !mkdir ($dest, 0777, true))
			{
				echo "mkdir $dest failed.\n";
				return;
			}

			if ($recursive == true)
				ftp_download_dir ($f, $full_path, $dest);
		}
		else
			ftp_download_file ($f, $full_path, $dest);
	}
}

function ftp_download ($p, $l)
{
	global $do_save, $encoding, $its_file;
	global $downloaded, $total_size, $current_filename;
	global $stat_dir;
	global $url;

	// connect ftp
	$f = ftp_connect_login ();
	echo 'connected.'.PHP_EOL;

	// save my pid, starttime
	$pid_f = fopen ($stat_dir.'/pid', 'w');
	fwrite ($pid_f, posix_getpid ());
	fclose ($pid_f);
	$time_f = fopen ($stat_dir.'/time_start', 'w');
	fwrite ($time_f, date ('Y/n/j H:i:s'));
	fclose ($time_f);
	$url_f = fopen ($stat_dir.'/url', 'w');
	fwrite ($url_f, $url);
	fclose ($url_f);
	$encoding_f = fopen ($stat_dir.'/encoding', 'w');
	fwrite ($encoding_f, $encoding);
	fclose ($encoding_f);
	echo 'starttime: '.date ('Y/n/j H:i:s').PHP_EOL;

	// determin download size
	if ($its_file < 0)
	{
		echo 'its directory'.PHP_EOL;
		$ret = ftp_get_size ($f, iconv ('UTF-8', $encoding, $p));
		$total_size = $ret['size'];
	}
	else
		$total_size = $its_file;
	echo 'total size: ', $total_size, PHP_EOL;
	$downloaded = 0;
	$current_filename = '';

	// check download directory
	if (!is_dir ($l))
		mkdir ($l, 0777, true);

	// download...
	$paths = explode ('/', $p);
	$dest = $l . '/' . $paths[count($paths)-1];
	if ($its_file >= 0)
	{
		echo "single file.\n";

		ftp_download_file ($f, $p, $dest);
	}
	else
	{
		echo "directory.\n";

		$list = ftp_list ($f, iconv ('UTF-8', $encoding, $p));
		if (!is_dir ($dest) && !mkdir ($dest, 0777, true))
		{
			echo "mkdir $dest failed.\n";
			return;
		}
		ftp_download_dir ($f, $p, $dest, $list);
	}

	$time_f = fopen ($stat_dir.'/time_end', 'w');
	fwrite ($time_f, date ('Y/n/j H:i:s'));
	fclose ($time_f);
	echo 'endtime: '.date ('Y/n/j H:i:s').PHP_EOL;

	// remove pid file
	unlink ($stat_dir.'/pid');

	touch ($l.'/__download_completed__');
}

function rrmdir($dir)
{
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (filetype($dir."/".$object) == "dir") rrmdir($dir."/".$object); else unlink($dir."/".$object);
			}
		}
		reset($objects);
		rmdir($dir);
	}
}

/* parse options */
$args_temp = array (
	array (&$get_src,  'g', 'getsrc',    true,  false,   '             : get this script source'                      ),
	array (&$pasv,     'p', 'pasv',      true,  false,   '             : Pasv connection'                             ),
	array (&$do_conf,  'c', 'confirm',   true,  false,   '             : Confirm the download of url'                 ),
	array (&$use_cache,'C', 'use_cache', true,  true,    '             : use cached data to list the directory'       ),
	array (&$do_save,  's', 'save',      true,  false,   '             : Save the url'                                ),
	array (&$do_down,  'D', 'download',  true,  false,   '             : Save the url, used to indecate background process'),
	array (&$recursive,'r', 'recursive', true,  false,   '             : Recursive to the directory'                  ),
	array (&$no_report,'n', 'no_report', true,  false,   '             : do not report status when downloading'       ),
	array (&$its_file, 'f', 'filesize',  false, -1,      '<file length>: download file size if its file, works with -D or -c'),
	array (&$encoding, 'e', 'encoding',  false, 'UTF-8', '<encoding>   : set Encoding of filenames, default: UTF-8'   ),
	array (&$save_dir, 'd', 'localdir',  false, '.',     '<directory>  : local Directory to save, default: "./"'      ),
	array (&$url,      'u', 'url',       false, false,   '<url>        : url to connect, ex: ftp://localhost/dir/file'),
	array (&$stat_dir ,'S', 'statedir',  false, './stat','<directory>  : directory to store stat, log, starttime, pid...'),
	array (&$remove_id,'R', 'remove',    false, -1,      '<id>         : remove a log'),
	array (&$kill,     'k', 'kill',      false, -1,      '<pid>        : kill the pid'),
	array (&$continue, 't', 'continue',  false, -1,      '<id>         : continue to download. works with "save"'),
);
parse_options ($args_temp);

if ($get_src)
{
	header('Content-Type: document/text');
	header('Content-Disposition: inline; filename=phpftp.php');
	header('Content-Length: ' . filesize(__FILE__));
	readfile (__FILE__);
	exit (0);
}

if ($kill > 0)
{
	debug ('killing '.$kill);
	posix_kill ($kill, 9);
}

if ($remove_id >= 0)
{
	$stat_dir = dirname (__FILE__).'/stat/'.$remove_id;
	if (is_dir ($stat_dir))
	{
		$data_dir = $__data_prefix.'/'.$remove_id;
		if (is_dir ($data_dir))
		{
			debug ('data_dir: '.$data_dir);
			for ($a=0; $a<100; $a++)
			{
				$dest_dir = $__data_prefix.'/store_'.$a;
				if (is_dir($dest_dir))
					continue;
				if (rename ($data_dir, $dest_dir))
					break;
			}
			if ($a == 100)
				error ('cannot move data directory. '.$data_dir);
			else
				debug ('moved '.$data_dir.' to '.$__data_prefix.'/store_'.$a);
		}
		rrmdir ($stat_dir);
	}
	else
		error ('unknown log id. '.$remove_id);

	if ($url != false)
	{
		$output .= 'go to '.l('privious page','.',
			array (
				'url'=>$url,
				'pasv'=>$pasv?'true':'false',
				'encoding'=>$encoding,
			)).'.';
	}
}

if ($url == false)
{
	if (true)
	{
		$output .= download_status ();
		$output .= '<hr />'.l('get script source','.',array('g'=>'true'));
	}
	else
	{
		error (' '. basename(__FILE__). ' <options> -u <url>');
		error ('  <options>');
		foreach ($args_temp as $arg)
			error ('   -'.$arg[1].' '.$arg[5]);

		error ('no url specified.');
	}
}
else if ($do_save)
{
	$stat_dir = dirname (__FILE__).'/stat';

	if (!is_dir ($stat_dir))
		mkdir ($stat_dir);

	if (!is_dir ($stat_dir))
	{
		error ('cannat make directory, '.$stat_dir);
		return 'failed';
	}

	if ($continue < 0)
	{
		for ($id=0; $id<100; $id++)
		{
			if (is_dir ($stat_dir.'/'.$id))
				continue;

			if (mkdir ($stat_dir.'/'.$id) == true)
				break;
		}
		if ($id == 100)
		{
			error ('cannot get download ticket');
			return 'failed';
		}

		/* save some variables like $url, $encoding to use in 
		 * 'continue' command */
	}
	else
	{
		/* reload $url, $encoding */
		$id = $continue;
	}

	$stat_dir .= '/'.$id;

	if ($no_report)
		touch ($stat_dir.'/no_report');

	$cmd = 'nohup php '.__FILE__;
	if ($pasv) $cmd .= ' --pasv';
	$cmd .= ' --download --recursive';
	$cmd .= ' --localdir '.$__data_prefix.'/'.$id;
	$cmd .= ' --encoding '.escapeshellarg($encoding);
	$cmd .= ' --url '.escapeshellarg($url);
	$cmd .= ' --statedir '.$stat_dir;
	$cmd .= ' --filesize '.$its_file;
	$cmd .= ' > '.$stat_dir.'/log';
	$cmd .= ' 2> '.$stat_dir.'/log';
	$cmd .= ' < /dev/null &';

	exec ($cmd);
	debug ('cmd: '.$cmd);
	debug ('statdir: '.$stat_dir);
	debug ('id: '.$id);
}
else
{
	$urls_temp = array (
		array (&$ftp_prot, 'scheme', 'ftp'),
		array (&$ftp_user, 'user',   'anonymous'),
		array (&$ftp_pass, 'pass',   'phpftp_client'),
		array (&$ftp_host, 'host',   'localhost'),
		array (&$ftp_path, 'path',   ''),
		array (&$ftp_port, 'port',   21),
	);

	$urls = parse_url ($url);
	array_to_vars ($urls, $urls_temp);

	// remove starting '/', '..' on $ftp_path
	$ftp_path2 = array();
	foreach (preg_split ('/\//', $ftp_path) as $a)
	{
		if ($a == '')
			continue;
		if ($a == '.')
			continue;
		if ($a == '..')
		{
			if (count($ftp_path2) && $ftp_path2[count($ftp_path2)-1] != '..')
				array_pop ($ftp_path2);
			else
				$ftp_path2[] = $a;
		}
		else
			$ftp_path2[] = $a;
	}
	$ftp_path = '';
	foreach ($ftp_path2 as $a)
	{
		if ($ftp_path != '')
			$ftp_path .= '/';
		$ftp_path .= $a;
	}
	$url = "$ftp_prot://$ftp_user:$ftp_pass@$ftp_host:$ftp_port".($ftp_path==''?'':'/'.$ftp_path);

	if ($do_conf)
		$output .= confirm_download ($ftp_path, $encoding);
	else if ($do_down)
		$output .= ftp_download ($ftp_path, $save_dir);
	else
		$output .= ftp_list_directory ($ftp_path, $encoding);
}

if (isset ($output) || isset ($debug_message) || isset ($error_message))
{
	if (isset ($_SERVER['HTTP_HOST']))
	{
		echo '<html><head>';
		echo '<meta name="viewport" ';
		echo 'content="user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, width=device-width" />';
		echo '<style type="text/css">';
		echo 'td.list_size { text-align: right; }';
		echo 'table { border-collapse: collapse; }';
		echo 'table tr td { border: 1px solid black; }';
		echo '</style>';
		echo '</head><body>';
	}

	if ($error_message != '')
	{
		if (isset ($_SERVER['HTTP_HOST']))
			echo '<div id="error">', $error_message, '</div>';
		else
			echo $error_message;
	}

	if ($debug_message != '')
	{
		if (isset ($_SERVER['HTTP_HOST']))
			echo '<div id="debug">', $debug_message, '</div>';
		else
			echo $debug_message;
	}

	if (isset ($output))
		echo $output;

	if (isset ($_SERVER['HTTP_HOST']))
		echo '</body></html>';
}

?>
