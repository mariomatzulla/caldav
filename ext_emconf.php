<?php

########################################################################
# Extension Manager/Repository config file for ext "caldav".
#
# Auto generated 03-05-2011 15:04
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'CalDAV',
	'description' => 'Extends Calendar Base with CalDAV functionality.',
	'category' => 'plugin',
	'author' => 'Mario Matzulla',
	'author_email' => 'mario@matzullas.de',
	'shy' => '',
	'dependencies' => 'cal',
	'conflicts' => '',
	'priority' => '',
	'module' => '',
	'state' => 'beta',
	'internal' => '',
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => '',
	'clearCacheOnLoad' => 1,
	'lockType' => '',
	'author_company' => '',
	'version' => '0.0.1',
	'constraints' => array(
		'depends' => array(
			'cal' => '1.4.0',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'_md5_values_when_last_written' => 'a:114:{s:9:"ChangeLog";s:4:"30c4";s:9:"_htaccess";s:4:"58d9";s:10:"caldav.php";s:4:"3755";s:16:"ext_autoload.php";s:4:"a579";s:21:"ext_conf_template.txt";s:4:"61d0";s:12:"ext_icon.gif";s:4:"986e";s:17:"ext_localconf.php";s:4:"ee40";s:14:"ext_tables.php";s:4:"a0fd";s:14:"ext_tables.sql";s:4:"65fd";s:16:"locallang_db.xml";s:4:"1797";s:14:"doc/manual.sxw";s:4:"15ce";s:48:"hooks/class.tx_caldav_tcemain_processdatamap.php";s:4:"617b";s:13:"lib/ChangeLog";s:4:"0cbe";s:11:"lib/LICENSE";s:4:"fe15";s:22:"lib/Sabre.autoload.php";s:4:"3943";s:22:"lib/Sabre.includes.php";s:4:"0239";s:22:"lib/Sabre/autoload.php";s:4:"0bbb";s:29:"lib/Sabre/CalDAV/Calendar.php";s:4:"4963";s:35:"lib/Sabre/CalDAV/CalendarObject.php";s:4:"ab9c";s:37:"lib/Sabre/CalDAV/CalendarRootNode.php";s:4:"74d9";s:34:"lib/Sabre/CalDAV/ICalendarUtil.php";s:4:"5f71";s:27:"lib/Sabre/CalDAV/Plugin.php";s:4:"73d7";s:27:"lib/Sabre/CalDAV/Server.php";s:4:"e33e";s:32:"lib/Sabre/CalDAV/TYPO3Server.php";s:4:"2701";s:34:"lib/Sabre/CalDAV/UserCalendars.php";s:4:"4c2f";s:28:"lib/Sabre/CalDAV/Version.php";s:4:"2e25";s:28:"lib/Sabre/CalDAV/XMLUtil.php";s:4:"6028";s:37:"lib/Sabre/CalDAV/Backend/Abstract.php";s:4:"c8c0";s:32:"lib/Sabre/CalDAV/Backend/PDO.php";s:4:"84cb";s:34:"lib/Sabre/CalDAV/Backend/TYPO3.php";s:4:"4781";s:53:"lib/Sabre/CalDAV/Exception/InvalidICalendarObject.php";s:4:"199e";s:59:"lib/Sabre/CalDAV/Property/SupportedCalendarComponentSet.php";s:4:"5a5a";s:51:"lib/Sabre/CalDAV/Property/SupportedCalendarData.php";s:4:"5fa6";s:51:"lib/Sabre/CalDAV/Property/SupportedCollationSet.php";s:4:"d6b0";s:27:"lib/Sabre/DAV/Directory.php";s:4:"b7fd";s:27:"lib/Sabre/DAV/Exception.php";s:4:"714b";s:22:"lib/Sabre/DAV/File.php";s:4:"494c";s:29:"lib/Sabre/DAV/ICollection.php";s:4:"02de";s:37:"lib/Sabre/DAV/IExtendedCollection.php";s:4:"d793";s:23:"lib/Sabre/DAV/IFile.php";s:4:"ec5f";s:27:"lib/Sabre/DAV/ILockable.php";s:4:"a06a";s:23:"lib/Sabre/DAV/INode.php";s:4:"1632";s:29:"lib/Sabre/DAV/IProperties.php";s:4:"2667";s:24:"lib/Sabre/DAV/IQuota.php";s:4:"c9bf";s:22:"lib/Sabre/DAV/Node.php";s:4:"4269";s:28:"lib/Sabre/DAV/ObjectTree.php";s:4:"6f44";s:26:"lib/Sabre/DAV/Property.php";s:4:"bb43";s:24:"lib/Sabre/DAV/Server.php";s:4:"d700";s:30:"lib/Sabre/DAV/ServerPlugin.php";s:4:"e8d5";s:33:"lib/Sabre/DAV/SimpleDirectory.php";s:4:"28e8";s:43:"lib/Sabre/DAV/TemporaryFileFilterPlugin.php";s:4:"c9e4";s:22:"lib/Sabre/DAV/Tree.php";s:4:"e618";s:25:"lib/Sabre/DAV/URLUtil.php";s:4:"6171";s:25:"lib/Sabre/DAV/Version.php";s:4:"2e28";s:25:"lib/Sabre/DAV/XMLUtil.php";s:4:"1e75";s:29:"lib/Sabre/DAV/Auth/Plugin.php";s:4:"c8a5";s:32:"lib/Sabre/DAV/Auth/Principal.php";s:4:"1bd8";s:42:"lib/Sabre/DAV/Auth/PrincipalCollection.php";s:4:"8659";s:39:"lib/Sabre/DAV/Auth/Backend/Abstract.php";s:4:"862a";s:44:"lib/Sabre/DAV/Auth/Backend/AbstractBasic.php";s:4:"259f";s:45:"lib/Sabre/DAV/Auth/Backend/AbstractDigest.php";s:4:"f214";s:37:"lib/Sabre/DAV/Auth/Backend/Apache.php";s:4:"663f";s:35:"lib/Sabre/DAV/Auth/Backend/File.php";s:4:"0a14";s:34:"lib/Sabre/DAV/Auth/Backend/PDO.php";s:4:"061a";s:36:"lib/Sabre/DAV/Auth/Backend/TYPO3.php";s:4:"8c30";s:42:"lib/Sabre/DAV/Browser/GuessContentType.php";s:4:"8e42";s:42:"lib/Sabre/DAV/Browser/MapGetToPropFind.php";s:4:"d7f7";s:32:"lib/Sabre/DAV/Browser/Plugin.php";s:4:"c029";s:38:"lib/Sabre/DAV/Exception/BadRequest.php";s:4:"edc2";s:36:"lib/Sabre/DAV/Exception/Conflict.php";s:4:"7c59";s:43:"lib/Sabre/DAV/Exception/ConflictingLock.php";s:4:"23db";s:40:"lib/Sabre/DAV/Exception/FileNotFound.php";s:4:"1da2";s:37:"lib/Sabre/DAV/Exception/Forbidden.php";s:4:"de85";s:47:"lib/Sabre/DAV/Exception/InsufficientStorage.php";s:4:"2dcd";s:47:"lib/Sabre/DAV/Exception/InvalidResourceType.php";s:4:"f1a7";s:54:"lib/Sabre/DAV/Exception/LockTokenMatchesRequestUri.php";s:4:"30d6";s:34:"lib/Sabre/DAV/Exception/Locked.php";s:4:"68ad";s:44:"lib/Sabre/DAV/Exception/MethodNotAllowed.php";s:4:"633a";s:44:"lib/Sabre/DAV/Exception/NotAuthenticated.php";s:4:"1d72";s:42:"lib/Sabre/DAV/Exception/NotImplemented.php";s:4:"2d2c";s:46:"lib/Sabre/DAV/Exception/PreconditionFailed.php";s:4:"7a6b";s:48:"lib/Sabre/DAV/Exception/ReportNotImplemented.php";s:4:"9807";s:56:"lib/Sabre/DAV/Exception/RequestedRangeNotSatisfiable.php";s:4:"a66f";s:48:"lib/Sabre/DAV/Exception/UnsupportedMediaType.php";s:4:"e149";s:30:"lib/Sabre/DAV/FS/Directory.php";s:4:"c377";s:25:"lib/Sabre/DAV/FS/File.php";s:4:"a10d";s:25:"lib/Sabre/DAV/FS/Node.php";s:4:"a222";s:33:"lib/Sabre/DAV/FSExt/Directory.php";s:4:"0487";s:28:"lib/Sabre/DAV/FSExt/File.php";s:4:"f8ca";s:28:"lib/Sabre/DAV/FSExt/Node.php";s:4:"5e29";s:32:"lib/Sabre/DAV/Locks/LockInfo.php";s:4:"ba20";s:30:"lib/Sabre/DAV/Locks/Plugin.php";s:4:"4fdf";s:40:"lib/Sabre/DAV/Locks/Backend/Abstract.php";s:4:"3ba3";s:34:"lib/Sabre/DAV/Locks/Backend/FS.php";s:4:"5d52";s:35:"lib/Sabre/DAV/Locks/Backend/PDO.php";s:4:"c36a";s:30:"lib/Sabre/DAV/Mount/Plugin.php";s:4:"f683";s:42:"lib/Sabre/DAV/Property/GetLastModified.php";s:4:"5c93";s:31:"lib/Sabre/DAV/Property/Href.php";s:4:"57e4";s:32:"lib/Sabre/DAV/Property/IHref.php";s:4:"1f85";s:40:"lib/Sabre/DAV/Property/LockDiscovery.php";s:4:"5262";s:36:"lib/Sabre/DAV/Property/Principal.php";s:4:"423c";s:39:"lib/Sabre/DAV/Property/ResourceType.php";s:4:"717c";s:35:"lib/Sabre/DAV/Property/Response.php";s:4:"c4dc";s:40:"lib/Sabre/DAV/Property/SupportedLock.php";s:4:"8aa1";s:45:"lib/Sabre/DAV/Property/SupportedReportSet.php";s:4:"1ca8";s:33:"lib/Sabre/DAV/Tree/Filesystem.php";s:4:"e562";s:26:"lib/Sabre/HTTP/AWSAuth.php";s:4:"a9bf";s:31:"lib/Sabre/HTTP/AbstractAuth.php";s:4:"4e16";s:28:"lib/Sabre/HTTP/BasicAuth.php";s:4:"4950";s:29:"lib/Sabre/HTTP/DigestAuth.php";s:4:"dbe5";s:26:"lib/Sabre/HTTP/Request.php";s:4:"8055";s:27:"lib/Sabre/HTTP/Response.php";s:4:"6dd2";s:23:"lib/Sabre/HTTP/Util.php";s:4:"5f79";s:26:"lib/Sabre/HTTP/Version.php";s:4:"08dd";}',
	'suggests' => array(
	),
);

?>