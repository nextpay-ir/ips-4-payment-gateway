<?php
namespace IPS\nexus\Gateway;

if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

class _Nextpay extends \IPS\nexus\Gateway
{
	public function checkValidity( \IPS\nexus\Money $amount, \IPS\GeoLocation $billingAddress )
	{
		if($amount->currency != 'IRR')
		{
			return FALSE;
		}
		
		return parent::checkValidity( $amount, $billingAddress );
	}
	
	public function auth( \IPS\nexus\Transaction $transaction, $values, \IPS\nexus\Fraud\MaxMind\Request $maxMind = NULL, $recurrings = array() )
	{		
		$transaction->save();
		
		$data = array(
			round((string) $transaction->amount->amount), 
			(string) \IPS\Settings::i()->base_url . 'applications/nexus/interface/gateways/nextpay.php?nexusTransactionId=' . $transaction->id,
		);
		
		$res = $this->api($data);
		if(strlen($res) > 5)
			\IPS\Output::i()->redirect("https://api.nextpay.org/gateway/payment/{$res}");
	
		throw new \RuntimeException;
	}
		
	public function capture( \IPS\nexus\Transaction $transaction )
	{

	}
	
	public function settings( &$form )
	{

        $settings = json_decode( $this->settings, TRUE );
		$form->add( new \IPS\Helpers\Form\Text( 'nextpay_api', $settings['api'], TRUE ) );
	}


	public function testSettings( $settings )
	{		
		return $settings;
	}
	
	public function api($data=array(), $verify=false)
	{	
		$settings = json_decode($this->settings, true);
		try 
		{

            if(!$verify){
                $client = new SoapClient('https://api.nextpay.org/gateway/token.wsdl', array('encoding' => 'UTF-8'));
                $rs = $client->TokenGenerator(
                    array(
                        'api_key' 	=> $settings['api'],
                        'amount' 	=> $data[0]/10,
                        'order_id' 	=> md5(uniqid(rand(), true)),
                        'callback_uri' 	=>  $data[1]
                    )
                );
                $rs = $rs->TokenGeneratorResult;

                if(intval($rs->code) == -1){
                    $result =  $rs->trans_id ;
                }else{
                    die();
                }

            }else{
                $client = new SoapClient('https://api.nextpay.org/gateway/verify.wsdl', array('encoding' => 'UTF-8'));
                $rs = $client->PaymentVerification(
                    array(
                        'api_key'	 => $settings['api'],
                        'trans_id' 	 => $data[1],
                        'order_id' => $data[2],
                        'amount'	 => $data[0]/10
                    )
                );
                $rs = $rs->PaymentVerificationResult;
                $result = $rs->code ;
            }

		}
		catch ( \ Exception $e) 
		{
			$result = '-116';
		} 

		return $result;
	}
}