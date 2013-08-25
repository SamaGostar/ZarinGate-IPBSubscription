<?php

/**
 * Product Title:		zarinpalzg gateway
 * Product Version:		1.0
 * Author:				mr.wosi
 * Website URL:			http://skinod.com/
 * Email:				wolaws@gmail.com
 */

if ( ! defined( 'GW_CORE_INIT' ) )
{
	print "You cannot access this module in this manner";
	exit();
}

//--------------------------------------------------------------------------
// DEFINITIONS EXPECTED AT THIS POINT
//--------------------------------------------------------------------------
// GW_URL_VALIDATE : The url for validating payment
// GW_URL_PAYDONE  : The url that the gatways returns the viewer to after
//                 : payment processed successfully
// GW_URL_PAYCANCEL: The url that the gatways returns the viewer to after
//                 : payment processed unsuccessfully or when cancelled
//--------------------------------------------------------------------------
// ITEM ARRAY
//--------------------------------------------------------------------------
// 'currency_code'    => Currency code,
// 'member_unique_id' => member's ID,
// 'member_name'      => member's NAME,
// 'member_email'     => member's EMAIL,
// 'package_cost'     => Requested package cost
// 'package_id'       => Requested package ID
// 'package_title'    => Requested package title
// 'duration_int'     => Requested package duration int  (ie: 12)
// 'duration_unit'    => Requested package duration unit (ie: m,d,y,w) [ month, day, year, week ]
// 'company_email'    => Company's email address
// 'ttr_int'          => Time to run (Time left on current package) integar (ie 3)
// 'ttr_unit'         => Time to run (Time left on current package) unit (ie w)
// 'ttr_balance'      => Time to run (Balance left on current package)
// 'ttr_package_id'   => Current package id (used for upgrading)
//--------------------------------------------------------------------------

class gatewayApi_zarinpalzg extends apiCore
{
	/**
	 * Identify this class
	 *
	 * @access	public
	 * @var		string
	 */
	const API_NAME = 'zarinpalzg';
	
	/**
	 * Can I do recurring billing?
	 *
	 * @access	 public
	 * @var		 boolean
	 */
	public $ALLOW_RECURRING = TRUE;
	
	/**
	 * Can I do upgrades?
	 *
	 * @access	 public
	 * @var		 boolean
	 */
	public $ALLOW_UPGRADES = TRUE;
	
	/**
	 * Can I post back?
	 *
	 * @access	 public
	 * @var		 boolean
	 */
	public $FORBID_POSTBACK = TRUE;
	
	
	public $item = array();
	
	
	function __construct( ipsRegistry $registry )
	{
		parent::__construct( $registry );
		require_once(IPSLib::getAppDir('subscriptions') . '/sources/classes/nusoap.php');
	}
	
	
	/*-------------------------------------------------------------------------*/
	// Generate hidden fields [ Recurring, normal screen ]
	/*-------------------------------------------------------------------------*/
	
	function makeFields_normal_recurring( $items=array() )
	{
		$this->item = $items;
		return $this->compileFields();
	}
	
	/*-------------------------------------------------------------------------*/
	// Generate hidden fields [ Recurring, upgrade screen ]
	/*-------------------------------------------------------------------------*/
	
	function makeFields_upgrade_recurring( $items=array() )
	{
		$this->item = $items;
		return $this->compileFields();
	}
	
	/*-------------------------------------------------------------------------*/
	// Generate hidden fields [ normal screen ]
	/*-------------------------------------------------------------------------*/
	
	function makeFields_normal( $items=array() )
	{
		$this->item = $items;
		return $this->compileFields();
	}
	
	/*-------------------------------------------------------------------------*/
	// Generate hidden fields [ upgrade screen ]
	/*-------------------------------------------------------------------------*/
	
	function makeFields_upgrade( $items=array() )
	{
		$this->item = $items;
		return $this->compileFields();
	}
	
	/*-------------------------------------------------------------------------*/
	// Generate Purchase button
	/*-------------------------------------------------------------------------*/
	
	function makePurchaseButton()
	{
		return '<input type="submit" class="input_submit" value="پرداخت" />';
	}
	
	/*-------------------------------------------------------------------------*/
	// Generate Form action [normal]
	/*-------------------------------------------------------------------------*/
	
	function makeFormAction_normal()
	{
		$merchantID = $this->item['vendor_id'];
		
		if(!$merchantID)
			ipsRegistry::getClass('output')->showError( 'zarinpalzg_novender', 'Novender_id', FALSE, '', 403 );
		
		$amount = floor($this->item['package_cost']) / 10;
		$callBackUrl = GW_URL_VALIDATE . "&tid=" . $this->item['transaction_id'] . "&verification=" . md5( $this->item['member_unique_id'] . $this->item['package_id'] . $this->settings['sql_pass'] );
		
		$title = $this->item['package_title'];
		
		$gate = new SoapClient('https://de.zarinpal.com/pg/services/WebGate/wsdl', array('encoding'=>'UTF-8'));
		$res = $gate->PaymentRequest(
	array(
					'MerchantID' 	=> $merchantID ,
					'Amount' 		=> $amount ,
					'Description' 	=> $title ,
					'Email' 		=> '' ,
					'Mobile' 		=> '' ,
					'CallbackURL' 	=> $callBackUrl

					)
			);
		if($res->Status == 100)
		{
		return 'https://www.zarinpal.com/pg/StartPay/' . $result->Authority . '/ZarinGate';
		}else{
			echo'ERR: '.$res->Status;
		}
	}

	/*-------------------------------------------------------------------------*/
	// Generate Form action [upgrade]
	/*-------------------------------------------------------------------------*/
	
	function makeFormAction_upgrade()
	{
		return $this->makeFormAction_normal();
	}
	
	/*-------------------------------------------------------------------------*/
	// Generate Form action [normal, recurring]
	/*-------------------------------------------------------------------------*/
	
	function makeFormAction_normal_recurring()
	{
		return $this->makeFormAction_normal();
	}
	
	/*-------------------------------------------------------------------------*/
	// Generate Form action [upgrade, recurring]
	/*-------------------------------------------------------------------------*/
	
	function makeFormAction_upgrade_recurring()
	{
		return $this->makeFormAction_normal();
	}
	
	/*-------------------------------------------------------------------------*/
	// Validate Payment
	// What we need to return:
	// 'currency_code'      => Currency code,
	// 'payment_amount'     => Amount paid,
	// 'payment_status'     => REFUND, ONEOFF, RECURRING
	// 'member_unique_id'   => member's ID,
	// 'purchase_package_id'=> Purchased package ID
	// 'current_package_id' => Current package ID (used for upgrading)
	// 'verified'           => TRUE , FALSE (Gateway verifies info as correct)
	// 'subscription_id'    => (Used for recurring payments)
	// 'transaction_id'     => Gateway transaction ID
	/*-------------------------------------------------------------------------*/
	
	function validatePayment($sets=array())
	{
		$tid = intval($this->request['tid']);
		
		if(!$tid)
			return $this->showerror();
		
		$merchantID = $sets['vendor_id'];
		
		if(!$merchantID)
			ipsRegistry::getClass('output')->showError( 'zarinpalzg_novender', 'Novender_id', FALSE, '', 403 );
			
		$trans = $this->DB->buildAndFetch(array(
			'select' => '*',
			'from' => 'subscription_trans',
			'where' => "subtrans_id='{$tid}'"
		));
		
		if(!$trans['subtrans_id'])
			return $this->showerror();
		
		$amount = floor($trans['subtrans_to_pay'] - $trans['subtrans_paid']) / 10;
	
		$au = $this->request['Authority'];
		$st = $this->request['Status'];
		if ($st == 'OK')
		{
				$gate = new nusoap_client('https://de.zarinpal.com/pg/services/WebGate/wsdl', 'wsdl');
				$res = $gate->call("PaymentVerification", array(
					array(
							'MerchantID'	 => $merchantID ,
							'Authority' 	 => $au ,
							'Amount'	 	=> $amount
						)));
				
				if ( $res->Status == 100 )
				{
					$return = array(
						'currency_code'      => trim('IRR'),
						'payment_amount'     => $amount * 10,
						'member_unique_id'   => $trans['subtrans_member_id'],
						'subtrans_id'		 => $trans['subtrans_id'],
						'purchase_package_id'=> $trans['subtrans_sub_id'],
						'current_package_id' => '',
						'verified'           => TRUE,
						'verification'		 => $this->request['verification'],
						'subscription_id'    => '0-'.intval($trans['subtrans_member_id']),
						'transaction_id'     => $tid,
						'renewing'			 => 0,
						'payment_status'     => 'ONEOFF',
						'state'     => 'paid',
					);
					
					return $return;
				}else{
					echo'ERR: '.$res->Status;
				}
		}
		return $this->showerror();
	}
	
	public function showerror() {
		$this->error = 'not_valid';
		return array( 'verified' => FALSE );
	}
	
	//---------------------------------------
	// Return ACP Package  Variables
	//
	// Returns names for the package custom
	// fields, etc
	//---------------------------------------
	
	function acpReturnPackageData()
	{
		return array( 'subextra_custom_1' => array( 'used' => 0, 'varname' => '' ),
					  'subextra_custom_2' => array( 'used' => 0, 'varname' => '' ),
					  'subextra_custom_3' => array( 'used' => 0, 'varname' => '' ),
					  'subextra_custom_4' => array( 'used' => 0, 'varname' => '' ),
					  'subextra_custom_5' => array( 'used' => 0, 'varname' => '' ),
					 );
	}
	
	//---------------------------------------
	// Return ACP Method Variables
	//
	// Returns names for the package custom
	// fields, etc
	//---------------------------------------
	
	function acpReturnMethodData()
	{
		return array( 'submethod_custom_1' => array( 'used' => 0, 'varname' => '' ),
					  'submethod_custom_2' => array( 'used' => 0, 'varname' => '' ),
					  'submethod_custom_3' => array( 'used' => 0, 'varname' => '' ),
					  'submethod_custom_4' => array( 'used' => 0, 'varname' => '' ),
					  'submethod_custom_5' => array( 'used' => 0, 'varname' => '' ),
					 );
	}
	
	/*-------------------------------------------------------------------------*/
	// INSTALL ROUTINES
	/*-------------------------------------------------------------------------*/
	
	function acpInstallGateway()
	{
		$this->db_info = array( 'human_title'         => 'zarinpalzg',
								'human_desc'		  => 'zarinpalzg gateway for iranian.',
								'module_name'         => self::API_NAME,
								'allow_creditcards'   => 1,
								'allow_auto_validate' => 1,
								'default_currency'    => 'IRR' );
																   
		$this->install_lang = array( 'zarinpalzg_novender' => 'Please report to admin' );
	}
}
