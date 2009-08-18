<?php /*********************************************************************
 *
 *   Copyright : (C) 2007 Nicolas Grekas. All rights reserved.
 *   Email     : p@tchwork.org
 *   License   : http://www.gnu.org/licenses/agpl.txt GNU/AGPL
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU Affero General Public License as
 *   published by the Free Software Foundation, either version 3 of the
 *   License, or (at your option) any later version.
 *
 ***************************************************************************/


class patchwork_bootstrapper_updatedb__0
{
	static function buildPathCache($patchwork_path, $last, $cwd, $zcache)
	{
		$parentPaths = array();

		$error_level = error_reporting(0);

		if (file_exists($cwd . '.parentPaths.txt'))
		{
			rename($cwd . '.parentPaths.txt', $cwd . '.parentPaths.old');
			$old_db = fopen($cwd . '.parentPaths.old', 'rb');
		}
		else $old_db = false;

		$db = fopen($cwd . '.parentPaths.txt', 'wb');

		$path = array_flip($patchwork_path);
		unset($path[$cwd]);
		uksort($path, array(__CLASS__, 'dirCmp'));

		foreach ($path as $h => $level)
		{
			self::populatePathCache($old_db, $db, $parentPaths, $path, substr($h, 0, -1), $level, $last);
		}

		fclose($db);
		$old_db && fclose($old_db) && unlink($cwd . '.parentPaths.old');

		if (IS_WINDOWS)
		{
			$h = new COM('Scripting.FileSystemObject');
			$h->GetFile($cwd . '.parentPaths.txt')->Attributes |= 2; // Set hidden attribute
		}

		error_reporting($error_level);

		$db = $h = false;

		if (function_exists('dba_handlers'))
		{
			$h = array('cdb','db2','db3','db4','qdbm','gdbm','ndbm','dbm','flatfile','inifile');
			$h = array_intersect($h, dba_handlers());
			$h || $h = dba_handlers();
			@unlink($cwd . '.parentPaths.db');
			if ($h) foreach ($h as $db) if ($h = @dba_open($cwd . '.parentPaths.db', 'nd', $db, 0600)) break;
		}

		if ($h)
		{
			foreach ($parentPaths as $path => &$level)
			{
				sort($level);
				dba_insert($path, implode(',', $level), $h);
			}

			dba_close($h);

			if (IS_WINDOWS)
			{
				$h = new COM('Scripting.FileSystemObject');
				$h->GetFile($cwd . '.parentPaths.db')->Attributes |= 2; // Set hidden attribute
			}
		}
		else
		{
			$db = false;

			foreach ($parentPaths as $path => &$level)
			{
				$path = md5($path);
				$path = $path[0] . '/' . $path[1] . '/' . substr($path, 2) . '.path.txt';

				if (false === $h = @fopen($zcache . $path, 'wb'))
				{
					@mkdir($zcache . $path[0]);
					@mkdir($zcache . substr($path, 0, 3));
					$h = fopen($zcache . $path, 'wb');
				}

				sort($level);
				fwrite($h, implode(',', $level));
				fclose($h);
			}
		}

		return $db;
	}

	protected static function populatePathCache(&$old_db, &$db, &$parentPaths, &$path, $root, $level, $last, $prefix = '', $subdir = '/')
	{
		// Kind of updatedb with mlocate strategy

		$dir = $root . (IS_WINDOWS ? strtr($subdir, '/', '\\') : $subdir);

		static $old_db_line, $populated = array();

		if ('/' === $subdir)
		{
			if (isset($populated[$dir])) return;

			$populated[$dir] = true;

			if ($level > $last)
			{
				$prefix = '/class';
				$parentPaths['class'][] = $level;
			}
		}

		isset($old_db_line) || $old_db_line = $old_db ? fgets($old_db) : false;

		if (false !== $old_db_line)
		{
			do
			{
				$h = explode('*', $old_db_line, 2);
				false !== strpos($h[0], '%') && $h[0] = rawurldecode($h[0]);

				if (0 <= $h[0] = self::dirCmp($h[0], $dir))
				{
					if (0 === $h[0] && max(filemtime($dir), filectime($dir)) === (int) $h[1])
					{
						if ('/' !== $subdir && false !== strpos($h[1], '/0config.patchwork.php/'))
						{
							if (isset($path[$dir]))
							{
								$populated[$dir] = true;

								$root   = substr($dir, 0, -1);
								$subdir = '/';
								$level  = $path[$dir];

								if ($level > $last)
								{
									$prefix = '/class';
									$parentPaths['class'][] = $level;
								}
								else $prefix = '';
							}
							else break;
						}

						fwrite($db, $old_db_line);

						$h = explode('/', $h[1]);
						unset($h[0], $h[count($h)]);

						foreach ($h as $file)
						{
							$h = $file[0];

							$file = $subdir . substr($file, 1);
							$parentPaths[substr($prefix . $file, 1)][] = $level;

							$h && self::populatePathCache($old_db, $db, $parentPaths, $path, $root, $level, $last, $prefix, $file . '/');
						}

						return;
					}

					break;
				}
			}
			while (false !== $old_db_line = fgets($old_db));
		}

		if ($h = opendir($dir))
		{
			static $now;
			isset($now) || $now = time() - 1;

			$files = array();
			$dirs  = array();

			while (false !== $file = readdir($h)) if ('.' !== $file[0] && 'zcache' !== $file)
			{
				if (is_dir($dir . $file)) $dirs[] = $file;
				else
				{
					$files[] = $file;

					if ('config.patchwork.php' === $file && '/' !== $subdir)
					{
						if (isset($path[$dir]))
						{
							$populated[$dir] = true;

							$root   = substr($dir, 0, -1);
							$subdir = '/';
							$level  = $path[$dir];

							if ($level > $last)
							{
								$prefix = '/class';
								$parentPaths['class'][] = $level;
							}
							else $prefix = '';
						}
						else
						{
							closedir($h);
							return;
						}
					}
				}
			}

			closedir($h);

			ob_start();

			echo strtr($dir, array('%' => '%25', "\r" => '%0D', "\n" => '%0A', '*' => '%2A')),
				'*', min($now, max(filemtime($dir), filectime($dir))), '/';

			foreach ($files as $file)
			{
				echo '0', $file, '/';
				$parentPaths[substr($prefix . $subdir . $file, 1)][] = $level;
			}

			if ($dirs)
			{
				IS_WINDOWS || sort($dirs, SORT_STRING);

				echo '1', implode('/1', $dirs), '/';
			}

			echo "\n";

			fwrite($db, ob_get_clean());

			foreach ($dirs as $file)
			{
				$file = $subdir . $file;
				$parentPaths[substr($prefix . $file, 1)][] = $level;
				self::populatePathCache($old_db, $db, $parentPaths, $path, $root, $level, $last, $prefix, $file . '/');
			}
		}
	}

	static function dirCmp($a, $b)
	{
		$len = min(strlen($a), strlen($b));

		if (IS_WINDOWS)
		{
			$a = strtoupper(strtr($a, '\\', '/'));
			$b = strtoupper(strtr($b, '\\', '/'));
		}

		for ($i = 0; $i < $len; ++$i)
		{
			if ($a[$i] !== $b[$i])
			{
				if ('/' === $a[$i]) return -1;
				if ('/' === $b[$i]) return  1;
				return strcmp($a[$i], $b[$i]);
			}
		}

		return strlen($a) - strlen($b);
	}
}
