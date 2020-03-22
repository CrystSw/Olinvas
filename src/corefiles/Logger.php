<?php
namespace Olinvas;

class Logger {
	private $useSyslog;
	private $logOutputDirectory;
	
	public function __construct($useSyslog = false, $logOutputDirectory = '/var/log') {
		$this->useSyslog = $useSyslog;
		$this->logOutputDirectory = $logOutputDirectory;
	}
	
	public function printLog($priority, $message) {
		switch($priority) {
			case LOG_EMERG:
				$status = 'EMERG';
				break;
			case LOG_ALERT:
				$status = 'ALERT';
				break;
			case LOG_CRIT:
				$status = 'CRIT';
				break;
			case LOG_ERR:
				$status = 'ERR';
				break;
			case LOG_WARNING:
				$status = 'WARNING';
				break;
			case LOG_NOTICE:
				$status = 'NOTICE';
				break;
			case LOG_INFO:
				$status = 'INFO';
				break;
			case LOG_DEBUG:
				$status = 'DEBUG';
				break;
			default:
				$status = 'UNKNOWN';
				break;
		}
		$logMessage = date('Y/n/d G:i:sP').' ['.$status.']'.$message."\n";
		print $logMessage;
		
		if($this->useSyslog) {
			//syslog
			syslog($priority, $message);
		}else{
			//output
			error_log($logMessage, 3, rtrim($this->logOutputDirectory, '\\/').DIRECTORY_SEPARATOR.'olinvas.log');
		}
	}
}
?>