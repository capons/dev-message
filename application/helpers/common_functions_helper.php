<?php
/**
 * This file contains functions that are used in a number of classes or views.
 *
 * @author Al Zziwa <al@clout.com>
 * @version 1.3.0
 * @copyright Clout
 * @created 06/03/2015
 */



# Extract an ID from the API user friendly ID
function extract_id($id)
{
	log_message('debug', 'common_functions_helper/extract_id');
	log_message('debug', 'common_functions_helper/extract_id:: [1] id='.$id);
	return !empty($id)? hexdec(substr($id, 2)): "";
}



# Format ID for display
function format_id($id)
{
	log_message('debug', 'common_functions_helper/format_id');
	log_message('debug', 'common_functions_helper/format_id:: [1] id='.$id);
	return !empty($id)? "CT".str_pad(dechex($id),10,'0',STR_PAD_LEFT): "";
}



# Generate a verification code
function format_activation_code($seed)
{
	log_message('debug', 'common_functions_helper/format_activation_code');
	log_message('debug', 'common_functions_helper/format_activation_code:: [1] seed='.$seed);
	return str_pad(dechex($seed),4,'0',STR_PAD_LEFT);
}







#limit string length
function limit_string_length($string, $maxLength, $ignoreSpaces=TRUE, $endString='..')
{
	log_message('debug', 'common_functions_helper/limit_string_length');
	log_message('debug', 'common_functions_helper/limit_string_length:: [1] string='.$string.' maxLength='.$maxLength.' ignoreSpaces='.$ignoreSpaces.' endString'.$endString);
	
    if (strlen(html_entity_decode($string, ENT_QUOTES)) <= $maxLength) return $string;
	
	if(!$ignoreSpaces)
	{
    	$newString = substr($string, 0, $maxLength);
		$newString = (substr($newString, -1, 1) != ' ')?substr($newString, 0, strrpos($newString, " ")) : $string;
	}
	else
	{
		$newString = substr(html_entity_decode($string, ENT_QUOTES), 0, $maxLength);
		if(strpos($newString, '&') !== FALSE)
		{
			$newString = substr($newString, 0, strrpos($newString, " "));
		}
	}
	log_message('debug', 'common_functions_helper/limit_string_length:: [2] return='.$newString.$endString);
	
    return $newString.$endString;
}



# Remove any characters which are not numbers
function only_numbers($number, $removeNumbers = FALSE)
{
	log_message('debug', 'common_functions_helper/only_numbers');
	$regex = $removeNumbers? '/[0-9]+/': '/[^0-9]+/';
	return preg_replace($regex, '', $number);
}


	
# Remove commas 
function remove_commas($number)
{
	log_message('debug', 'common_functions_helper/remove_commas');
	return clean_str(str_replace(",","",$number));
}



# Cleans user string
function clean_str($strInput)
{
	log_message('debug', 'common_functions_helper/clean_str');
	return htmlentities(trim($strInput));
}



	
# Remove quotes before saving to the database
function remove_quotes($string)
{
	log_message('debug', 'common_functions_helper/remove_quotes');
	return str_replace('"', '', str_replace("'", '', $string));
}


# Check if the string is a number
function cast_number($string)
{
	log_message('debug', 'common_functions_helper/cast_number');
	if($string != '') {
		$remained = only_numbers($string,TRUE);
		if($remained == '.' || $remained == '-.'){
			return (float)$string;
		} else if($remained == '' || $remained == '-'){
			return (int)$string;
		}
	}
	return $string;
}



# Encrypts the entered values
function encrypt_value($value)
{
	log_message('debug', 'common_functions_helper/encrypt_value');
	$num = strlen($value);
	$numIndex = $num-1;
	$newValue="";
		
	#Reverse the order of characters
	for($x=0;$x<strlen($value);$x++){
		$newValue .= substr($value,$numIndex,1);
		$numIndex--;
	}
		
	#Encode the reversed value
	$newValue = base64_encode($newValue);
	return $newValue;
}
	
	
# Decrypts the entered values
function decrypt_value($dvalue)
{
	log_message('debug', 'common_functions_helper/decrypt_value');
	#Decode value
	$dvalue = base64_decode($dvalue);
		
	$dnum = strlen($dvalue);
	$dnumIndex = $dnum-1;
	$newDvalue = "";
		
	#Reverse the order of characters
	for($x=0;$x<strlen($dvalue);$x++){
		$newDvalue .= substr($dvalue,$dnumIndex,1);
		$dnumIndex--;
	}
	return $newDvalue;
}



# Post data to the URL 
function server_curl($url, $data)
{
	log_message('debug', 'common_functions_helper/server_curl');
	# Connect and post to URL
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	
	if(!empty($data['__check'])) echo PHP_EOL.PHP_EOL.PHP_EOL.$url."?".http_build_query($data);
	
	$response = curl_exec($ch); 
	$responseArray = json_decode($response, TRUE);
	
	curl_close($ch);	
	return $responseArray;
}







# Run on API
function run_on_api($url, $data, $runType='POST', $returnType='array')
{
		log_message('debug', 'common_functions_helper/run_on_api');
		#Prepare for sending
		$ch = curl_init();
    	
		#GET the data
		if($runType == 'GET')
		{
			$string = http_build_query($data);
			curl_setopt($ch, CURLOPT_URL, $url.'?'.$string); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, '10000');
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
			curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
		}
		
		
		#POST the data
		else if($runType == 'POST')
		{
			$string = json_encode($data);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($string)));
    		curl_setopt($ch, CURLOPT_URL, $url);
    		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
   			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    		curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
       	 	curl_setopt($ch, CURLOPT_POSTFIELDS,  $string);
       		curl_setopt($ch, CURLOPT_POST, 1);
		}
		#Other send run options
		else
		{
			$string = http_build_query($data);
			curl_setopt($ch, CURLOPT_URL, $url.'?'.$string); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, '10000');
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $runType);
		}
		
   		#Run the value as passed
		$result = curl_exec($ch);
			
		#Show error
   		if (curl_errno($ch)) 
		{
        	$error = curl_error($ch); 
			$errorResult = array('code' => 404, 'message' => 'system error', 'resolve' => $error ); 
    	} 
		#Close the channel
		curl_close($ch);
		
		
    	
		
		#Determine the type of data to return
		if($returnType == 'string')
		{
			return !empty($error)? $error: $result;
		}
		else
		{
			return !empty($errorResult)? $errorResult: json_decode($result, TRUE);
		}
}



#Function to format a number to a desired length and format
function format_number($number, $maxCharLength=100, $decimalPlaces=2, $singleChar=TRUE, $hasCommas=TRUE, $forceFloat=FALSE)
{
	log_message('debug', 'common_functions_helper/format_number');
	#first strip any formatting;
    $number = (0+str_replace(",","",$number));
    #is this a number?
    if(!is_numeric($number)) return false;
	
	#now format it based on desired length and other instructions
    if($number > 1000000000000 && $maxCharLength < 13) return number_format(($number/1000000000000),$decimalPlaces, '.', ($hasCommas? ',': '')).($singleChar? 'T': ' trillion');
    else if($number > 1000000000 && $maxCharLength < 10) return number_format(($number/1000000000),$decimalPlaces, '.', ($hasCommas? ',': '')).($singleChar? 'B': ' billion');
    else if($number > 1000000 && $maxCharLength < 7) return number_format(($number/1000000),$decimalPlaces, '.', ($hasCommas? ',': '')).($singleChar? 'M': ' million');
    else if($number > 1000 && $maxCharLength < 4) return number_format(($number/1000),$decimalPlaces, '.', ($hasCommas? ',': '')).($singleChar? 'K': ' thousand');
	else return number_format($number,((is_float($number) || $forceFloat)? $decimalPlaces: 0), '.', ($hasCommas? ',': ''));
}





#Remove an array item from the given items and return the final array
function remove_item($item, $fullArray)
{
	log_message('debug', 'common_functions_helper/remove_item');
	#First remove the item from the array list
	unset($fullArray[array_search($item, $fullArray)]);
	
	return $fullArray;
}




	
# Function checks all values to see if they are all true and returns the value TRUE or FALSE
function get_decision($values_array, $defaultTo=FALSE)
{
	log_message('debug', 'common_functions_helper/get_decision');
	$decision = empty($values_array)? $defaultTo: TRUE;
	
	if(!empty($values_array))
	{
		foreach($values_array AS $value)
		{
			if(!$value)
			{
				$decision = FALSE;
				break;
			}
		}
	}
	
	return $decision;
}




# Generate an associative array from a string
function generate_array_from_string($string)
{
	log_message('debug', 'common_functions_helper/generate_array_from_string');
	$keyValuePairs = explode(',',$string);
	$final = array();
	foreach($keyValuePairs AS $keyValuePair){
		$keyValue = explode('=',$keyValuePair);
		if(!empty($keyValue[0])) $final[$keyValue[0]] = (!empty($keyValue[1])? $keyValue[1]: '');
	}
	
	return $final;
}




# Remove tags from a string
function remove_tags($string)
{
	log_message('debug', 'common_functions_helper/remove_tags');
	$tags = array('<b red>', '<b green>', '<b>', '</b>');	
	$finalString = $string;
	foreach($tags AS $tag){
		$finalString = str_replace($tag, '', $finalString);
	}
	
	return $finalString;
}


# get the string between two given tags for start and end of the string
function get_string_between($string, $start, $end)
{
	log_message('debug', 'common_functions_helper/get_string_between');
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}






# Format a field for picking a date in the unix timestamp format
function format_field_for_date($fieldName)
{
	log_message('debug', 'common_functions_helper/format_field_for_date');
	return " IF(".$fieldName." IS NOT NULL AND ".$fieldName." NOT LIKE '%0000%', UNIX_TIMESTAMP(".$fieldName."), ' ') AS ".$fieldName." ";
}

	

# trim based on a preg pattern
function trim_($string, $pattern) 
{
	log_message('debug', 'common_functions_helper/trim_');
    $pattern = array( "/^" . $pattern . "*/", "/" . $pattern . "*$/" );
	return preg_replace( $pattern, "", $string );
}

	

# mimick strstr based on a preg pattern
# remove pattern as well (from the end)
# return same string if the pattern does not exist
function strstr_($string, $pattern, $beforePattern=FALSE) 
{
	log_message('debug', 'common_functions_helper/strstr_');
    $final = $string = $string;
	
	if(strlen($pattern) > 0 && strpos($string, $pattern) !== FALSE){
		$parts = explode($pattern, $string);
		if(count($parts) > 0){
			if(!$beforePattern) {
				array_shift($parts); # remove first item
				$final = implode($pattern, $parts);
			}
			else $final = $parts[0];
		}
	}
	
	return $final;
}
	
	


#Function to get current user's IP address
function get_ip_address()
{
	log_message('debug', 'common_functions_helper/get_ip_address');
	$ip = "";
	if ( isset($_SERVER["REMOTE_ADDR"]) )    
	{
    	$ip = ''.$_SERVER["REMOTE_ADDR"];
	} 
	else if ( isset($_SERVER["HTTP_X_FORWARDED_FOR"]) )    
	{
    	$ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	} 
	else if ( isset($_SERVER["HTTP_CLIENT_IP"]) )
	{
    	$ip = $_SERVER["HTTP_CLIENT_IP"];
	}
	
	return (ENVIRONMENT == 'development' || (!empty($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== FALSE))? DEFAULT_IP: $ip;
}



# Extract a value of a setting 
function extract_rule_setting_value($string, $setting, $settingKey='') 
{
	log_message('debug', 'common_functions_helper/extract_rule_setting_value');
	$settingStart = strpos($string,$setting);
			
	# a) extract the value of the setting
	$equalPos = strpos($string, '=', $settingStart);
	$closeBracePos = strpos($string, ']', $equalPos);
	#+1 to remove = and -1 to remove ]
	$rawValueString = substr($string, ($equalPos+1), ($closeBracePos - $equalPos - 1)); 
	$valueStringArray = explode('|', $rawValueString);
	$valueString = $valueStringArray[0];
			
	# b) extract the setting itself i.e., setting_variable=Setting Value|setting_id
	$settingString = substr($string, $settingStart, (strlen($setting) + 1 + strlen($rawValueString)) );
	
	$settings = array('setting_string'=>$settingString, 'setting'=>$setting, 'value'=>$valueString, 'value_id'=>(!empty($valueStringArray[1])? $valueStringArray[1]: ''));
	
	return array_key_exists($settingKey, $settings)? $settings[$settingKey]: $settings;
}






# Filter forwarded data to get only the passed variables
# In addition, it picks out all non-zero data from a URl array to be passed to a form
function filter_forwarded_data($obj, $urlDataArray=array(), $reroutedUrlDataArray=array(), $noOfPartsToIgnore=RETRIEVE_URL_DATA_IGNORE)
{
	log_message('debug', 'common_functions_helper/filter_forwarded_data');
	# Get the passed details into the url data array if any
	$urlData = $obj->uri->uri_to_assoc($noOfPartsToIgnore, $urlDataArray);
	
	$dataArray = array();
	
	
	foreach($urlData AS $key=>$value)
	{
		if($value !== FALSE && trim($value) != '' && !array_key_exists($value, $urlData))
		{
			if($value == '_'){
				$dataArray[$key] = '';
			} else {
				$dataArray[$key] = $value;
			}
		}
	}
	
	#handle re-routed URL data
	if(!empty($reroutedUrlDataArray))
	{
		$urlInfo = $obj->uri->ruri_to_assoc(3);
		foreach($reroutedUrlDataArray AS $urlKey)
		{
			if(!empty($urlInfo[$urlKey]))
			{
				$dataArray[$urlKey] = $urlInfo[$urlKey];
			}
		}
	}
	
	return restore_bad_chars_in_array($dataArray);
}




# Restore bar chars in an array
function restore_bad_chars_in_array($goodArray)
{
	log_message('debug', 'common_functions_helper/restore_bad_chars_in_array');
	$badArray = array();
	
	foreach($goodArray AS $key=>$item)
	{
		$badArray[$key] = restore_bad_chars($item);
	}
	
	return $badArray;
}




# Replace placeholders for bad characters in a text passed in URL with their actual characters
function restore_bad_chars($goodString)
{
	log_message('debug', 'common_functions_helper/restore_bad_chars');
	$badString = '';
	$badChars = array("'", "\"", "\\", "(", ")", "/", "<", ">", "!", "#", "@", "%", "&", "?", "$", ",", " ", ":", ";", "=", "*");
	$replaceChars = array("_QUOTE_", "_DOUBLEQUOTE_", "_BACKSLASH_", "_OPENPARENTHESIS_", "_CLOSEPARENTHESIS_", "_FORWARDSLASH_", "_OPENCODE_", "_CLOSECODE_", "_EXCLAMATION_", "_HASH_", "_EACH_", "_PERCENT_", "_AND_", "_QUESTION_", "_DOLLAR_", "_COMMA_", "_SPACE_", "_FULLCOLON_", "_SEMICOLON_", "_EQUAL_", "_ASTERISK_");
	
	foreach($replaceChars AS $pos => $charEquivalent)
	{
		$badString = str_replace($charEquivalent, $badChars[$pos], $goodString);
		$goodString = $badString;
	}
	
	return $badString;
}





# Replace placeholders for bad characters in a text passed in URL with their actual characters
function replace_bad_chars($badString)
{
	log_message('debug', 'common_functions_helper/replace_bad_chars');
	$badChars = array("'", "\"", "\\", "(", ")", "/", "<", ">", "!", "#", "@", "%", "&", "?", "$", ",", " ", ":", ";", "=", "*");
	$replaceChars = array("_QUOTE_", "_DOUBLEQUOTE_", "_BACKSLASH_", "_OPENPARENTHESIS_", "_CLOSEPARENTHESIS_", "_FORWARDSLASH_", "_OPENCODE_", "_CLOSECODE_", "_EXCLAMATION_", "_HASH_", "_EACH_", "_PERCENT_", "_AND_", "_QUESTION_", "_DOLLAR_", "_COMMA_", "_SPACE_", "_FULLCOLON_", "_SEMICOLON_", "_EQUAL_", "_ASTERISK_");
	
	$goodString = $badString;
	foreach($badChars AS $pos => $char) $goodString = str_replace($char, $replaceChars[$pos], $goodString);
	
	return $goodString;
}






 /**
	* Validate an email address. If the email address is not required, then an empty string will be an acceptable
	* value for the email address
	* 
	* @param String $email The email address to be validated
	* @param boolean $isRequired Whether the email is required or not. Defaults to TRUE
	*
	* @returns true if the email address has the correct email address format and that the domain exists.
	*/
function is_valid_email($email, $isRequired = true)
{
		log_message('debug', 'common_functions_helper/is_valid_email');
	   $isValid = true;
	   $atIndex = strrpos($email, "@");
	   
	   #if email is not required and is an empty string, do not check it. Return True.
	   if(!$isRequired && empty($email)){
		   return true;
	   }
	   if (is_bool($atIndex) && !$atIndex){
		  $isValid = false;
	   } else {
		  $domain = substr($email, $atIndex+1);
		  $local = substr($email, 0, $atIndex);
		  $localLen = strlen($local);
		  $domainLen = strlen($domain);
		  
		if ($localLen < 1 || $localLen > 64) {
			 # local part length exceeded
			 $isValid = false;
		  } else if ($domainLen < 1 || $domainLen > 255) {
			 # domain part length exceeded
			 $isValid = false;
		  }  else if ($local[0] == '.' || $local[$localLen-1] == '.') {
			 # local part starts or ends with '.'
			 $isValid = false;
		  } else if (preg_match('/\\.\\./', $local)) {
			 # local part has two consecutive dots
			 $isValid = false;
		  } else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
			 # character not valid in domain part
			 $isValid = false;
		  } else if (preg_match('/\\.\\./', $domain)) {
			 # domain part has two consecutive dots
			 $isValid = false;
		  } else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\","",$local))) {
			 # character not valid in local part unless 
			 # local part is quoted
			 if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\","",$local))) {
				$isValid = false;
			 }
		  } else if (strpos($domain, '.') === FALSE) {
			 # domain has no period
			 $isValid = false;
		  }
		  
		 /* if ($isValid && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A"))) {
			 # domain not found in DNS
			 $isValid = false;
		  } */
	 }
	 #return true if all above pass
	 return $isValid;
}
	
	
	
/**
 * Validate a delimited list of email addresses
 *
 * @param String $emaillist A delimited list of email addresses
 * @param boolean $isRequired Whether the email addresses are required
 * @param String $delimiter The delimiter for the emails, defaults to a comma
 * 
 * @return TRUE if the emails in the list are valid, and FALSE if any of the emails in the list are invalid
 */
function is_valid_email_list($emaillist, $isRequired = true, $delimiter = ",") 
{
	log_message('debug', 'common_functions_helper/is_valid_email_list');
	$list = explode($delimiter, $emaillist); 
	foreach ($list as $email) {
		if (!is_valid_email($email, $isRequired)) {
			return false; 
		} 
	}
	return true; 
}








# Download file from URL and push it to the AWS S3 Server
function download_from_url($url, $keepName=TRUE, $returnType='name', $suggestedName='', $urlType='string')
{
	log_message('debug', 'common_functions_helper/download_from_url');
	require APPPATH."libraries/s3/s3.config.php";
	
	# determine the file name to use
	$fileName = basename($url);
	$fileName = strpos($fileName, '?') !== FALSE? substr($fileName, 0, strpos($fileName, '?')): $fileName;
	if(!$keepName && empty($suggestedName)){
		$fileName = 'download_'.strtotime('now').'.'.strtolower(substr($fileName, strrpos($fileName, '.')+1));
	} else if(!$keepName && !empty($suggestedName)){
		$fileName = $suggestedName;
	}
	
	# now send the file
	try {
		$response = $s3->putObject(($urlType == 'content'? $url: @file_get_contents($url)), S3_BUCKET_NAME, $fileName, S3::ACL_PUBLIC_READ);
	} catch (Exception $e) {
		$response = FALSE;
	}
	
	# confirm upload and return the final file name to the user
	if($response && url_exists('https://'.S3_BUCKET_NAME.'.s3.amazonaws.com/'.$fileName)){
		if($returnType == 'name') return $fileName;
		if($returnType == 'url') return 'https://'.S3_BUCKET_NAME.'.s3.amazonaws.com/'.$fileName;
	}
	else return '';
}




# delete a file from the S3 bucket
function delete_from_s3($fileName)
{
	log_message('debug', 'common_functions_helper/delete_from_s3');
	require_once APPPATH."libraries/s3/s3.config.php";
	# now delete the file
	try {
		$response = $s3->deleteObject(S3_BUCKET_NAME, $fileName);
	} catch (Exception $e) {
		$response = FALSE;
	}
	
	return $response;
}




# wrap an s3 url to the given file name from the database
function s3_url($fileName)
{
	log_message('debug', 'common_functions_helper/s3_url');
	return 'https://'.S3_BUCKET_NAME.'.s3.amazonaws.com/'.$fileName;
}




# check if a URL is valid
function url_exists($url){
	log_message('debug', 'common_functions_helper/url_exists');
   $headers=get_headers($url);
   return stripos($headers[0],"200 OK")? true:false;
}



# CURL get contents
function curl_get_contents($url)
{
	log_message('debug', 'common_functions_helper/curl_get_contents');
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_URL, $url);

    $data = curl_exec($ch);
    curl_close($ch);

    return $data;
}





# Get column as array from a multi-row array
function get_column_from_multi_array($array, $column)
{
	log_message('debug', 'common_functions_helper/get_column_from_multi_array');
	$final = array();
	$firstItem = current($array);
	
	if(!empty($firstItem[$column])){
		foreach($array AS $row) array_push($final, $row[$column]);
	}

	return $final;
}





# Merge multi-dimensional arrays
function multi_dimensional_merge($array1, $array2)
{
	log_message('debug', 'common_functions_helper/multi_dimensional_merge');
	$combined = array_merge($array1, $array2);
	return array_map("unserialize", array_unique(array_map("serialize", $combined)));
}






# Prepare a search condition based on instructions
# Options for:
# type = match, like
# accuracy = low, medium, strict, very_strict
function generate_search_condition($obj, $phrase, $type='match', $accuracy='medium')
{
	log_message('debug', 'common_functions_helper/generate_search_condition');
	$final = "";
	if(!empty($phrase)) $parts = explode(' ',strtolower($phrase));
	
	# Generate a match condition
	if(!empty($phrase) && $type == 'match') {
		if($accuracy == 'very_strict') $final = '+"'.implode(' ',$parts).'"';
		else if($accuracy == 'strict') $final = '+'.implode(' +',$parts);
		else if($accuracy == 'medium') {
			$commonWords = $obj->_query_reader->get_single_column_as_array('get_intersecting_common_words', 'word', array('phrase_words'=>"'".implode("','",$parts)."'"));
			 $final = '';
			 foreach($parts AS $part) {
				 if(in_array($part,$commonWords)) $final .= ' ~'.$part;
				 else $final .= ' +'.$part;
			 }
			 $final = trim($final);
		}
		else $final = implode(' ',$parts);
	}
	
	#Generate a like condition
	else if(!empty($phrase) && $type == 'like') {
		if($accuracy == 'very_strict') $final = implode(' ',$parts);
		else if($accuracy == 'strict') $final = "%".implode(' ',$parts)."%";
		else if($accuracy == 'medium') $final = "%".implode('% %',$parts)."%";
		else $final = "%".implode('%',$parts)."%";
	}
	
	return $final;
}




# Checks the time spent from the start time to this time
function time_spent($startTime)
{
	log_message('debug', 'common_functions_helper/time_spent');
	return (time() - $startTime);
}



?>