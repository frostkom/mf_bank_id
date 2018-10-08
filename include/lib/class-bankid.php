<?php
/**
 * Class to handle BankID Integration
 *
 * @link        http://livetime.nu
 * @since       1.0.0
 * @author      Alexander Karlsson <alexander@livetime.nu>
 * @package     BankID
 */

use \SoapClient as SoapClient;
use \Exception as Exception;

class BankID
{
    /**
     * SOAPClient to make API requests to the BankID central server.
     *
     * @since       1.0.0
     * @var         SOAPClient      $soap       Current SOAPClient
     */
    protected $_soap;

    /**
     * Certificates to verify against BankID.
     *
     * @since       1.0.0
     * @var         string          $certs      File path to certificate files.
     */
    protected $_certs;

    /**
     * All the API related URLs.
     *
     * @since       1.0.0
     * @var         string          $api_url        URL to the API.
     * @var         string          $wsdl_url       URL to the API structure.
     * @var         string          $verify_cert    Path to the local CA file to verify the central BankID Server.
     */
    private $_settings;

    /**
     * CN to match.
     *
     * @since       1.0.0
     * @var         string          $_cn_match      The CN to match when making requests.
     */
    protected $_cn_match;

    /**
     * During the initialization of our class we will set the correct urls and ceritifications depending on if
     * it's run as a test or not.
     *
     * @since       1.0.0
     * @param       options     $options       associative array with your setup option.
     */
    public function __construct($user_settings = array())
    {
		/*if(file_exists($user_settings['cert_path']))
		{
			do_log("Cert exists: ".$user_settings['cert_path']);
		}

		else
		{
			do_log("Cert does NOT exist: ".$user_settings['cert_path']);
		}*/

        $fixed_settings = array(
             'test_server' => array(
                 'api_url'   => 'https://appapi.test.bankid.com/rp/v4',
                 'wdsl_url'  => 'https://appapi.test.bankid.com/rp/v4?wsdl',
                 'cert_path' => JPATH_BASE.'/certs/certname.pem',
                 'peer_name' => 'appapi.test.bankid.com'
             ),
             'production_server' => array(
                 'api_url'   => 'https://appapi.bankid.com/rp/v4',
                 'wdsl_url'  => 'https://appapi.bankid.com/rp/v4?wsdl',
                 //'cert_path' => JPATH_BASE.'/'.$user_settings['cert_path'],
				'cert_path' => $user_settings['cert_path'],
                 'peer_name' => 'appapi.bankid.com'
             )
        );

		if($user_settings['test_mode'] == 1)
		{
			$default_settings = array(
				'production'  => false,
				'certificate' => JPATH_BASE.'/certs/certname.pem',
			);
		}

		else
		{
			$default_settings = array(
				'production'  => true,
				//'certificate' => JPATH_BASE.'/'.$user_settings['cert_path']
				'certificate' => $user_settings['cert_path'],
			);
		}

        $user_settings = array();
        // check options for unknown entries
        $invalid_settings = array_diff_key($user_settings, $default_settings);

		if(count($invalid_settings) > 0)
		{
			$returnarray = array(
				'error' => 1,
				'msg' => "invalid settings: " . implode(array_keys(', ',$invalid_settings)),
			);

			echo json_encode($returnarray);
			exit;
        }

        // merge the final settings together
        $this->_settings = array_merge($fixed_settings, array_replace($default_settings, array_intersect_key($user_settings, $default_settings)));
    }

    private function &connect()
	{
        if($this->_soap === null)
		{
            // open an encrypted connection to the BankID server
            $server = $this->_settings['production'] ? $this->_settings['production_server'] : $this->_settings['test_server'];
            $certificate_path = $this->_settings['certificate'];

            $context_options = array(
				 'http' => array(
        		    'user_agent' => 'PHPSoapClient'
		         ),
                'ssl' => array(
                    'local_cert'            => $certificate_path,
                    'cafile'                => $server['cert_path'],
                    'verify_peer'           => false,
                    'verify_peer_name'      => false,
                    'verify_depth'          => 5,
                    'peer_name'             => $server['peer_name'],
                    'disable_compression'   => true,
                    'SNI_enabled'           => true,
		   			'allow_self_signed' => true,
                    'ciphers'               => 'ALL!EXPORT!EXPORT40!EXPORT56!aNULL!LOW!RC4'
                )
            );

            // make sure test or production RP certificate exists
            if(!file_exists($certificate_path))
			{
				$returnarray = array(
					'error' => 1,
					'msg' => "Unable to load your certificate file! (".$certificate_path.")",
				);

				echo json_encode($returnarray);
				exit;
            }

            // make sure the bankid server certificate for the selected server exists
            if(!file_exists($server['cert_path']))
			{
				$returnarray = array(
					'error' => 1,
					'msg' => "Unable to find bankid certificate: " . $server['cert_path'],
				);

				echo json_encode($returnarray);
				exit;
            }

            $ssl_context = stream_context_create( $context_options );

			if($ssl_context === null)
			{
				$returnarray = array(
					'error' => 1,
					'msg' => "Failed to create stream context for communication with the bank-id server (" . $server['peer_name'] . ")",
				);

				echo json_encode($returnarray);
				exit;
            }

            // Connect and store our SOAP connection.
            $this->_soap = new SoapClient( $server['wdsl_url'], array(
                'stream_context' => $ssl_context
            ));
        }

        return $this->_soap;
    }

    /**
     * Start an authentication process for a user against BankID.
     *
     * @since       1.0.0
     * @param       string      $ssn        The SSN of the person you want to authenticate, format YYYYMMDDXXXX
     * @param       string      $kwargs     Keyword argument array to allow any number of the additional BankID settings.
     * @return      string                  Valid API response or null
     */
    public function authenticate( $ssn, $kwargs = array() )
    {
        $error = null;
        try {
            $soap = $this->connect();
            $kwargs['personalNumber'] = $ssn;
            $out = $soap->Authenticate( $kwargs );
        } catch ( \SoapFault $fault ) {
            $out = null;

			if(!empty($fault->detail->RpFault->detailedDescription))
			{
				$error = $fault->detail->RpFault->detailedDescription;
			}
			else
			{
	            $error = $fault->faultstring;
			}
        }

        return array($out, $error);
    }

    /**
     * Start a signing process for a user against BankID.
     *
     * @since       1.0.0
     * @param       string      $ssn            The SSN of the person you want to sign the data.
     * @param       string      $visible_data   The data that the user will be prompted to sign.
     * @param       string      $hidden_data    The data that will be held at BankIDs servers. Example use: Verify that the data signed is correct and hasn't been tampered with.
     * @param       array       $kwargs         Keyword argument array to allow any of the additional BankID settings.
     * @return                                  Valid API response or null
     */
    public function sign( $ssn, $visible_data, $hidden_data = '', $kwargs = array() )
    {
        $error = null;
        try {
            $soap = $this->connect();
            $kwargs['personalNumber'] = $ssn;
            $kwargs['userVisibleData'] =  base64_encode( $visible_data );
            $kwargs['userNonVisibleData'] = base64_encode( $hidden_data );
            $out = $soap->Sign( $kwargs );
        } catch ( \SoapFault $fault ) {
            $out = null;
            $error = Utils::is_known_error($fault) ? $fault->faultstring : null;
        }

        return array($out, $error);
    }

    /**
     * Collect a response from an ongoing order.
     *
     * @since       1.0.0
     * @param       string      $order_ref      The order reference to collect from.
     * @return                                  Valid BankID response or null
     */
    public function collect( $order_ref )
    {
        $error = null;
        try {
            $soap = $this->connect();
            $out = $soap->Collect( $order_ref );
        } catch ( \SoapFault $fault ) {
            $out = null;
			if(!empty($fault->detail->RpFault->detailedDescription))
			{
				$error = $fault->detail->RpFault->detailedDescription;
			}
			else
			{
	            $error = $fault->faultstring;
			}
         }

        return array($out, $error);
    }
}
