#!/usr/bin/php
<?php
/**
 * phpIni.check
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

  if(! function_exists("parse_ini_file"))  {
    echo "Error: You need a more up-to-date version of PHP 4+ or 5+ which has the parse_ini_file function.\n\n";
    exit(1);
  }
  
  if($argc == 1)  {
    echo "Syntax: phpIni.check.php <filename.ini>\n\n";
    exit(1);
  }
    
  $filename = $argv[1];
  if(! file_exists($filename))  {
    echo "Error: The file ". $filename ." does not exist.\n\n";
    exit(1);
  }
  
  function isOn($value)  {
    return ((strcasecmp($value, "On") == 0) || (strcasecmp($value, "True") == 0) || (strcmp($value, "1") == 0));
  }
  
  $contents = parse_ini_file($filename);
  $warns = array();
  $safe_mode = true;
  foreach($contents as $key => $value)  {
    if((strcmp($key, "register_globals") == 0) && isOn($value))
      array_push($warns, "Warning: Register Globals is turned ON.");
      
    else if((strcmp($key, "allow_url_fopen") == 0) && isOn($value))
      array_push($warns, "Warning: fopen() is allowed to open remote files (allow_url_fopen).");
      
    else if((strcmp($key, "allow_url_include") == 0) && isOn($value))
      array_push($warns, "Warning: include[_once] and require[_once] functions are allows to open remote files (allow_url_include).");
      
    else if((strcmp($key, "display_errors") == 0) && isOn($value))
      array_push($warns, "Warning: Errors are allowed to be displayed to the end-user (display_errors).");
      
    else if((strcmp($key, "log_errors") == 0) && (! isOn($value)))
      array_push($warns, "Warning: Logging of errors is turned off (log_errors).");
      
    else if((strcmp($key, "error_log") == 0) && (strlen($value) > 0))
      array_push($warns, "Info: PHP System Error Log: ". $value);
      
    else if((strcmp($key, "display_startup_errors") == 0) && isOn($value))
      array_push($warns, "Warning: Displaying startup error messages should be turned off except when debugging (display_startup_errors).");
      
    else if((strcmp($key, "safe_mode") == 0) && (! isOn($value)))  {
      array_push($warns, "Warning: Safe mode is off (safe_mode).");
      $safe_mode = false;
      
    }else if((strcmp($key, "safe_mode_gid") == 0) && isOn($value) && $safe_mode)
      array_push($warns, "Warning: Safe mode is on, but scripts can access files which are accessible by other groups (safe_mode_gid).");
    
    else if((strcmp($key, "safe_mode_exec_dir") == 0) && $safe_mode)
      array_push($warns, "Info: Safe mode execution directory: ". $value);
      
    else if((strcmp($key, "expose_php") == 0) && isOn($value))
      array_push($warns, "Warning: PHP exposes itself within the HTTP headers (X-Powered-By header) (expose_php).");
    
    else if(strcmp($key, "post_max_size") == 0)
      array_push($warns, "Info: Maximum POST size allowed is ". $value);
      
    else if(strcmp($key, "upload_max_filesize") == 0)
      array_push($warns, "Info: Maximum size for a uploaded file is ". $value);
      
    else if(strcmp($key, "include_path") == 0)
      array_push($warns, "Info: PHP System Include Path is ". $value);
    
    else if(strcmp($key, "extension") == 0)
      array_push($warns, "Info: Extension ". $value ." is dynamically loaded.");
    
  }
  
  echo "Checked file: ". $filename ." (". filesize($filename) ."bytes)\n";
  echo "Number of settings: ". count($contents) ."\n";
  
  if(count($warns) > 0)  {
    echo "-- Findings ----------------------------------\n";
    foreach($warns as $warn)
      echo "* ". $warn ."\n";
    
    echo "----------------------------------------------\n";
  }
  
  echo "Total findings: ". count($warns) ."\n";
  
  exit(0);
?>