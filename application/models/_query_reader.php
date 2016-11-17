<?php
/**
 * This class generates and runs queries and then returns the result in the desired format. 
 *
 * @author Al Zziwa <al@clout.com>
 * @version 1.3.0
 * @copyright Clout
 * @created 06/04/2015
 */
class _query_reader extends CI_Model
{
	#a variable to hold the cached queries to prevent pulling from the DB for each request
    private $cachedQueries=array();
	private $dbWrite;
	private $dbRead;
	private $memcached;
	
	
	#Constructor to set some default values at class load
	public function __construct()
    {
    	log_message('debug', '_query_reader/__construct');
        parent::__construct();
		
		# write DB connection
        $this->dbWrite = $this->load->database('default',TRUE);
		# read DB connection
		$this->dbRead = $this->load->database('default',TRUE);
		# memcached for read queries
		if(extension_loaded('memcached')){
			$this->memcached = new Memcached();
			$this->memcached->addServer(HOSTNAME, 11211);
		}
		
		# use the query cache if its enabled so that the query templates are not fetched from the database
		$this->load->helper('queries_list');
		
		if(MONGODB_ENABLE) $this->load->model('_mongo');
	}
	
	
	
	# Get the query from the database
	function get_query_by_code($queryCode, $queryData = array())
	{
		log_message('debug', '_query_reader/get_query_by_code');
		log_message('debug', '_query_reader/get_query_by_code:: [1] queryCode='.$queryCode.' queryData='.json_encode($queryData));

		$cachedQuery = ENABLE_QUERY_CACHE && function_exists('get_sys_query')? get_sys_query($queryCode):'';

		$queryString = (!empty($cachedQuery) && ENABLE_QUERY_CACHE)? $cachedQuery: $this->get_raw_query_string($queryCode);
		log_message('debug', '_query_reader/get_query_by_code:: [2] cachedQuery='.$cachedQuery);
		log_message('debug', '_query_reader/get_query_by_code:: [3] queryString='.$queryString);
		
		return !empty($queryString)? $this->populate_template($queryCode, $queryString, $queryData): $queryString;
	}
	
	


	# Populate the query template with the provided values
	function populate_template($queryCode, $template, $queryData = array())
	{
		log_message('debug', '_query_reader/populate_template');
		log_message('debug', '_query_reader/populate_template:: [1] queryCode='.$queryCode.' template='.$template.' queryData='.json_encode($queryData));
		
		$query = $template;
		# Process the query data to fit the field format expected by the query
		$queryData = $this->format_field_for_query($queryData);
		
		#replace place holders with actual data required in the string
		foreach($queryData AS $key => $value) {
			if(!is_array($value)) {
				$query = str_replace("'".$key."'", "'".((strstr($queryCode,'__',TRUE) == 'mongodb' && MONGODB_ENABLE && strpos(strtolower($value), ' limit ') === FALSE && strpos(strtolower($value), "','") === FALSE)? str_replace(',','_MCOMMA_',$value): $value)."'", $query);
			}
		}
		
		#Then replace any other keys without quotes
		foreach($queryData AS $key => $value) {
			if(!is_array($value)) {
				$query = str_replace($key, ''.((strstr($queryCode,'__',TRUE) == 'mongodb' && MONGODB_ENABLE && strpos(strtolower($value), ' limit ') === FALSE && strpos(strtolower($value), "','") === FALSE)? str_replace(',','_MCOMMA_',$value): $value), $query);
			}
		}
		log_message('debug', '_query_reader/populate_template:: [2] query='.$query);
		
		return $query;
	}
	

	
	# Returns the raw query string
	private function get_raw_query_string($queryCode)
	{
		log_message('debug', '_query_reader/get_raw_query_string');
		log_message('debug', '_query_reader/get_raw_query_string:: [1] queryCode='.$queryCode);
		
		# Get the query from the database by the query code
		$qresultArray = $this->dbWrite->query("SELECT details FROM queries WHERE code = '".$queryCode."'")->row_array();
		log_message('debug', '_query_reader/get_raw_query_string:: [2] qresultArray='.json_encode($qresultArray));
		
		return !empty($qresultArray['details'])? $qresultArray['details']: '';
	}
	
	
	
	
	# Returns all fields in the format array('_FIELDNAME_', 'fieldvalue') which is expected by the database 
	# query processing function
	function format_field_for_query($queryData)
	{	
		log_message('debug', '_query_reader/format_field_for_query');
		log_message('debug', '_query_reader/format_field_for_query:: [1] queryData='.json_encode($queryData));
		
		$dataForQuery = array();
		
		# Sort the array keys to start replpacement with longest first
		$keys = array_keys($queryData);
		usort($keys, function($a, $b) {
    		return strlen($b) - strlen($a);
		});
		
		#e.g., $queryData['_LIMIT_'] = "10";
		foreach($keys AS $key) $dataForQuery['_'.strtoupper($key).'_'] = $queryData[$key];
		log_message('debug', '_query_reader/format_field_for_query:: [2] dataForQuery='.json_encode($dataForQuery));
		
		return $dataForQuery;
	}
	
	
	
	
	
	#Load queries into the cache file
	public function load_queries_into_cache()
	{
		log_message('debug', '_query_reader/load_queries_into_cache');
		
		$queries = $this->dbRead->query("SELECT * FROM queries")->result_array();
		
		#Now load the queries into the file
		file_put_contents(QUERY_FILE, "<?php ".PHP_EOL."global \$sysQuery;".PHP_EOL); 
		foreach($queries AS $query)
		{
			$queryString = "\$sysQuery['".$query['code']."'] = \"".str_replace('"', '\"', $query['details'])."\";".PHP_EOL;  
			file_put_contents(QUERY_FILE, $queryString, FILE_APPEND);
		}
		
		file_put_contents(QUERY_FILE, PHP_EOL.PHP_EOL." function get_sys_query(\$code) { ".PHP_EOL."global \$sysQuery; ".PHP_EOL."return !empty(\$sysQuery[\$code])? \$sysQuery[\$code]: '';".PHP_EOL." }".PHP_EOL, FILE_APPEND); 
		
		echo "QUERY CACHE FILE HAS BEEN UPDATED [".date('F d, Y H:i:sA T')."]";
	}
	
			
	
	# Simply run a query where no result is expected
	# if $updateStrict = TRUE then return false if there are no affected rows
	function run($queryCode, $queryData = array(), $updateStrict = FALSE)
	{
		log_message('debug', '_query_reader/run');
		log_message('debug', '_query_reader/run:: [1] queryCode='.$queryCode.' queryData='.json_encode($queryData).' updateStrict'.$updateStrict);
		
		$query = $this->get_query_by_code($queryCode, $queryData);
		log_message('debug', '_query_reader/run:: [2] query='.$query);
		
		# Determine which db to run on
		if(strstr($queryCode,'__',TRUE) == 'mongodb' && MONGODB_ENABLE) {
			log_message('debug', '_query_reader/run:: [3] run mongodb');
			return $this->_mongo->run($query,$updateStrict);
		} else {
			$result = $this->dbWrite->query($query);
			if($updateStrict) return !empty($this->dbWrite->affected_rows());
			else return $result;
		}
	}
	
	
	
	# read data from the database. using this function instead of the direct $this->dbRead
	# takes queries through memcached for faster retrieval of cached results
	function read($query, $queryType)
	{
		log_message('debug', '_query_reader/read');
		log_message('debug', '_query_reader/read:: [1] query='.$query.' queryType='.$queryType);
		
		if(extension_loaded('memcached')){
			$queryKey = "KEY".md5($query);
			$data = $this->memcached->get($queryKey);
			
			# query is not yet cached or expired, cache it again for MEMCACHED_PERIOD seconds
			if(!$data){
				$data = $this->read_from_db($query, $queryType);
				$this->memcached->set($queryKey, $data, MEMCACHED_PERIOD);
			}
			
			log_message('debug', '_query_reader/read:: [2] data='.$data);
			return $data;
		}
		
		# memcached is not enabled
		else {
			log_message('debug', '_query_reader/read:: [3] memcached is not enabled');
			return $this->read_from_db($query, $queryType);
		}
	}
	
	
	
	# function to force the user to read directly from the database
	function read_from_db($query, $queryType)
	{
		log_message('debug', '_query_reader/read_from_db');
		log_message('debug', '_query_reader/read_from_db:: [1] query='.$query.' queryType='.$queryType);
		
		if($queryType == 'get_count') $data = $this->dbRead->query($query)->num_rows();
		else if($queryType == 'get_row_as_array') $data = $this->dbRead->query($query)->row_array();
		else if($queryType == 'get_list') $data = $this->dbRead->query($query)->result_array();
		else $data = array();
		log_message('debug', '_query_reader/read_from_db:: [2] data='.json_encode($data));
		
		return $data;
	}
	
	
	
	
	# Get the result count for the given query details 
	function get_count($queryCode, $queryData = array())
	{
		log_message('debug', '_query_reader/get_count');
		log_message('debug', '_query_reader/get_count:: [1] queryCode='.$queryCode.' queryData='.json_encode($queryData));
		
		$query = $this->get_query_by_code($queryCode, $queryData);
		log_message('debug', '_query_reader/get_count:: [2] query='.$query);
		
		return (strstr($queryCode,'__',TRUE) == 'mongodb' && MONGODB_ENABLE)? $this->_mongo->get_count($query): $this->read($query, 'get_count');
	}
		
	
	# Given the query details, return the result as a single associated array
	function get_row_as_array($queryCode, $queryData = array())
	{
		log_message('debug', '_query_reader/get_row_as_array');
		log_message('debug', '_query_reader/get_row_as_array:: [1] queryCode='.$queryCode.' queryData='.json_encode($queryData));
		
		$query = $this->get_query_by_code($queryCode, $queryData);
		log_message('debug', '_query_reader/get_row_as_array:: [2] query='.$query);
		log_message('debug', '_query_reader/get_row_as_array:: [3] return='.(strstr($queryCode,'__',TRUE) == 'mongodb' && MONGODB_ENABLE));
		
		return (strstr($queryCode,'__',TRUE) == 'mongodb' && MONGODB_ENABLE)? $this->_mongo->get_row_as_array($query): $this->read($query, 'get_row_as_array');
	}
			
	
	# Given the query details, return the result as an array of associated arrays
	function get_list($queryCode, $queryData = array())
	{
		log_message('debug', '_query_reader/get_list');
		log_message('debug', '_query_reader/get_list:: [1] queryCode='.$queryCode.' queryData='.json_encode($queryData));
		
		$query = $this->get_query_by_code($queryCode, $queryData);
		log_message('debug', '_query_reader/get_list:: [2] query='.$query);
		log_message('debug', '_query_reader/get_list:: [3] return='.(strstr($queryCode,'__',TRUE) == 'mongodb' && MONGODB_ENABLE));
		
		return (strstr($queryCode,'__',TRUE) == 'mongodb' && MONGODB_ENABLE)? $this->_mongo->get_list($query): $this->read($query, 'get_list');
	}
			
	
	# Given the query details that return a single column, return the result as an array
	function get_single_column_as_array($queryCode, $columnName, $queryData = array())
	{
		log_message('debug', '_query_reader/get_single_column_as_array');
		log_message('debug', '_query_reader/get_single_column_as_array:: [1] queryCode='.$queryCode.' columnName='.$columnName.' queryData='.json_encode($queryData));
		
		$list = array();
		
		$results = $this->get_list($queryCode, $queryData);
		log_message('debug', '_query_reader/get_single_column_as_array:: [2] results='.json_encode($results));
		
		# check if the column exists in the returned data
		
		if(!empty($results)) 
		{
			$listFirstPiece = array_slice($results, 0, 1);
			$firstItem = array_shift($listFirstPiece);
			if(isset($firstItem[$columnName])) foreach($results AS $row) array_push($list, $row[$columnName]);
		}
		log_message('debug', '_query_reader/get_single_column_as_array:: [3] list='.json_encode($list));
		
		return $list;
	}
	
			
	
	# Run an insert query and return the id of the record
	function add_data($queryCode, $queryData = array())
	{
		log_message('debug', '_query_reader/add_data');
		log_message('debug', '_query_reader/add_data:: [1] queryCode='.$queryCode.' queryData='.json_encode($queryData));
		
		log_message('debug', '_query_reader/add_data:: [3] return='.(strstr($queryCode,'__',TRUE) == 'mongodb' && MONGODB_ENABLE));
		if(strstr($queryCode,'__',TRUE) == 'mongodb' && MONGODB_ENABLE) {
			return $this->_mongo->run($query,TRUE); # returns _id of the record if INSERT query
		} else {
			$this->dbWrite->query($this->get_query_by_code($queryCode, $queryData));
			return $this->dbWrite->insert_id();
		}
	}
	
	
	
	
	# TESTING FUNCTION ONLY
	# get the mongo query after conversion
	# Use this function to test query conversion. It is not used in live code.
	function get_mongo_query_by_code($queryCode, $queryData = array())
	{
		log_message('debug', '_query_reader/get_mongo_query_by_code');
		log_message('debug', '_query_reader/get_mongo_query_by_code:: [1] queryCode='.$queryCode.' queryData='.json_encode($queryData));
		
		return $this->get_list('mongodb__test_in_condition');
		$this->load->model('_mongo');
		#$query = $this->get_query_by_code($queryCode, $queryData);
		
		$query = "SELECT store_id FROM bname WHERE subcategories IN ('996')  AND store_id NOT IN ('12128952','9649981','9628362','11390619','16335055') AND mongo_distance('34.0690282','-118.3504599','loc') < 10 LIMIT 0,20";
		if(strstr($queryCode,'__',TRUE) == 'mongodb') return $this->_mongo->mysql_to_mongo($query);
		else return $query;
	}
	
	
	
}


?>