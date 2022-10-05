<?php namespace SmashPig\Core\Listeners;

use SmashPig\Core\Http\Request;
use SmashPig\Core\Http\Response;
use SmashPig\Core\Logging\Logger;

abstract class SoapListener extends ListenerBase {

	/** @var \SoapServer Request processing object */
	protected $server;

	/** @var string URI to the WDSL defining the commands available in this server */
	protected $wsdlpath = '';

	/** @var array Mapping of WSDL entity names to PHP classes. Key is the entity name, value is the fully defined class name */
	protected $classmap = [];

	public function __construct() {
		parent::__construct();
		$this->server = new \SoapServer(
			$this->wsdlpath,
			[
				 'classmap'   => $this->classmap,
				 'cache_wsdl' => WSDL_CACHE_BOTH,
			]
		);
	}

	public function execute( Request $request, Response $response ) {
		parent::execute( $request, $response );

		Logger::info( "Starting processing of listener request from {$this->request->getClientIp()}" );

		try {
			$this->doIngressSecurity();
			$soapData = $request->getRawRequest();
			$tl = Logger::getTaggedLogger( 'RawData' );

			// remove expiryDate from soapData for reason and additionalData;
			// replace mm/yyyy to blank only for log
			$patterns = [ '/(\d{1,2})\/(19|20)(\d{2})/',
				'/^\s*{(\w+)}\s*=/' ];
			$replace = [ '', '$\1 =' ];
			$tl->info( preg_replace( $patterns, $replace, $soapData ) );

			$response->sendHeaders();

			/* --- Unfortunately because of how PHP handles SOAP requests we cannot do the fully wrapped
					loop like we could in the REST listener. Instead it is up to the listener itself to
					do the required call to $this->processMessage( $msg ).

					It is also expected that inside the handle() context that an exception will throw a SOAP
					fault through $this->server->fault() instead of doing a $response->kill_response() call.
			*/
			$this->server->setObject( $this );
			$this->server->handle( $soapData );

			/* We disable output late in the game in case there was a last minute exception that could
				be handled by the SOAP listener object inside the handle() context. */
			$response->setOutputDisabled();
		} catch ( ListenerSecurityException $ex ) {
			Logger::notice( 'Message denied by security policy, death is me.', null, $ex );
			$response->setStatusCode( 403, "Not authorized." );
		}
		catch ( \Exception $ex ) {
			Logger::error( 'Listener threw an unknown exception, death is me.', null, $ex );
			$response->setStatusCode( 500, "Unknown listener exception." );
		}

		Logger::info( 'Finished processing listener request' );
	}
}
