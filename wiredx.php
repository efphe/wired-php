<?php
/* Copyright (c) 2009, Federico Tomassini AKA yellow (yellow AT wubook DOT net)
 * All rights reserved.
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the University of California, Berkeley nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE REGENTS AND CONTRIBUTORS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE REGENTS AND CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.\
 */


  /*  WuBook: Booking Client (http://wubook.net) 
   *
   * Versions (last= actual version):
   *    - Version: 0.1 (Jun 2009) 
   *
   * Changelog:
   *
   *   0.2: 
   *    - hst (missing third arg fixed on init_wubook()
   */

include("xmlrpc.inc");
$_wbdbgmode= 1;

/* 
 * Utils, forget them
 */

function _serv() {
  global $wbhost;
  $server = new xmlrpc_client($wbhost);
  $server->setSSLVerifyPeer(0); 
  return $server;
}

function _scal($s, $n) {
  $t= $s->arraymem($n);
  return $t->scalarVal();
}
function _scalv($s, $m) {
  return $s->structMem($m)->scalarVal();
}

function parse_name_descr($o, $c) {
  $c->descr_en= _scalv($o, 'descr_en');
  $c->descr_de= _scalv($o, 'descr_de');
  $c->descr_pt= _scalv($o, 'descr_pt');
  $c->descr_es= _scalv($o, 'descr_es');
  $c->descr_fr= _scalv($o, 'descr_fr');
  $c->descr_it= _scalv($o, 'descr_it');
  $c->name_en= _scalv($o, 'name_en');
  $c->name_de= _scalv($o, 'name_de');
  $c->name_pt= _scalv($o, 'name_pt');
  $c->name_es= _scalv($o, 'name_es');
  $c->name_fr= _scalv($o, 'name_fr');
  $c->name_it= _scalv($o, 'name_it');
  $c->name= _scalv($o, 'name');
  $c->descr= _scalv($o, 'descr');
}

function parse_room($r, $a= -1) {
  $wroom= new WbRoom();

  parse_name_descr($r, $wroom);
  $wroom->occupancy= _scalv($r, 'beds');
  $wroom->avgprice= _scalv($r, 'avgprice');
  $wroom->id= _scalv($r, 'id');
  $wroom->adults= _scalv($r, 'men');
  $wroom->children= _scalv($r, 'children');
  $wroom->imglink= _scalv($r, 'img');

  if ($a != -1) 
    $wroom->avail= $o;
  else
    $wroom->avail= _scalv($r, 'avail');
  return $wroom;
}

function parse_groom($gr) {
  $wgroom= new WbGRoom();

  $gavail= _scalv($gr, 'avail');
  $wgroom->avail= $gavail;
  $wgroom->rooms= array();

  $subrooms= $gr->structMem('rooms');
  $n= $subrooms->arraysize();
  foreach(range(0,$n-1) as $i) {
    $subr= $subrooms->arraymem($i);
    $wroom= parse_room($subr, $gavail);
    $wgroom->rooms[$i]= $wroom;
  }
  return $wgroom;
  
}

function parse_offer($off) {
  $woff= new WbOffer();
  parse_name_descr($off, $woff);
  return $woff;
}

function parse_facility($s, $lcode) {
  $rooms= $s->arraymem(0);
  $grooms= $s->arraymem(1);
  $opps= $s->arraymem(2);
  $soffs= $s->arraymem(3);


  $nrooms= $rooms->arraysize();
  $wbrooms= array();
  if ($nrooms != 0) {
    foreach(range(0, $nrooms - 1) as $i) {
      $r= $rooms->arraymem($i);
      $wroom= parse_room($r);
      $wbrooms[$i] = $wroom;
    }
  }

  $nrooms= $grooms->arraysize();
  $wbgrooms= array();
  if ($nrooms != 0) {
    foreach(range(0, $nrooms - 1) as $i) {
      $r= $grooms->arraymem($i);
      $gr= parse_groom($r);
      $wbgrooms[$i]= $gr;
    }
  }

  $noffs= $soffs->arraysize();
  $offs= array();
  if ($noffs != 0) {
    foreach(range(0, $noffs-1) as $i) {
      $off= $soffs->arraymem($i);
      $woff= parse_offer($off);
      $offs[$i]= $woff;
    }
  }

  $wvfac= new WbFacility();
  $wvfac->lcode= (int)$lcode;
  $wvfac->rooms= $wbrooms;
  $wvfac->grooms= $wbgrooms;
  $wvfac->offers= $offs;
  $wvfac->offers= $offs;

  return $wvfac;
}

function parse_facilities($s, $lcodes) {
  $wfac= new WbFacilities();
  $wfar= array();
  foreach($lcodes as $lcode) {
    $f= $s->structmem((string)$lcode);
    $fres= _scal($f, 0);
    if ($fres < 0) continue;
    $fs= $f->arraymem(1);
    $wf= parse_facility($fs, $lcode);
    $wfar[]= $wf;
  }
  $wfac->facilities= $wfar;
  return $wfac;
}

function parse_cc_requirements($s) {
  $cc= $s->structmem('ccard');
  $ccreq= $cc->structmem('cc_required')->scalarVal();
  if ($ccreq) 
    $cccvc= $cc->structmem('cc_cvv_required')->scalarVal();
  else
    $cccvc= 0;
  $ccs= $cc->structmem('cc_available');
  $ccr= new CCRequirements();
  $ccr->cc_available= $ccs;
  $ccr->cc_required= $ccreq;
  $ccr->cc_cvc_required= $cccvc;
  return $ccr;
}

function parse_book_request($s) {
  $br= new BookRequest();
  $cc= parse_cc_requirements($s);
  $br->cc= $cc;
  $br->total_amount= $s->structmem('total_amount')->scalarVal();
  $br->clean_amount= $s->structmem('clean_amount')->scalarVal();
  $br->rooms_amount= $s->structmem('rooms_amount')->scalarVal();
  $off= $s->structmem('offer');
  if ($off->structmemexists('name')) 
    $br->offer= parse_offer($off);
  else
    $br->offer= 0;

  $rprices= array();
  $prices= $s->structmem('dailyprices');
  $np= $prices->arraysize();
  foreach(range(0,$np-1) as $i) {
    $dayprices= $prices->arraymem($i);
    $rid= $dayprices->structmem('id')->scalarVal();
    $dprices= $dayprices->structmem('prices');
    $ndp= $dprices->arraysize();
    $ridprices= array();
    foreach(range(0,$ndp-1) as $j) {
      $ridprices[]= $dprices->arraymem($j)->scalarVal();
    }
    $rprices[$rid]= $ridprices;
  }
  $br->rooms_daily_prices= $rprices;
  return $br;
}

function get_token($acc, $pwd) {
  //
  // Returns:
  //    -1  -> Autenthtication Failed 
  //    str -> WuBook Token 
  //
  $server= _serv();
  $message = new xmlrpcmsg('get_token',
                           array(new xmlrpcval($acc, 'string'),
                                 new xmlrpcval($pwd, 'string')));
  $struct = $server->send($message)->value();
  if (!$struct) return -1;

  $ires= _scal($struct, 0);
  if ($ires != 0) return -1;
  $token= _scal($struct, 1);
  return $token;
}

function _facility_request($token, $lcode, $dfrom, $dto) {
  $server= _serv();
  $message = new xmlrpcmsg('facility_request',
                           array(new xmlrpcval($token, 'string'),
                                 new xmlrpcval($lcode, 'int'),
                                 new xmlrpcval($dfrom, 'string'),
                                 new xmlrpcval($dto, 'string')));
  $struct = $server->send($message)->value();
  $res= _scal($struct, 0);
  if ($res < 0) return -1;

  $s= $struct->arraymem(1);
  $f= parse_facility($s, $lcode);
  return $f;
}

function _facilities_request($token, $lcodes, $dfrom, $dto) {
  $server= _serv();
  $nlcodes= array();
  foreach($lcodes as $lcode) {
    $nlcodes[]= new xmlrpcval($lcode, 'string');
  }
  $message = new xmlrpcmsg('facilities_request',
                           array(new xmlrpcval($token, 'string'),
                                 new xmlrpcval($nlcodes, 'array'),
                                 new xmlrpcval($dfrom, 'string'),
                                 new xmlrpcval($dto, 'string')));
  $struct = $server->send($message)->value();
  if (!$struct) {
    print "Failed to call facilities_request()";
    return -1;
  }
  $res= _scal($struct, 0);
  if ($res < 0) return -1;
  $s= $struct->arraymem(1);
  $wf= parse_facilities($s, $lcodes);
  return $wf;
}

/* Embed this Call inside WbFacility? */
function _book_request($token, $fac, $rooms, $grooms) { 
  $fac->request= 0;
  $server= _serv();
  $arooms= array();
  foreach($rooms as $rid => $how) {
    $fat= new xmlrpcval();
    $show= new xmlrpcval($how, 'int');
    $srid= new xmlrpcval($rid, 'int');
    $fat->addStruct(array('number'=> $show, 'id'=> $srid));
    $arooms[]= $fat;
  }

  $lcode= $fac->lcode;
  $message = new xmlrpcmsg('rooms_request',
                           array(new xmlrpcval($token, 'string'),
                                 new xmlrpcval($lcode, 'int'),
                                 new xmlrpcval($arooms, 'array')));
  $struct = $server->send($message)->value();
  $res= _scal($struct, 0);
  if ($res < 0) return -1;
  $s= $struct->arraymem(1);
  $br= parse_book_request($s);
  $fac->request= $br;
  return $br;
}

function _book_now($token, $fac, $customer, $cc) {
  $server= _serv();
  $fcc= new xmlrpcval(array(
      'cc_owner'=> new xmlrpcval($cc->cc_owner, 'string'),
      'cc_number'=> new xmlrpcval($cc->cc_number, 'string'),
      'cc_exp_month'=> new xmlrpcval($cc->cc_exp_month, 'string'),
      'cc_exp_year'=> new xmlrpcval($cc->cc_exp_year, 'string'),
      'cc_cvc'=> new xmlrpcval($cc->cc_cvc, 'string'),
      'cc_family'=> new xmlrpcval($cc->cc_family, 'string')), 'struct');
  $fcust= new xmlrpcval(array(
    'fname'=> new xmlrpcval($customer->fname, 'string'),
    'lname'=> new xmlrpcval($customer->lname, 'string'),
    'email'=> new xmlrpcval($customer->email, 'string'),
    'city'=> new xmlrpcval($customer->city, 'string'),
    'street'=> new xmlrpcval($customer->street, 'string'),
    'country'=> new xmlrpcval($customer->country, 'string'),
    'phone'=> new xmlrpcval($customer->phone, 'string'),
    'notes'=> new xmlrpcval($customer->remarks, 'string'),
    'arrival_hour'=> new xmlrpcval($customer->arrival_hour, 'string')), 'struct');
  $tok= new xmlrpcval($token, 'string');

  $msg= new xmlrpcmsg('book_last_request', array($tok, $fcust, $fcc));
  $struct = $server->send($msg)->value();
  $res= _scal($struct, 0);
  if ($res < 0) return -1;
  $s= $struct->arraymem(1);
  return 0;
}

/*
 * Classes
 */

class WbNamesDescr {
  var $descr_es;
  var $descr_en;
  var $descr_it;
  var $descr_fr;
  var $descr_de;
  var $descr_pt;
  var $descr;
  var $name_es;
  var $name_en;
  var $name_it;
  var $name_fr;
  var $name_de;
  var $name_pt;
  var $name;

  function get_name($lang) {
    /* does PHP have a getattr() to automize this? */
    switch($lang) {
      case 'en': 
        $name= $this->name_en;
        break;
      case 'pt': 
        $name= $this->name_pt;
        break;
      case 'es': 
        $name= $this->name_es;
        break;
      case 'fr': 
        $name= $this->name_fr;
        break;
      case 'de': 
        $name= $this->name_de;
        break;
      case 'it': 
        $name= $this->name_it;
        break;
      default:
        $name= $this->name;
    }
    if (!$name) return $this->name;
    return $name;
  }

  function get_descr($lang) {
    /* does PHP have a getattr() to automize this? */
    switch($lang) {
      case 'en': 
        $descr= $this->descr_en;
        break;
      case 'pt': 
        $descr= $this->descr_pt;
        break;
      case 'es': 
        $descr= $this->descr_es;
        break;
      case 'fr': 
        $descr= $this->descr_fr;
        break;
      case 'de': 
        $descr= $this->descr_de;
        break;
      case 'it': 
        $descr= $this->descr_it;
        break;
      default:
        $descr= $this->descr;
    }
    if (!$descr) return $this->descr;
    return $descr;
  }
}

class WbRoom extends WbNamesDescr {
  var $avail;
  var $avgprice;
  var $id;
  var $occupancy;
  var $adults;
  var $children;
  var $imglink;
}

class WbGRoom {
  var $avail;
  var $rooms= array();
}

class WbOffer extends WbNamesDescr{
}

class CCRequirements {
  var $cc_required;
  var $cvc_required;
  var $cc_available= array();
}

class BookRequest {
  var $total_amount;
  var $clean_amount;
  var $rooms_amount;
  var $offer;
  var $cc;
  var $rooms_daily_prices;

  function room_prices($rid) {
    return $this->rooms_daily_prices[$rid];
  }
}

class WbCustomer {
  var $fname;
  var $lname;
  var $street;
  var $city;
  var $email;
  var $country;
  var $phone;
  var $remarks;
  var $arrival_hour;
}

class WbCC {
  var $cc_number;
  var $cc_family;
  var $cc_cvc;
  var $cc_owner;
  var $cc_exp_month;
  var $cc_exp_year;
}



class WbFacility {
  var $lcode;
  var $rooms= array();
  var $grooms= array();
  var $offers= array();

  var $request= 0;
  var $invoice= 0;

  function facility_available() {
    if ($this->rooms == -1 || $this->grooms == -1 || !(count($this->rooms) + count($this->grooms)))
      return 0;
    return 1;
  }

  function why_unavailable() {
    if (!(count($this->rooms) + count($this->grooms)))
      return -1;
    if ($this->rooms == -1)
      return $this->errcode;
  }

  function facility_offered() {
    if (count($this->offers)) return 1;
    return 0;
  }
  function get_rooms() {
    return $this->rooms;
  }
  function get_grooms() {
    return $this->grooms;
  }
  function get_offers() {
    return $this->offers;
  }
  function get_invoice() {
    return $this->invoice;
  }

  ##
  # Parse Last Request Properties
  ####
  function last_request_amount() {
    return $this->request->total_amount;
  }
  function last_request_clean_amount() {
    return $this->request->clean_amount;
  }
  function room_prices($rid) {
    $req= $this->request;
    return $req->room_prices($rid);
  }
  function last_request_offer() {
    return $this->request->offer;
  }
  function _get_cc() {
    return $this->request->cc;
  }
  function last_request_cc_required() {
    $cc= $this->_get_cc();
    return $cc->cc_required;
  }
  function last_request_cc_available() {
    $cc= $this->_get_cc();
    return $cc->cc_available;
  }
  function last_request_cc_cvc_required() {
    $cc= $this->_get_cc();
    return $cc->cvc_required;
  }

  ##
  # Time to Book!
  #####
  function book_now($token, $customer, $cc) {
    if ($this->request == 0 || $this->invoice != 0) return -1;
    $res= _book_now($token, $this, $customer, $cc);
  }
}

class WbFacilities {
  var $facilities;

  function get_facility($lcode) {
    foreach($this->facilities as $fac) {
      if ($fac->lcode == $lcode) return $fac;
    }
    return -1;
  }

  function is_available($lcode) {
    $fac= $this->get_facility($lcode);
    if ($fac == -1) return -1;
    return $fac->facility_available();
  }

  function why_unavailable($lcode) {
    $fac= $this->get_facility($lcode);
    if ($fac == -1) 
      return -1;
    return $fac->why_unavailable();
  }

  function get_facilities() {
    return $this->facilities;
  }

  function get_available() {
    $res= array();
    foreach($this->facilities as $fac) {
      if ($fac->facility_available())
        $res[]= $fac;
    }
    return $res;
  }

  function get_unavailable() {
    $res= array();
    foreach($this->facilities as $fac) {
      if (!$fac->facility_available())
        $res[]= $fac;
    }
    return $res;
  }
}

/*
 * THE Class
 */

class WuBook {
  var $token;
  var $wbfacs= array();
  var $account;
  var $pwd;

  function init_wubook($acc, $pwd, $hst= "https://wubook.net:443/xrwx/" ) {
    global $wbhost;
    $wbhost= $hst;
    $this->account= $acc;
    $this->pwd= $pwd;
    $this->token= -1;
  }

  /*
   * Utils, forget
   */
  function _get_token() {
    $tok= get_token($this->account, $this->pwd);
    if ($tok < 0) return -1;
    $this->token= $tok;
    return 0;
  }

  function _token_ready() {
    if ($this->token < 0) 
      $this->_get_token();
  }

  /*
   * Api: go there
   */

  function facilities_request($lcodes, $dfrom, $dto) {
    $this->_token_ready();
    $wbf= _facilities_request($this->token, $lcodes, $dfrom, $dto);
    $this->wbfacs= $wbf;
    return $wbf;
  }

  function get_facilities() {
    return $this->wbfacs;
  }

  function get_available_facilities() {
    return $this->wbfacs->get_available();
  }

  function get_unavailable_facilities() {
    return $this->wbfacs->get_unavailable();
  }

  function is_available($lcode) {
    $wbfacs= $this->get_facilities();
    if ($wbfacs == -1) return -1;
    return $wbfacs->is_available($lcode);
  }

  function get_offered_facilities() {
    $wbf= $this->get_available_facilities();
    $res= array();
    foreach($wbf as $wb) {
      if ($wb->facility_offered())
        $res[]= $wb;
    }
    return $res;
  }

  function get_not_offered_facilites() {
    $wbf= $this->get_available_facilities();
    $res= array();
    foreach($wbf as $wb) {
      if (!$wb->facility_offered())
        $res[]= $wb;
    }
    return $res;
  }

  function get_facility($lcode) {
    $wbfacs= $this->get_facilities();
    if ($wbfacs == -1) return -1;
    return $wbfacs->get_facility($lcode);
  }

  function get_facility_rooms($lcode) {
    $wbf= $this->get_facility($lcode);
    if ($wbf == -1) return -1;
    return $wbf->get_rooms();
  }
  function get_facility_grooms($lcode) {
    $wbf= $this->get_facility($lcode);
    return $wbf->grooms;
  }
  function get_facility_offers($lcode) {
    $wbf= $this->get_facility($lcode);
    return $wbf->offer;
  }

  function book_request($lcode, $rooms, $grooms) {
    $wbf= $this->get_facility($lcode);
    if ($wbf == -1) return -1;
    $br= _book_request($this->token, $wbf, $rooms, $grooms);
    return $wbf;
  }

  function book_now($lcode, $customer, $cc) {
    $wbf= $this->get_facility($lcode);
    $res= $wbf->book_now($this->token, $customer, $cc);
    return $res;
  }
}

function create_wbcustomer($fname, $lname, $street, $city, $country,
                           $email, $phone, $arrival_hour, $remarks) {
  $wbc= new WbCustomer();
  $wbc->fname= $fname;
  $wbc->lname= $lname;
  $wbc->street= $street;
  $wbc->city= $city;
  $wbc->country= $country;
  $wbc->email= $email;
  $wbc->phone= $phone;
  $wbc->arrival_hour= $arrival_hour;
  $wbc->remarks= $remarks;
  return $wbc;
}

function create_wbcc($family, $number, $owner, $expmonth, $expyear, $cvc= '') {
  $wbcc= new WbCC();
  $wbcc->cc_family= $family;
  $wbcc->cc_number= $number;
  $wbcc->cc_owner= $owner;
  $wbcc->cc_exp_month= $expmonth;
  $wbcc->cc_exp_year= $expyear;
  $wbcc->cc_cvc= $cvc;
  return $wbcc;
}

function init_wubook($acc, $pwd, $hst= "https://wubook.net:443/xrwx/") {
  $wb= new WuBook();
  $wb->init_wubook($acc, $pwd, $hst);
}
                            

/* ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ *
              Library End
 * ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

/*
 * Usage Example:
 *
 *   
 * Let's Go: init Wb!
 * Just initialize a WuBook Instance.
 *
 *    $wubook= new WuBook();
 *
 * Let's use our credentials:
 *
 *    $wubook->init_wubook('XX003', 'mypwd');
 *
 * Ok, you should know which facilities you can
 * control. Suppose you can control two faiclities
 * with lcodes= 111, 222
 *
 * Let's initialize facilities offer for
 * the period 12/08/2009 ~> 15/08/2009
 *
 *    $myfacs= array(111, 222);
 *    $wbf= $wubook->facilities_request($myfacs, '12/08/2009', '15/08/2009');
 *
 * $wbf is a WbFacilities Instance. Don't worry,
 * you can handle your facilities using the $wubook 
 * instance.  *
 * Now, let's see which rooms are available for the
 * facility 111 ($rooms will be an array(WbRoom)):
 *
 *    $rooms= $wubook->get_facility_rooms(111);
 *    foreach($rooms as $room) {
 *      print $room->get_name('en');
 *      print ' (availability: ';
 *      print $room->avail;
 *      print ', Avg. Price: ';
 *      print $room->avgprice;
 *      print ', Id: ';
 *      print $room->id;
 *      print ')';
 *      printf("\n");
 *    }
 *
 * We want to book one room with id =3. Let's require economical
 * conditions:
 *
 *    $wbf= $wubook->book_request(111, array(3=>1), array());
 *     // Now $wbf is a WbFacility Instance and you can
 *     // use class methods to obtain request information
 *    print $wbf->last_request_amount();
 *    printf("\n");
 *    print $wbf->last_request_clean_amount();
 *    printf("\n");
 *    $prices= $wbf->room_prices(1);
 *    foreach($prices as $p) {
 *      print $p;
 *      print ' ~ ';
 *    }
 *    printf("\n");
 *
 * Ei, I like these conditions, I want to confirm the request:
 *
 *    $cust= create_wbcustomer('mario', 'rossi', 'via via', 'city', 'IT',
 *                         'mymail@addre.ss', '333333', '12:34', 'My Remarks');
 *    $cc= create_wbcc(1, '43423423421234', 'mario rossi', '11', '2009', '870');
 *    $res= $wubook->book_now(111, $cust, $cc);
 *
 */
?>
