<?php
require_once '../../../../init.php';
\IPS\Session\Front::i();

try
{
	$transaction = \IPS\nexus\Transaction::load(\IPS\Request::i()->nexusTransactionId);
	if($transaction->status !== \IPS\nexus\Transaction::STATUS_PENDING)
		throw new \OutofRangeException;
}
catch (\OutOfRangeException $e)
{
	\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=nexus&module=payments&controller=checkout&do=transaction&id=&t=" . \IPS\Request::i()->nexusTransactionId, 'front', 'nexus_checkout', \IPS\Settings::i()->nexus_https ) );
}

try
{
	$trans_id = \IPS\Request::i()->trans_id;
    $order_id = \IPS\Request::i()->order_id;

	$data = array(	
		round((string) $transaction->amount->amount),
        $trans_id,
        $order_id
	);
	
	$res = $transaction->method->api($data, true);	
	if($res == 0)
	{
		$transaction->gw_id = $au;
		$transaction->save();
		$transaction->checkFraudRulesAndCapture(NULL);
		$transaction->sendNotification();
		\IPS\Session::i()->setMember( $transaction->invoice->member ); 
		\IPS\Output::i()->redirect( $transaction->url() );
	}		

	throw new \OutofRangeException;	
}
catch ( \Exception $e )
{
	\IPS\Output::i()->redirect( $transaction->invoice->checkoutUrl()->setQueryString( array( '_step' => 'checkout_pay', 'err' => $transaction->member->language()->get( 'gateway_err' ) ) ) );
}