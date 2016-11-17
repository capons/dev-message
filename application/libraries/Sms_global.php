 <?php  if (!defined('BASEPATH')) exit('No direct script access allowed');

/***
* SMS Global Library for CodeIgniter
*/

class Sms_global {

    var $user;
    var $pass;
    var $to;
    var $from;
    var $message;
    var $error;
    var $smsID = '';
    var $serverResponse;
    
    function _clear()
    {
        $this->to = '';
        $this->from = '';
        $this->message = '';
        $this->error = '';
    }
    
    function Sms_global($config = array())
    {        
        $this->_clear();
        if (count($config) > 0)
        {
            foreach ($config as $key => $val)
            {
                $this->$key = $val;
            }
        }    

    }
    
    function to($to)
    {
        $this->to = $to;
    }
    
    function from($from)
    {
        $this->from = $from;
    }
    
    function message($message)
    {
        $this->message = $message;
    }
    
    function send()
    {
        // Check to is set
        if (!$this->to)
        {
            $this->error .= "No message entered<br />";
        }
        // Check msg is set
        if (!$this->message)
        {
            $this->error .= "No message entered<br />";
        }
        // Check from is set
        if (!$this->from)
        {
            $this->error .= "No 'from' number set<br />";
        }
        
        // If no error then send
        if (!$this->error)
        {
            $smsID = $this->sg_send_sms($this->user, $this->pass, $this->from, $this->to, $this->message);
            if (!$smsID)
            {
                $this->error = 'SMS Global failure';
            }
        	$this->smsID = $smsID;
        }        
    }
    
    function get_sms_id()
    {
        return $this->smsID;
    }
    
    function print_debugger()
    {
        echo '<strong>Status:</strong> ';
        if ($this->error)
        {
            echo $this->error.'<br />';
        } 
        else
        {
            echo 'SMS sent succesfully<br />';
        }
        echo '<strong>SMS ID:</strong> '.$this->smsID.'<br />';
        echo '<strong>Username:</strong> '.$this->user.'<br />';
        echo '<strong>Password:</strong> '.$this->pass.'<br />';
        echo '<strong>To:</strong> '.$this->to.'<br />';
        echo '<strong>From:</strong> '.$this->from.'<br />';
        echo '<strong>Message:</strong> '.$this->message.'<br />';
        echo '<strong>Server Response</strong> '.$this->serverResponse;
        
    }
	
	
    function send_sms($content) {
        
		$ch = curl_init('http://www.smsglobal.com.au/http-api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec($ch);
		curl_close ($ch);
        return $output;    
    }
	
	
    function sg_send_sms($user,$pass,$sms_from,$sms_to,$sms_msg)  
    {      
        $content =  'action=sendsms'.
                '&user='.rawurlencode($user).
                '&password='.rawurlencode($pass).
                '&to='.rawurlencode($sms_to).
                '&from='.rawurlencode($sms_from).
                '&text='.rawurlencode($sms_msg);
    
    	$smsglobal_response = $this->send_sms($content);
    	
		//Sample Response
    	//OK: 0; Sent queued message ID: 04b4a8d4a5a02176 SMSGlobalMsgID:6613115713715266 
    	$explode_response = explode('SMSGlobalMsgID:', $smsglobal_response);
		
    	return (count($explode_response) == 2)? $explode_response[1]: '';
    }

}    
?>  