<?php 
/**
 * This class generates and runs queries on the mongodb and then returns the result in the desired format. 
 * Queries are converted from a provided MySQL template into a MongoDB query.
 *
 * ---------------------------------------------------------------------------------------------------------
 * As of 11/20/2015: 
 * - Only simple queries can be made on the mongodb. 
 *   This means that no JOINs or embedded SELECT statements are supported.
 * - All data values and search is assumed to be lower case or if conditional, case insensitive
 * - This note may be removed if support for the above restrictions is added
 * ---------------------------------------------------------------------------------------------------------
 *
 * @author Al Zziwa <al@clout.com>
 * @version 1.3.0
 * @copyright Clout
 * @created 11/16/2015
 */
class _mongo extends CI_Model
{
	# a variable to hold the mongo db collection
	private $db;
	
		
	#Constructor to set some default values at class load
	public function __construct()
    {
    	log_message('debug', '_mongo/__construct');
        parent::__construct(); 
		
		$mongo = new MongoClient(MONGODB_DBDRIVER."://".MONGODB_USERNAME.":".MONGODB_PASSWORD."@".MONGODB_HOSTNAME.":".MONGODB_DBPORT."/".MONGODB_DATABASE);
		$this->db = $mongo->selectDB(MONGODB_DATABASE);
	}
	
	
	# run query on the database
	function run($query, $strict=FALSE)
	{
		log_message('debug', '_mongo/run');
		log_message('debug', '_mongo/run:: [1] query='.$query.' strict='.$strict);
		
		$result = FALSE;
		$mQuery = $this->mysql_to_mongo($query);
		$mTable = $this->db->selectCollection($mQuery['table']);
		
		$conditions = $mQuery['array']['conditions'];
		$values = $mQuery['array']['values'];
		log_message('debug', '_mongo/run:: [2] mQuery='.json_encode($mQuery));
		# Determine which method to use
		try {
		switch($mQuery['type']){
			case 'insert':
				# INSERT ... ON DUPLICATE KEY UPDATE ...
				if(!empty($conditions)) {
					$allValues = array_merge($conditions, $values);
					$mTable->save($allValues);
					$result = $strict? (!empty($allValues['_id'])? $allValues['_id']: ''): !empty($allValues['_id']);
				}
				# INSERT ...
				else {
					$mTable->insert($values);
					$result = $strict? (!empty($values['_id'])? $values['_id']: '') : !empty($values['_id']);
				}
			break;
			
			
			case 'update':
				$response = $mTable->update($conditions, array('$set'=>$values), array("multiple"=>TRUE, "w"=>($strict?1:0) ));
				$result = $strict? !empty($response['n']): $response;
			break;
			
			
			case 'delete':
				$result = $mTable->remove($conditions, array("w"=>($strict?1:0) ));
			break;
		}
		} catch(MongoCursorException $e) {
    		$result = FALSE;
		}
		log_message('debug', '_mongo/run:: [3] result='.$result);
		
		return $result;
	}
	
	
	# get count of possible results
	function get_count($query)
	{
		log_message('debug', '_mongo/get_count');
		log_message('debug', '_mongo/get_count:: [1] query='.$query);
		
		$mQuery = $this->mysql_to_mongo($query);
		log_message('debug', '_mongo/get_count:: [2] mQuery='.json_encode($mQuery));
		
		return  $this->db->selectCollection($mQuery['table'])->count($mQuery['array']['conditions']);
	}
	
	
	# get first row that matches and return as associated array
	function get_row_as_array($query)
	{
		log_message('debug', '_mongo/get_row_as_array');
		log_message('debug', '_mongo/get_row_as_array:: [1] query='.$query);
		
		$mQuery = $this->mysql_to_mongo($query);
		log_message('debug', '_mongo/get_row_as_array:: [2] mQuery='.json_encode($mQuery));
		
		return $this->db->selectCollection($mQuery['table'])->findOne($mQuery['array']['conditions'], $mQuery['array']['values']);
	}
	
	
	# get list of results of a query on the db
	function get_list($query)
	{
		log_message('debug', '_mongo/get_list');
		log_message('debug', '_mongo/get_list:: [1] query='.$query);
		
		$mQuery = $this->mysql_to_mongo($query);
		log_message('debug', '_mongo/get_list:: [2] mQuery='.json_encode($mQuery));
		
		if(!empty($mQuery['limit']) && !empty($mQuery['array']['orderby'])){
			$response = $this->db->selectCollection($mQuery['table'])->find($mQuery['array']['conditions'], $mQuery['array']['values'])->sort($mQuery['array']['orderby'])->skip($mQuery['offset'])->limit($mQuery['limit']);
			
		} else if(!empty($mQuery['limit'])) {
			$response = $this->db->selectCollection($mQuery['table'])->find($mQuery['array']['conditions'], $mQuery['array']['values'])->skip($mQuery['offset'])->limit($mQuery['limit']);
			
		} else if (!empty($mQuery['array']['orderby'])){
			$response = $this->db->selectCollection($mQuery['table'])->find($mQuery['array']['conditions'], $mQuery['array']['values'])->sort($mQuery['array']['orderby']);
			
		} else {
			$response = $this->db->selectCollection($mQuery['table'])->find($mQuery['array']['conditions'], $mQuery['array']['values']);
		}
		log_message('debug', '_mongo/get_list:: [3] response='.$response);
		
		return iterator_to_array($response);
	}
	
	
	
	
	# converts mysql query to mongodb query
	function mysql_to_mongo($originalQuery)
	{
		log_message('debug', '_mongo/mysql_to_mongo');
		log_message('debug', '_mongo/mysql_to_mongo:: [1] originalQuery='.$originalQuery);
		
		$query = trim(strtolower($originalQuery),';'); # deal with all lower to ease processing
		
		$array = array('values'=>array(), 'conditions'=>array(), 'orderby'=>array()); 
		$type = strstr($query, ' ', TRUE);
		$table = $offset = $limit = '';
		log_message('debug', '_mongo/mysql_to_mongo:: [2] type='.$type);
		
		switch($type){
			case 'select':
				# SELECT
				$fields = strstr_(strstr_($query, ' '), ' from ', TRUE);
				$values = explode(',',str_replace(' ','',$fields));
				foreach($values AS $value) $array['values'][$value] = 1;
				
				# FROM
				$table = trim(strstr_(trim(strstr_($query, ' from ')), ' ', TRUE));
				
				# WHERE
				$conditionString1 = trim(strstr_($query, ' where '));
				if(strpos($conditionString1, ' order by ') !== FALSE)  {
					$conditionString2 = strstr_($conditionString1, ' order by ', TRUE);
				} else if(strpos($conditionString1, ' limit ') !== FALSE) {
					$conditionString2 = strstr_($conditionString1, ' limit ', TRUE);
				}
				else if(!empty($conditionString1)) $conditionString2 = $conditionString1;
				else $conditionString2 = '';
				
				# get the condition string into an array
				if(!empty($conditionString2)) $array['conditions'] = $this->convert_condition_to_array($conditionString2);
				
				# ORDER BY
				if(strpos($conditionString1, ' order by ') !== FALSE)  {
					$orderString1 = strstr_($conditionString1, ' order by ');
					$orderString = strpos($orderString1, ' limit ') !== FALSE? strstr_($orderString1, ' limit ', TRUE): $orderString1;
					$order = explode(' ', trim($orderString));
					if(count($order) > 0) $array['orderby'] = array(trim($order[0]) => (!empty($order[1]) && trim($order[1]) == 'desc'? -1: 1));
				}
				
				# LIMIT
				$limitString = strpos($conditionString1, ' limit ') !== FALSE? str_replace(' ','',trim(strstr_($query, ' limit '))): '';
				if(!empty($limitString)){
					if(strpos($limitString, ',') !== FALSE){
						$limitParts = explode(',',$limitString);
						$offset = $limitParts[0];
						$limit = $limitParts[1];
					}
					else {
						$offset = 0;
						$limit = trim($limitString);
					}
				} 
				
			break;
			
			
			
			
			
			
			
			
			case 'insert':
				# INSERT INTO
				$table = trim(strstr_(trim(strstr_($query, ' into ')), ' ', TRUE));
				
				# (...)
				$fields = str_replace(' ','',strstr_(strstr_($query, '('), ')', TRUE));
				$valueFields = explode(',',$fields);
				
				# VALUES (...)
				$valuesString = trim(strstr_(strstr_(strstr_($query, 'values'), '('), ')', TRUE));
				$values = explode(',', $valuesString);
				
				foreach($valueFields AS $i=>$field) {
					$array['values'][$field] = trim(trim(str_replace('_MCOMMA_',',',$values[$i])), "'");
				}
			break;
			
			
			
			
			
			
			case 'update':
				# UPDATE
				$table = trim(strstr_(trim(strstr_($query, ' ')), ' ', TRUE));
				
				# SET 
				$fieldString = trim(strstr_(strstr_($query, ' set '), ' where ', TRUE));
				$fieldArray = explode(',',$fieldString);
				foreach($fieldArray AS $fieldNValue){
					$array['values'][trim(strstr_($fieldNValue, '=', TRUE))] = str_replace('_MCOMMA_',',',trim(trim(strstr_($fieldNValue, '=')), "'"));
				}
				
				# WHERE
				if(strpos($query, ' where ') !== FALSE){
					$conditionString = trim(strstr_($query, ' where '));
					$array['conditions'] = $this->convert_condition_to_array($conditionString);
				}
			break;
			
			
			
			
			
			
			
			
			case 'delete':
				# DELETE
				$table = trim(strstr_(strstr_($query, ' from '), ' ', TRUE));
				
				# WHERE 
				$conditionString = trim(strstr_($query, ' where '));
				$array['conditions'] = $this->convert_condition_to_array($conditionString);
			break;
			
		}
		
		# make sure the mongo ids are actually the db accepted ids
		if(array_key_exists('_id', $array['values'])) $array['values']['_id'] = new MongoId($array['values']['_id']);
		if(array_key_exists('_id', $array['conditions'])) $array['conditions']['_id'] = new MongoId($array['conditions']['_id']);
		
		$returnArray =  array('type'=>$type, 'array'=>$array, 'table'=>$table, 'offset'=>cast_number($offset), 'limit'=>cast_number($limit));
		log_message('debug', '_mongo/mysql_to_mongo:: [3] returnArray='.json_encode($returnArray));
		
		return $returnArray;
	}
	 
	
	
	
	
	
	
	
	
	# convert a query condition to an array
	# WARNING: condition conversion does not handle nested conditions e.g., those with parenthesis ()
	# OR those with sub queries OR MATCH in the condition
	function convert_condition_to_array($string)
	{
		log_message('debug', '_mongo/convert_condition_to_array');
		log_message('debug', '_mongo/convert_condition_to_array:: [1] string='.$string);
		
		$array1 = $array = array();
		# first split by OR and then by AND
		$parts1 = explode(' or ', trim($string));
		
		foreach($parts1 AS $part1){
			$array2 = array();
			$parts2 = explode(' and ',trim($part1));
			
			foreach($parts2 AS $part2){
				# searching within a given distance
				# assuming function is written as follows in MySQL: 
				# WHERE mongo_distance('latitude','longitude','field_name') < 10[km]
				if(strpos($part2, 'mongo_distance(') !== FALSE){
					$distanceparts = explode('<',str_replace(' ','',str_replace("'","",str_replace(')','',str_replace('mongo_distance(','',$part2)))));
					if(count($distanceparts) > 1) $latLonParts = explode(',',$distanceparts[0]);
					if(!empty($latLonParts) && count($latLonParts) > 2) {
						array_push($array2, array($latLonParts[2] => array('$near'=>array(
							'$geometry'=> array(
									'type'=>'Point',
									'coordinates'=>array(cast_number($latLonParts[1]), cast_number($latLonParts[0]))
									),
							'$maxDistance'=>($distanceparts[1]*1000)
							)))
						);
					}
				}
				
				# LIKE operand
				else if(strpos($part2, ' like ') !== FALSE) {
					$parts = explode(' like ',$part2);
					$like = count($parts) > 1? trim(trim($parts[1]),"'"): '';
					
					if(substr($like,0,1) == '%' && substr($like,-1) == '%') array_push($array2, array(trim($parts[0])=>
						array('$regex'=>trim($like,'%')) ));
						
					else if(substr($like,0,1) == '%') array_push($array2, array(trim($parts[0])=> array('$regex'=>trim($like,'%').'^')) );
					
					else if(substr($like,-1) == '%') array_push($array2, array(trim($parts[0])=> array('$regex'=>'^'.trim($like,'%'))) );
					else array_push($array2, array(trim($parts[0]) => cast_number(trim($like)) ));
				}
				
				
				# IN operand
				else if(strpos($part2, ' not in ') !== FALSE) {
					$parts = explode(' not in ',$part2);
					$itemString = count($parts) > 1? trim(trim(trim( trim($parts[1]), '('),')'),"'"): '';
					$items = array();
					$itemsRaw = explode(',',$itemString);
					foreach($itemsRaw AS $itemR) array_push($items, cast_number(trim(trim($itemR),"'")));
					
					if(count($items) > 0) array_push($array2, array(trim($parts[0])=> array('$nin'=>$items)) );
				}
				else if(strpos($part2, ' in ') !== FALSE) {
					$parts = explode(' in ',$part2);
					$itemString = count($parts) > 1? trim(trim(trim( trim($parts[1]), '('),')'),"'"): '';
					$items = array();
					$itemsRaw = explode(',',$itemString);
					foreach($itemsRaw AS $itemR) array_push($items, cast_number(trim(trim($itemR),"'")));
					
					if(count($items) > 0) array_push($array2, array(trim($parts[0])=> array('$in'=>$items)) );
				}
				
				
				# NULL operands
				else if(strpos($part2, ' is not null') !== FALSE) {
					array_push($array2, array(trim(str_replace('is not null','',$part2)) => array('$exists'=>TRUE) ));
				}
				else if(strpos($part2, ' is null') !== FALSE) {
					array_push($array2, array(trim(str_replace('is null','',$part2)) => array('$exists'=>FALSE) ));
				}
				
				# any other character operands
				else if(strpos($part2, '<=') !== FALSE) {
					$parts = explode('<=',$part2);
					if(count($parts) > 1) array_push($array2, array(trim($parts[0])=>array('$lte'=>cast_number(trim(trim($parts[1]),"'")) )) );
				}
				else if(strpos($part2, '>=') !== FALSE) {
					$parts = explode('>=',$part2);
					if(count($parts) > 1) array_push($array2, array(trim($parts[0])=>array('$gte'=>cast_number(trim(trim($parts[1]),"'")) )) );
				}
				else if(strpos($part2, '!=') !== FALSE || strpos($part2, '<>') !== FALSE) {
					$parts = strpos($part2, '!=') !== FALSE? explode('!=',$part2): explode('<>',$part2);
					if(count($parts) > 1) array_push($array2, array(trim($parts[0])=>array('$ne'=>cast_number(trim(trim($parts[1]),"'")) )) );
				}
				else if(strpos($part2, '<') !== FALSE) {
					$parts = explode('<',$part2);
					if(count($parts) > 1) array_push($array2, array(trim($parts[0])=>array('$lt'=>cast_number(trim(trim($parts[1]),"'")) )) );
				}
				else if(strpos($part2, '>') !== FALSE) {
					$parts = explode('>',$part2);
					if(count($parts) > 1) array_push($array2, array(trim($parts[0])=>array('$gt'=>cast_number(trim(trim($parts[1]),"'")) )) );
				}
				else if(strpos($part2, '=') !== FALSE) {
					$parts = explode('=',$part2);
					if(count($parts) > 1) array_push($array2, array(trim($parts[0])=>cast_number(trim(trim($parts[1]),"'")) ) );
				}
				
			}
			
			if(count($array2) > 1) {
				$temp = array();
				foreach($array2 AS $array2Item) array_push($temp, $array2Item);
				array_push($array1, array('$and'=>$temp)); 
			}
			else if(count($array2) > 0) $array1 = array_merge($array1, $array2);
		}
		log_message('debug', '_mongo/convert_condition_to_array:: [2] array2='.json_encode($array2));
		log_message('debug', '_mongo/convert_condition_to_array:: [3] array1='.json_encode($array1));
		log_message('debug', '_mongo/convert_condition_to_array:: [4] array='.json_encode($array));
		# Add the OR condition
		if(count($parts1) > 1) $array['$or'] = $array1;
		else if(count($array2) > 1) $array['$and'] = $array2;
		else if(count($array2) > 0) $array = $array2[0];
		
		return (count($array2) > 0? $array: array());
	}
	
	
	
	
	
	
	
	
}