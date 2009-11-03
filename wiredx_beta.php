<?php
/**
 * Booking engine wrapper.
 *
 * PHP versions 5
 *
 * WuBook  (http://www.wubook.net)
 * Copyright 2009, WuBook  (http://www.wubook.net)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @since         v 1.0
 * @version       v 1.0
 * @author	Marco Pergola (marco.pergola dot gmail.com)
 * @lastmodified  03/11/2009
 */
 
 
/**
 * Included libs
 * XML-RPC for PHP v 3.0.0beta (http://phpxmlrpc.sourceforge.net/)
 */
include("xmlrpc.inc");

class WuBook {

	/**
	 * List of errors
	 *
	 * @var array
	 * @access public
	 */
    var $errors = array();
	
	/**
	 * Name of Wubook account
	 *
	 * @var string
	 * @access public
	 */
    var $account;
	
	/**
	 * Password of Wubook account
	 *
	 * @var string
	 * @access public
	 */
    var $password;
    
	/**
	 * Token released from Wubook server
	 *
	 * @var string
	 * @access public
	 */
    var $token = null;   
    
	/**
	 * Xml- rpc Client
	 *
	 * @var object
	 * @access private
	 */
    private $xmlrpc_client = null;
	
	var $facilities = array();
    var $facility;
	
	
    /**
	 * Credit Cards information
	 *
	 * @var array
	 * @access public
	 */
    var $cc_family = array (
        1 => array('name'=>'Visa', 'cvv'=>1),
        2 => array('name'=>'MasterCard', 'cvv'=>1),
        4 => array('name'=>'Discover', 'cvv'=>1),
        8 => array('name'=>'American Express', 'cvv'=>1),
        16 => array('name'=>'Enroute', 'cvv'=>0),
        32 => array('name'=>'Jcb', 'cvv'=>0),
        64 => array('name'=>'Diners', 'cvv'=>0),
        128 => array('name'=>'Unknown', 'cvv'=>0),
        256 => array('name'=>'Maestro', 'cvv'=>0),
        512 => array('name'=>'Carte Blanche', 'cvv'=>0),
        1024 => array('name'=>'Australian BankCard', 'cvv'=>0),
        2048 => array('name'=>'Virtual Credit Card', 'cvv'=>0)
    );
	
	
    /**
	 * Constructor. Initialize xml-rpc client, accoutn e password
	 *
	 * @param string $account Account name
	 * @param string $password Account password
	 */
    function __construct($account, $password = null) {
        
        $this->xmlrpc_client = new xmlrpc_client('/xrwx/', 'wubook.net', '443', 'https');
        $this->xmlrpc_client->setSSLVerifyPeer(0);
        $this->account = $account;
        $this->password = $password;
    }
    
    /**
	* Call API method
	*
	* @param string $name Name of the method to call
	* @param string $params Parameters for the method.
	* @return array The response from API method
	* @access public
	*/
    function call_method($name, $params) {
        
        $this->errors = array();
        
        if (!$this->token) {
            $token_params = array(
                   new xmlrpcval($this->account, 'string'),
                   new xmlrpcval($this->password, 'string')
            );
            
            $xmlrpc_message = new xmlrpcmsg('get_token', $token_params);
            $xmlrpc_response = $this->xmlrpc_client->send($xmlrpc_message);
            
            $this->token = $this->validate_response($xmlrpc_response);
            unset($xmlrpc_message);
            unset($xmlrpc_response);
        }
        
        
        $params = array_merge(array($this->token), $params);        
      
        foreach ($params as $param) {
             $xmlrpc_val[] = php_xmlrpc_encode($param);
        }
        
        $xmlrpc_message = new xmlrpcmsg($name, $xmlrpc_val);

        $xmlrpc_response = $this->xmlrpc_client->send($xmlrpc_message);
        
        return $this->validate_response($xmlrpc_response);
              
    }
    
    
    /**
	* Check if XML-RPC response has error
	*
	* @param object $xmlrpc_response
	* @return mixed On success an array with data, on failure false
	* @access private
	*/
    private function validate_response($xmlrpc_response) {
        
        if (!$xmlrpc_response->faultCode()) {
            
            $xmlrpc_response = php_xmlrpc_decode($xmlrpc_response->value());
            if ($xmlrpc_response[0]) {
                $this->errors[] = array('code'=> $xmlrpc_response[0], 'message' => $xmlrpc_response[1]);
                return false;
            } else {
                return $xmlrpc_response[1];
            }
            
        } else {
            $this->errors[] = array('code'=> $xmlrpc_response->faultCode(), 'message' => $xmlrpc_response->faultString());
            return false;
        }    
       
    }
    
    /**
	* Retrieve a list of facilities and their details matching the specified period
	*
	* @param mixed $lcodes Property Identifier
	* @param string $dfrom Arrival date (dates being in european format: 21/12/2012)
	* @param string $dto Departure  date (dates being in european format: 21/12/2012)
	* @return array Bookable room, addons and offers for the selected period. 
	* - Available Rooms (with Addons and Reductions)
	* - Available Grouped Rooms
	* - General Addons and Reductions
	* - Special Offers
	* @access public
	*/
    function facilities_request($lcodes, $dfrom, $dto) {
        
        if (!is_array($lcodes)) $lcodes = array($lcodes);
        
        $params = array($lcodes, $dfrom, $dto);        
        $items = $this->call_method('facilities_request', $params);
        
        $i = 0;
        foreach ($items as $key=>$item) {
            
            if ($item[0]) {
                $this->errors[] = array('code' => $item[0], 'message' => $item[1]);
            } else {
                $facilities[$i]['id'] = $key;
                $facilities[$i]['rooms'] = $item[1][0];
                $facilities[$i]['grouped_rooms'] = $item[1][1];
                $facilities[$i]['addons'] = $item[1][2];
                $facilities[$i]['offers'] = $item[1][3];
                $i++;
            }
        }
        
        $this->facilities = $facilities;        
        return $this->facilities;

    }
    
    /**
	* Retrieve the facility details
	*
	* @param integer $lcode Property Identifier
	* @param string $dfrom Arrival date (dates being in european format: dd/mm/yyyy (21/12/2012))
	* @param string $dto Departure  date (dates being in european format: dd/mm/yyyy (21/12/2012))
	* @return array Bookable rooms, addons and offers for the selected period. 
	* - Available Rooms (with Addons and Reductions)
	* - Available Grouped Rooms
	* - General Addons and Reductions
	* - Special Offers
	* @access public
	*/
    function facility_request($lcode, $dfrom, $dto) {
        
        $params = array($lcode, $dfrom, $dto);        
        $item = $this->call_method('facility_request', $params);

        $facility['id'] = $lcode;
        $facility['rooms'] = $item[0];
        $facility['grouped_rooms'] = $item[1];
        $facility['addons'] = $item[2];
        $facility['offers'] = $item[3];
        
        $this->facility = $facility;
        
        return $facility;        
    }
    
    /**
	* Select the rooms to book later
	*
	* @param integer $lcode Property Identifier
	* @param array $rooms Rooms you want to book:
	*   int Room id
	*   int Room quantity
	*   (ie: array(111 => 2) where 111 is the room id and 2 is how many rooms you want to book)
	*
	* @return array Associated information for the rooms requested
	* - Daily Rooms Prices
	* - Credit Card Requirements
	* - Discount Type
	* - The Special Offer applied for this request
	* - Rooms Amount
	* - Rooms Addons/Reductions Amount
	* - Generic Addons/Reductions Amount
	* - Clean Amount (with no discount and offer)
	* - Total Amount (the amount with discount and offer applied)
	* 
	* @access public
	*/
    function room_request($lcode, $rooms = array()) {
        
        if (!$this->facility) {
            return false;
        }
        
        foreach ($rooms as $key=>$value) {            
            $room_reservations[] = array('number'=>$value, 'id'=>$key);            
        }
    
        $params = array($lcode, $room_reservations, array());        
        $items = $this->call_method('rooms_request', $params);
        
        return $items;
    }
    
    /**
	* Book the last request called by room_request()
	*
	* @param integer $lcode Property Identifier
	* @param array $customer Traveler personal information:
	*   string fname First Name
	*   string lname Last Name
	*   string street Street
	*   string email Email
	*   string country Country code - two chars (it = Italy)
	*   string city City
	*   string phone Phone
	*   string notes Notes
	*   string arrival_hour Time of arrival
	* @param array $credit_card Credit card information:
	*   string ctype Family Id
	*   string cc_number Number
	*   string cc_cvv CVV
	*   string cc_exp_month Expiration Month
	*   string cc_owner Owner Name
	*   string cc_exp_year Expiration Year
	* @param array $iata Iata information:
	*   string iata_name Name
	*   string iata_street Street
	*   string iata_zip ZIP
	*   string iata_city City
	*   string iata_phone Phone
	*   string iata_email Email
	*   string iata_vat VAT
	*   string iata_code Code
	*
	* @return string Html invoice (this invoice is sent also to the customer and to the lodging)
	* @access public
	*/
    function book_last_request($customer, $credit_card = array(), $iata = array()) {
        
        $params = array($customer, $credit_card, $iata);        
        $items = $this->call_method('book_last_request', $params);
        
    }
    
}

?>