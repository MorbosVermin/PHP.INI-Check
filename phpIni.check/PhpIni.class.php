<?php
/**
 * phpIni.check Class
 * Copyright (c)2010 Mike Duncan <mike.duncan@waitwha.com>
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */
class PhpIniCheck {
	
	private $file;
	private $config;
	private $parser;
	private $issues;
	
	public function __construct($filename, $config_filename="phpIni.check.config.xml")  {
		$this->config = array();
		$this->issues = array();
		
		try  {
			$this->file = new PhpIniFile($filename);
			$this->getConfiguration($config_filename);
			
		}catch(Exception $e)  {
			trigger_error($e->getMessage(), E_USER_ERROR);
			throw new Exception($e);
		}
	}
	
	private function getConfiguration($filename)  {
		$this->parser = xml_parser_create();
		xml_set_object($this->parser, $this);
		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, true);
		xml_set_element_handler($this->parser, "startElement", "endElement");
		xml_set_character_data_handler($this->parser, "dataHandler");
		
		$fp = fopen($filename, "r+");
		$line_number = 1;
		while(! feof($fp))  {
			$line = fgets($fp, 1024);
			$test = xml_parse($this->parser, $line);
			if(! $test)
				throw new Exception("Could not parse XML file ". $filename ."; parsing issue on line ". $line_number);
			
			$line_number++;
		}
		
		fclose($fp);
		trigger_error("Successfully loaded configuration from file ". $filename, E_USER_NOTICE);
	}
	
	public function getIssues()  {
		return $this->issues;
	}
	
	/**
	 * Returns the total number of issues found with the 
	 * PHP.INI file.
	 *
	 * @return int
	 */
	public function getNumberOfIssues()  {
		return count($this->issues);
	}
	
	private function getNumberOf($level)  {
		$num = 0;
		foreach($this->issues as $issue)
			if($issue->getLevel() == $level)
				$num++;
				
		return $num;
	}
	
	private function getIssueByLevel($level)  {
		$issues = array();
		foreach($this->issues as $i)
			if($i->getLevel() == $level)
				array_push($issues, $i);
				
		return $issues;
	}
	
	public function getNumberOfErrorIssues()  {
		return $this->getNumberOf(PhpIniIssue::ERROR);
	}
	
	public function getNumberOfWarnings()  {
		return $this->getNumberOf(PhpIniIssue::WARN);
	}
	
	public function getNumberOfInformations()  {
		return $this->getNumberOF(PhpIniIssue::INFO);
	}
	
	public function getErrors()  {
		return $this->getIssuesByLevel(PhpIniIssue::ERROR);
	}
	
	public function getWarnings()  {
		return $this->getIssuesByLevel(PhpIniIssue::WARN);
	}
	
	public function getInformations()  {
		return $this->getIssuesByLevel(PhpIniIssue::INFO);
	}
	
	/**
	 * Handles the configuration file parsing, specifically the
	 * start of an XML element.
	 *
	 * @param resource $parser
	 * @param string $name
	 * @param array $attrs
	 */
	public function startElement($parser, $name, $attrs)  {
		$name = strtolower($name);
		if(strcasecmp($name, "configuration") == 0)
			return;
		
		$setting = $this->file->getSetting($name);
		if(! is_null($setting))  {
			$setting->setRequired(true);
			$setting->setMessage($attrs["MESSAGE"]);
			$setting->setExpectedValue($attrs["VALUE"]);
			$setting->setimportance(intval($attrs["LEVEL"]));
			
			if(in_array("IFSAFEMODE", array_keys($attrs)))  {
				$safeMode = $this->file->isSafeMode();
				$check = PhpIniSetting::isOn($attrs["IFSAFEMODE"]);
				trigger_error("SafeMode is ". (($safeMode) ? "on" : "off") ." and the check is for it to be ". (($check) ? "on" : "off") .".", E_USER_NOTICE);
				if($safeMode !== $check)
					return; //If the safeMode and value of isSafeMode check out, skip it.
				
			}
			
			if(! $setting->evaluate())
				array_push($this->issues, new PhpIniIssue($setting));
			
		}
	}
	
	/**
	 * Signals the end of a element being parsed. This is not used
	 * with the current version.
	 *
	 * @param unknown_type $parser	xml_parser object.
	 * @param unknown_type $name	Name of the element which was parsed.
	 */
	public function endElement($parser, $name)  {}
	
	/**
	 * Handles the data within an element being parsed. This is CDATA which is 
	 * normally part of a text node. This is unused with the current version.
	 *
	 * @param unknown_type $parser	xml_parser object.
	 * @param unknown_type $data	Data within the element being parsed.
	 */
	public function dataHandler($parser, $data)  {}
	
}

class PhpIniFile  {
	
	private $filename;
	private $settings;
	private $safeMode;
	private $filesize;
	
	public function __construct($filename)  {
		$this->settings = array();
		$this->filename = $filename;
		$this->safeMode = false;
		if(! file_exists($this->filename))
			throw new Exception("File ". $this->filename ." does not exist.");
		else
			$this->parse();
		
		$this->filesize = filesize($filename);
	}
	
	public function getFileSize()  {
		return $this->filesize;
	}
	
	/**
	 * @return unknown
	 */
	public function getFilename() {
		return $this->filename;
	}
	
	/**
	 * @return array
	 */
	public function getSettings() {
		return $this->settings;
	}
	
	private function addSetting($setting)  {
		if(strcasecmp($setting->getKey(), "safe_mode") == 0)
			$this->safeMode = PhpIniSetting::isOn($setting->getValue());
		
		array_push($this->settings, $setting);
	}
	
	/**
	 * Returns whether or not Safe Mode is on within 
	 * the PHP.INI file.
	 *
	 * @return boolean
	 */
	public function isSafeMode()  {
		return $this->safeMode;
	}
	
	public function getSetting($name)  {
		foreach($this->settings as $s)
			if(strcmp($s->getKey(), $name) == 0)
				return $s;
				
		return null;
	}
	
	/**
	 * Parses a PHP.INI file and populates the settings 
	 * member.
	 *
	 */
	private function parse()  {
		$contents = parse_ini_file($this->filename);
		foreach($contents as $key => $value)
			$this->addSetting(new PhpIniSetting($key, $value));
		
		trigger_error(get_class($this) ." parsed ". $this->filename ." and retrieved ". count($this->settings) ." settings.", E_USER_NOTICE);
	}

}

class PhpIniSetting  {
	
	private $key;
	private $value;
	private $required;
	private $expectedValue;
	private $importance;
	private $message;
	
	public function __construct($key, $value)  {
		$this->key = PhpIniSetting::clean($key);
		$this->value = PhpIniSetting::clean($value);
		$this->expectedValue = PhpIniSetting::clean($value);
		$this->importance = PhpIniIssue::INFO;
		$this->message = "";
		$this->required = false;
	}
	
	/**
	 * @return unknown
	 */
	public function getKey() {
		return $this->key;
	}
	
	/**
	 * @return unknown
	 */
	public function getValue() {
		return $this->value;
	}
	
	/**
	 * @return unknown
	 */
	public function getExpectedValue() {
		return $this->expectedValue;
	}
	
	/**
	 * @return unknown
	 */
	public function getImportance() {
		return $this->importance;
	}
	
	/**
	 * @return unknown
	 */
	public function getMessage() {
		return $this->message;
	}
	
	/**
	 * @return unknown
	 */
	public function getRequired() {
		return $this->required;
	}
	
	/**
	 * @param unknown_type $expectedValue
	 */
	public function setExpectedValue($expectedValue) {
		$this->expectedValue = $expectedValue;
	}
	
	/**
	 * @param unknown_type $importance
	 */
	public function setImportance($importance) {
		$this->importance = intval($importance);
	}
	
	/**
	 * @param unknown_type $message
	 */
	public function setMessage($message) {
		$message = str_replace(array("\$name\$", "\$value\$"), array($this->name, $this->value), $message);
		$this->message = $message;
	}
	
	/**
	 * @param unknown_type $required
	 */
	public function setRequired($required) {
		$this->required = $required;
	}
	
	public function evaluate()  {
		if((strcmp($this->expectedValue, "non-blank") == 0) && (strlen($this->value) == 0))
			return false;
			
		else if((strcmp($this->expectedValue, "true") == 0) && (! PhpIniSetting::isOn($this->value)))
			return false;
			
		else if((strcmp($this->expectedValue, "false") == 0) && PhpIniSetting::isOn($this->value))
			return false;
			
		else if(strlen($this->expectedValue) == 0)
			return false;
			
		return true;
	}
	
	/**
	 * Returns a cleansed value.
	 *
	 * @param mixed $value	Array or string value to cleanse.
	 * @return mixed				Cleansed array or value.
	 */
	public static final function clean($value)  {
		if(is_array($value))  {
			foreach($value as $key => $val)
				$data[$key] = Request::clean($val);
				
			return $data;
		}else{
			
			$value = str_replace(chr(0xCA), '', str_replace(' ', ' ', $value));
			
			$value = preg_replace(
				array("/\&/", "/%/", "/</", "/>/", '/"/', "/'/", "/\(/", "/\)/", "/\+/", "/-/", "/\\\$/", "/\r/", "/&amp;#([0-9]+);/s", "/\\\(?!&amp;#|\?#)/"),
				array("&amp;", "&#37;", "&lt;", "&gt;", "&quot;", "&#39;", "&#40;", "&#41;", "&#43;", "&#45;", "$", "", "&#\\1;", "\\"),
				$value
			);
			
			$value = str_replace("'", "'", str_replace("!", "!", $value));
			
			return $value;
		}
	}
	
	/**
	 * Returns whether or not the value given is TRUE.
	 *
	 * @param string $value		Value to check.
	 * @return boolean
	 */
	public static function isOn($value)  {
		return ((strcasecmp($value, "On") == 0) || (strcasecmp($value, "True") == 0) || (strcmp($value, "1") == 0));
	}

}

class PhpIniIssue  {
	
	const INFO = 0;
	const WARN = 1;
	const ERROR = 2;
	private $setting;
	
	public function __construct($setting)  {
		$this->setting = $setting;
	}
	
	/**
	 * @return unknown
	 */
	public function getSetting() {
		return $this->setting;
	}
	
	public function getSettingName()  {
		return $this->setting->getKey();
	}
	
	public function getLevel()  {
		return $this->setting->getImportance();
	}
	
	public function getMessage()  {
		return $this->setting->getMessage();
	}
	
}


//Application entry point for CLI operations.
if((!isset($_SERVER["REQUEST_METHOD"]) && ($argc >= 2))  {
	//error_reporting(E_ALL ^E_NOTICE ^E_USER_NOTICE);
	
	$filename = $argv[1];
	$config_filename = ($argc == 3) ? $argv[2] : "phpIni.check.config.xml";
	
	$check = new PhpIniCheck($filename, $config_filename);
	echo "Scanned file ". $filename ." (". filesize($filename) ."bytes)\n";
	echo "Issues found: ". (($check->getNumberOfErrorIssues() + $check->getNumberOfWarnings())) ."\n";
	foreach($check->getIssues() as $issue)  {
		$log = " * ";
		switch($issue->getLevel())  {
			case PhpIniIssue::INFO:
				$log .= "INFO ";
				break;
			case PhpIniIssue::WARN:
				$log .= "WARN ";
				break;
			default:
				$log .= "ERROR "; 
		}
		
		$log .= "(". $issue->getSettingName() ."): ". $issue->getMessage() ."\n";
		echo $log;
	}
}
?>