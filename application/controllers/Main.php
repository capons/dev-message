<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This class handles running the queries or over-the-api commands to the message server.
 *
 * @author Al Zziwa <al@clout.com>
 * @version 1.3.0
 * @copyright Clout
 * @created 02/02/2016
 */

class Main extends CI_Controller
{
	#Constructor to set some default values at class load
	function __construct()
    {
        parent::__construct();
        $this->load->model('_message');
        $this->load->model('_message_cron');
        $this->load->model('_messenger');
        //if database connection error
        $this->load->database();
        $this->db->reconnect();
	}


	# the receiver for all the queries
	function index()
	{
		log_message('debug', 'Main/index');
		$_POST = !empty($_POST)? $_POST: array();

		# testing on the API
		$data = filter_forwarded_data($this);
		if(!empty($data) && !empty($data['ctest'])) $_POST = array_merge($_POST, $data);
		if(!empty($_GET) && !empty($_GET['__check'])) $_POST = array_merge($_POST, $_GET);

		# return error if there is no post
		if(empty($_POST) || empty($_POST['__action'])) {
			echo json_encode(array('responseCode'=>'400', 'message'=>'Bad Request. No instruction data posted.', 'moreInfo'=>'https://developers.clout.com/errors/400001', 'messageCode'=>'400001'));
			return 0;
		}
		log_message('debug', 'Main/index:: [1] _action ['.$_POST['__action'].']');

		# Test IAM DB connection through the API
		if($_POST['__action'] == 'test_db')
		{
			$mysqli = new mysqli(HOSTNAME, USERNAME, PASSWORD, DATABASE, DBPORT);
			echo json_encode(array('IS'=>($mysqli->ping()? 'CONNECTED': 'NO CONNECTION') ));
		}


		# Run a generic query on the database
		else if($_POST['__action'] == 'run')
		{
			log_message('debug', 'Main/index/run');

			$result = $this->_query_reader->run($_POST['query'], $_POST['variables'], (!empty($_POST['strict']) && $_POST['strict'] == 'true'));
			log_message('debug', 'Main/index/run:: [1] result='.$result);
			# determine what to return
			if(!empty($_POST['return']) && $_POST['return'] == 'plain') echo json_encode($result);
			else echo json_encode(array('result'=>($result? 'SUCCESS': 'FAIL') ));
		}


		# Run a generic query on the database
		else if($_POST['__action'] == 'add_data')
		{
			log_message('debug', 'Main/index/add_data');

			$id = $this->_query_reader->add_data($_POST['query'], $_POST['variables']);
			log_message('debug', 'Main/index/add_data:: [1] id='.json_encode($id));
			# determine what to return
			if(!empty($_POST['return']) && $_POST['return'] == 'plain') echo json_encode($id);
			else echo json_encode(array('id'=>$id));
		}


		# Run a generic query on the database
		else if($_POST['__action'] == 'get_list')
		{
			log_message('debug', 'Main/index/get_list');

			$list = $this->_query_reader->get_list($_POST['query'], $_POST['variables']);
			log_message('debug', 'Main/index/get_list:: [1] list='.json_encode($list));
			echo json_encode($list);

		}


		# Run a generic query on the database
		else if($_POST['__action'] == 'get_row_as_array')
		{
			log_message('debug', 'Main/index/get_row_as_array');

			$row = $this->_query_reader->get_row_as_array($_POST['query'], $_POST['variables']);
			log_message('debug', 'Main/index/get_row_as_array:: [1] list='.json_encode($row));
			echo json_encode($row);
		}


		# Run a generic query on the database
		else if($_POST['__action'] == 'get_single_column_as_array')
		{
			log_message('debug', 'Main/index/get_single_column_as_array');

			$list = $this->_query_reader->get_single_column_as_array($_POST['query'], $_POST['column'], $_POST['variables']);
			log_message('debug', 'Main/index/get_single_column_as_array:: [1] list='.json_encode($list));
			echo json_encode($list);
		}


		# cache the mysql queries for this server
		else if($_POST['__action'] == 'load_queries_into_cache')
		{
			log_message('debug', 'Main/index/load_queries_into_cache');
			log_message('debug', 'Main/index/load_queries_into_cache:: [1] ENABLE_QUERY_CACHE='.ENABLE_QUERY_CACHE);

			if(ENABLE_QUERY_CACHE) $this->_query_reader->load_queries_into_cache();
			echo json_encode(array('result'=>'DONE'));
		}


		# send messages using the message server
		else if($_POST['__action'] == 'send')
		{
			log_message('debug', 'Main/index/send');

			$result = (ENVIRONMENT == 'local')? TRUE: $this->_messenger->send($_POST['receiverId'], $_POST['message'], $_POST['requiredFormats'], $_POST['strictFormatting']);
			log_message('debug', 'Main/index/send:: [1] result='.$result);

			# determine what to return
			if(!empty($_POST['return']) && $_POST['return'] == 'plain') echo json_encode($result);
			else echo json_encode(array('result'=>($result? 'SUCCESS': 'FAIL') ));
		}


		# cache the messages for this server
		else if($_POST['__action'] == 'load_messages_into_cache')
		{
			log_message('debug', 'Main/index/load_messages_into_cache');
			log_message('debug', 'Main/index/load_messages_into_cache:: [1] ENABLE_QUERY_CACHE='.ENABLE_QUERY_CACHE);

			if(ENABLE_MESSAGE_CACHE) $this->_messenger->load_messages_into_cache();
			echo json_encode(array('result'=>'DONE'));
		}


		# activate more messages for sending
		else if($_POST['__action'] == 'activate_more_messages')
		{
			log_message('debug', 'Main/index/activate_more_messages');

			$result = $this->_message_cron->activate_more_messages();
			log_message('debug', 'Main/index/activate_more_messages:: [1] result='.json_encode($result));
			echo json_encode($result);
		}


		# send first reminder to non-responsive users
		else if($_POST['__action'] == 'send_first_reminder')
		{
			log_message('debug', 'Main/index/send_first_reminder');

			$result = $this->_message_cron->send_first_reminder();
			log_message('debug', 'Main/index/send_first_reminder:: [1] result='.json_encode($result));
			echo json_encode($result);
		}


		# send second reminder to non-responsive users
		else if($_POST['__action'] == 'send_second_reminder')
		{
			log_message('debug', 'Main/index/send_second_reminder');

			$result = $this->_message_cron->send_second_reminder();
			log_message('debug', 'Main/index/send_second_reminder:: [1] result='.json_encode($result));
			echo json_encode($result);
		}


		# send pending invitations
		else if($_POST['__action'] == 'send_pending_invitations')
		{
			log_message('debug', 'Main/index/send_pending_invitations');

			$result = $this->_message_cron->send_pending_invitations();
			log_message('debug', 'Main/index/send_pending_invitations:: [1] result='.json_encode($result));
			echo json_encode($result);
		}


		# send pending messages
		else if($_POST['__action'] == 'send_pending_messages')
		{
			log_message('debug', 'Main/index/send_pending_messages');

			$result = $this->_message_cron->send_pending_messages();
			log_message('debug', 'Main/index/send_pending_messages:: [1] result='.json_encode($result));
			echo json_encode($result);
		}


		# send direct email to an email specified
		else if($_POST['__action'] == 'send_direct_email')
		{
			log_message('debug', 'Main/index/send_direct_email');

			$result = $this->_messenger->send_direct_email($_POST['recipientEmail'], $_POST['userId'], $_POST['message'], (!empty($_POST['method'])? $_POST['method']: 'unverified'));
			log_message('debug', 'Main/index/send_direct_email:: [1] result='.json_encode($result));
			echo json_encode($result);
		}


		# get list of messages
		else if($_POST['__action'] == 'get_list_of_messages')
		{
			log_message('debug', 'Main/index/get_list_of_messages');

			$result = $this->_message->get_list($_POST['userId'], $_POST['offset'], $_POST['limit'], (!empty($_POST['filters'])? $_POST['filters']: array()));
			log_message('debug', 'Main/index/get_list_of_messages:: [1] result='.json_encode($result));
			echo json_encode($result);
		}


		# get the user message statistics
		else if($_POST['__action'] == 'get_statistics')
		{
			log_message('debug', 'Main/index/get_statistics');

			$result = $this->_message->statistics($_POST['userId'], $_POST['fields']);
			log_message('debug', 'Main/index/get_statistics:: [1] result='.json_encode($result));
			echo json_encode($result);
		}


		# get the user message details
		else if($_POST['__action'] == 'get_details')
		{
			log_message('debug', 'Main/index/get_details');

			$result = $this->_message->details($_POST['userId'], $_POST['messageId']);
			log_message('debug', 'Main/index/get_details:: [1] result='.json_encode($result));
			echo json_encode($result);
		}


		# like a message
		else if($_POST['__action'] == 'like_message')
		{
			log_message('debug', 'Main/index/like_message');

			$result = $this->_message->like_message($_POST['userId'], $_POST['messages'], $_POST['action']);
			log_message('debug', 'Main/index/like_message:: [1] result='.json_encode($result));
			echo json_encode($result);
		}


		# add a mark to a message
		else if($_POST['__action'] == 'add_mark')
		{
			log_message('debug', 'Main/index/add_mark');

			$result = $this->_message->add_mark($_POST['userId'], $_POST['messages'], $_POST['action']);
			log_message('debug', 'Main/index/add_mark:: [1] result='.json_encode($result));
			echo json_encode($result);
		}


		# send a contact message
		else if($_POST['__action'] == 'send_contact_msg')
		{
			log_message('debug', 'Main/index/send_contact_msg');

			$result = $this->_message->send_contact_msg($_POST['name'], $_POST['emailAddress'], $_POST['telephone'], $_POST['message'], $_POST['userId']);
			log_message('debug', 'Main/index/send_contact_msg:: [1] result='.json_encode($result));
			echo json_encode($result);
		}


		# schedule sending a message
		else if($_POST['__action'] == 'schedule_send')
		{
			log_message('debug', 'Main/index/schedule_send');

			$result = $this->_message->schedule_send($_POST['message'], $_POST['userId'], $_POST['organizationId'], $_POST['organizationType']);
			log_message('debug', 'Main/index/schedule_send:: [1] result='.json_encode($result));
			echo json_encode($result);
		}


		# unsubscribe from Clout emails
		else if($_POST['__action'] == 'unsubscribe')
		{
			log_message('debug', 'Main/index/unsubscribe');

			$result = $this->_message->unsubscribe($_POST['emailAddress'], $_POST['telephone'], $_POST['reason']);
			log_message('debug', 'Main/index/unsubscribe:: [1] result='.json_encode($result));
			echo json_encode($result);
		}












		# run a test function
		else if($_POST['__action'] == 'test_this')
		{
			$this->test_function();
		}


	}



	# this is a test function
	function test_function()
	{
		$this->load->model('_messenger');
		/*$isSent = $this->_messenger->send_direct_email('6786442425@mms.att.net', '', array(
				'code'=>'new_store_scores',
				'firstname'=>'Aloysious',
				'newstorescores'=>'Apple (560), Green Heart (670)..',
				'__format'=>'text'
		));*/
		
		$isSent = $this->_messenger->send_direct_email('6786442425@mms.att.net', '', array(
				'code'=>'user_defined_message',
				'user_defined_subject'=>'Your Store Scores Have Changed',
				'user_defined_message'=>'Hi handsome, I hope you are doing fine this lovely day!',
				'user_defined_sms'=>'Your new scores are: Papa Johns Pizza=1,008, Venmo Payment 16991172=1,008, Apple Store=1,008 ..more in email',
				'__format'=>'text'
		));
			
		echo $isSent? "SENT":"FAIL";

	}














}

/* End of controller file */
