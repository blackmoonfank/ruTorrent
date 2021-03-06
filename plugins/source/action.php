<?php
require_once( '../../php/rtorrent.php' );

if(isset($_REQUEST['result']))
	cachedEcho('noty(theUILang.cantFindTorrent,"error");',"text/html");
if(isset($_REQUEST['hash']))
{
	$query = urldecode($_REQUEST['hash']);
	$hashes = explode(" ", $query);
	if(count($hashes) == 1)
	{
		$torrent = rTorrent::getSource($_REQUEST['hash']);
		if($torrent)
			$torrent->send();
	}
	else
	{
		if(!class_exists('ZipArchive'))
			cachedEcho('noty("PHP module \'zip\' is not installed.","error");',"text/html");
		foreach($hashes as $hash)
		{
			$req = new rXMLRPCRequest( array(
				new rXMLRPCCommand("get_session"),
				new rXMLRPCCommand("d.get_tied_to_file",$hash)) );
			if($req->run() && !$req->fault)
			{
				$fname = $req->val[0].$hash.".torrent";
				if(empty($req->val[0]) || !is_readable($fname))
				{
					if(strlen($req->val[1]) && is_readable($req->val[1]))
						$fname = $req->val[1];
					else
						$fname = null;
				}
				if($fname)
				{
					$filepaths[] = $fname;
					$files[] = new Torrent( $fname );
				}
			}
		}
		if(isset($files))
		{
			ignore_user_abort(true);
			set_time_limit(0);

			$fn = 1;
			$zipname = uniqid("torrents-".getUser()."-").".zip";
			$zippath = getTempDirectory().$zipname;
			$zip = new ZipArchive;
			$zip->open($zippath, ZipArchive::CREATE);
			foreach(array_combine($filepaths, $files) as $filepath => $file)
			{
				$filename = $file->info['name']."-".$fn.".torrent";
				$zip->addFile($filepath, $filename);

				$fn++;
			}
			$zip->close();

			if(sendFile($zippath, "application/zip", $zipname, false))
				unlink($zippath);

			exit();
		}
	}
}
header("HTTP/1.0 302 Moved Temporarily");
header("Location: action.php?result=0");
