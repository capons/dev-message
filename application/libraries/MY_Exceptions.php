<?php

class MY_Exceptions extends CI_Exceptions{

    function MY_Exceptions(){
        parent::CI_Exceptions();
    }

    function show_404($page=''){    
        $this->config =& get_config();
        /* Use this section if your view file is under views directory
		$baseUrl = $this->config['base_url'];
        header("location: ".$baseUrl.'web/my404');//view file location
        */
        $CI = &get_instance();
		$CI->load->view('web/404');
       
        exit;
    }

}

?>

