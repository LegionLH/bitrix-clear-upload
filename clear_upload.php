#!/usr/bin/env php
<?php set_time_limit(0); 

if (count($argv) < 2) {
    echo <<< USAGE

Usage: php clear_upload.php [--delete-files] [--move-files=/path/to] /path/to/document/root

Скрипт для очистки каталога upload/iblock от неиспользуемых файлов (оставшихся после удаления элемента инфоблока).
Проверяет каждый файл в каталоге upload/iblock, есть ли он в таблице b_file и если его там нет выводит полный 
путь к нему на экран.

Если указана опция --move-file=/путь, то перемещает файл в указанную директорию с сохранением иерархии.

Если указана опция --delete-files, то удаляет файл. В режиме удаления (с опцией --delete-files), 
если каталог, в котором находился удаляемый файл становится пустым - удаляет и его.


Примеры использования:

Получить список всех неиспользуемых файлов из каталога upload/iblock:
    
    php clear_upload.php /var/www/example.com
    
Переместить все неиспользуемые файлы из каталога upload/iblock в папку /backup:
    
    php clear_upload.php --move-files=/backup /var/www/example.com

Удалить все неиспользуемые файлы из каталога upload/iblock:
    
    php clear_upload.php --delete-files /var/www/example.com

USAGE;
    exit(0);
}

////////////////////////////////////////////////////////////////////////////////////////////

$command='';
$commandvalue='';

// Проверяем аргументы
if ( substr( $argv[1], 0, 2 ) === '--' ) {
	$argv[1] = substr( $argv[1], 2 );
	if (strpos($argv[1],'=')) {
		list($command,$commandvalue) = explode("=",$argv[1],2);
	} else {
		$command = $argv[1];
	}
}

$_SERVER['DOCUMENT_ROOT'] = $DOCUMENT_ROOT = count($argv) > 2 ? $argv[2] : $argv[1];

#define("LANG", "ru"); 
define("NO_KEEP_STATISTIC", true); 
define("NOT_CHECK_PERMISSIONS", true); 
 
$prolog = $_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php";
if (file_exists($prolog)) include($prolog); else die("Указанный каталог не является корневой директорией сайта на 1С-Битрикс" . PHP_EOL);

// Формируем кэш имен файлов на основе таблицы b_file.
$arFilesCache = array();
$result = $DB->Query('SELECT FILE_NAME, SUBDIR FROM b_file WHERE MODULE_ID = "iblock"');
while ($row = $result->Fetch()) {
    $arFilesCache[] = $row;
}

$rootDirPath = $_SERVER['DOCUMENT_ROOT'] . "/upload/iblock";
get_dir_contents($rootDirPath);

	//-------------------------------------
	
	function get_dir_contents( $dir ) {
		global $arFilesCache, $command, $commandvalue;
		if ( file_exists($dir) ) {
			if ( is_dir($dir) )	{
				$handle = opendir($dir);
				while (($filename = readdir($handle)) !== false) {
					if (($filename != ".") && ($filename != "..")) {
						if (is_dir($dir."/".$filename)) {
							get_dir_contents($dir."/".$filename);
						} else {
				               	$subDirName = str_replace($_SERVER['DOCUMENT_ROOT']."/upload/","",$dir);
						        if (in_array(array('FILE_NAME'=>$filename, 'SUBDIR'=>$subDirName), $arFilesCache)) {
						            continue;
						        }
						        if ($command=='delete-files') {
						            if (unlink($dir."/".$filename)) {
						                echo "Removed: " . $dir."/".$filename . PHP_EOL;
						            }
						        } elseif ($command=='move-files' && !empty($commandvalue)) {
									mkdir("$commandvalue/$subDirName",0775, true);
									if (rename($dir."/".$filename, "$commandvalue/$subDirName/$filename")) {
										echo "Move: " . $dir."/".$filename . PHP_EOL;
									}
								} else {
						            echo $dir."/".$filename . PHP_EOL;
						        }
						}
					}
				}
				closedir($handle);
		        if (!empty($command)) {
					//delete empty directory
					@rmdir($handle);
				}
			 }
		}
	}
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
