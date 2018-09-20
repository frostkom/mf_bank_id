<?php

/**
 * Class to contain Util functions.
 *
 * @link        http://livetime.nu
 * @since       1.0.0
 * @author      Alexander Karlsson <alexander@livetime.nu>
 * @package     BankID
 */

//namespace BankID;

class Utils
{

    const COLLECT_ERRORS       = 'collect';
    const AUTHENTICATION_ERROR = 'authentication';
    const SIGNING_ERROR        = 'signing';
    const PROGRESS_STATUS      = 'progress';

    private static $is_initialized = false;

    private static $error_types;

    private static $progress_status_types;

    private static $signing_errors;
    private static $authentication_errors;
    private static $collect_errors;

    private static function init() {
        if(self::$is_initialized){
            return;
        }

        self::$signing_errors = array(
            'INVALID_PARAMETERS' => array(
                'reason' => 'Invalid parameter. Invalid use of method',
                'action' => "RP must not try the same request again. This is an internal error within RP's system and must not be communicated to the user as a BankID-error.",
            ),
            'ALREADY_IN_PROGRESS' => array(
                'reason' => 'An order for this user is already in progress. The order is aborted. No order is created.',
                'action' => 'RP must inform the user that a login or signing operation is already initiated for this user. Message RFA3 should be used.',
                'message'=> 'RFA3'
            ),
            'INTERNAL_ERROR' => array(
                'reason' => 'Internal technical error in the BankID system.',
                'action' => 'RP must not automatically try again. RP must inform the user that a technical error has occurred. Message RFA5 should be used.',
                'message'=> 'RFA5'
            ),
            'RETRY' => array(
                'reason' => 'Internal technical error in the BankID system.',
                'action' => 'RP must not automatically try again. RP must inform the user that a technical error has occurred. Message RFA5 should be used.',
                'message'=> 'RFA5'
            ),
            'ACCESS_DENIED_RP' => array(
                'reason' => 'RP does not have access to the service or requested operation.',
                'action' => 'RP must not try the same request again. This is an internal error within RP\'s system and must not be communicated to the user as a BankID'
            )
        );
        self::$authentication_errors = self::$signing_errors; // shares the same error definitions

        self::$collect_errors = array(
            'INVALID_PARAMETERS' => array(
                'reason' => "Invalid parameter. Invalid use of method. Using an orderRef that previously resulted in COMPLETE. The order cannot be collected twice.",
                'action' => "RP must not try the same request again. This is an internal error within RP's system and must not be communicated to the user as a BankID-error."
            ),
            'REQ_PRECOND' => array(
                'reason' => "Not used.",
                'action' => ""),
            'REQ_ERROR' => array(
                'reason' => "Not used.",
                'action' => ""
            ),
            'REQ_BLOCKED' => array(
                'reason' => "Not used.",
                'action' =>  ""
            ),
            'INTERNAL_ERROR'      => array(
                'reason' => "Internal technical error in the BankID system.",
                'action' => "RP must not automatically try again. RP must inform the user. Message RFA5.",
                'message'=> 'RFA5'
            ),
            'RETRY' => array(
                'reason' => "Internal technical error in the BankID system.",
                'action' => "RP must not automatically try again. RP must inform the user. Message RFA5.",
                'message'=> 'RFA5'
            ),
            'ACCESS_DENIED_RP' => array(
                'reason' => "RP does not have access to the service, requested operation or the orderRef.",
                'action' => "RP must not try the same request again. This is an internal error within RP's system and must not be communicated to the user as a BankID-error."
            ),
            'CLIENT_ERR' => array(
                'reason' => "Internal technical error. It was not possible to create or verify the transaction.",
                'action' => "RP must not automatically try again. RP must inform the user. Message RFA12.",
                'message'=> 'RFA12'
            ),
            'EXPIRED_TRANSACTION' => array(
                'reason' => "The order has expired. The BankID security app/program did not start, the user did not finalize the signing or the RP called collect too late.",
                'action' => "RP must inform the user. Message RFA8.",
                'message'=> 'RFA8'
            ),
            'CERTIFICATE_ERR' => array(
                'reason' => "This error is returned if: 1) The user has entered wrong security code too many times in her mobile device. The Mobile BankID cannot be used. 2) The users BankID is revoked. 3) The users BankID is invalid.",
                'action' => "RP must inform the user. Message RFA16.",
                'message'=> 'RFA16'
            ),
            'USER_CANCEL' => array(
                'reason' => "The user decided to cancel the order.",
                'action' => "RP must inform the user. Message RFA6.",
                'message'=> "RFA6"
            ),
            'CANCELLED' => array(
                'reason' => "The order was cancelled. The system received a new order for the user.",
                'action' => "RP must inform the user. Message RFA3.",
                'message'=> 'RFA3'
            ),
            'START_FAILED' => array(
                'reason' => "The user did not provide her ID, or the RP requires autostarttoken to be used, but the client did not start within a certain time limit. The reason may be:: 1) RP did not use autoStartToken when starting BankID security program/app. 2) The client software was not installed or other problem with the user’s computer.",
                'action' => "1) The RP must use autoStartToken when starting the client 2) The RP must inform the user. Message RFA17.",
                'message' => 'RFA17'
            ),
            'ALREADY_COLLECTED' => array(
                'reason' => "Not used.",
                'action' => "",
            )
        );

        self::$progress_status_types = array(
            'OUTSTANDING_TRANSACTION' => array(
                'reason' => 'The order is being processed. The client has not yet received the order. The status will later change to NO_CLIENT, STARTED or USER_SIGN.',
                'action' => 'If RP tried to start the client automatically, the RP should inform the user that the app is starting. Message RFA13 should be used. If RP did not try to start the client automatically, the RP should inform the user that she needs to start the app. Message RFA1 should be used.',
                'message' => array('RFA13', 'RFA1')
            ),
            'NO_CLIENT' => array(
                'reason' => 'The order is being processed. The client has not yet received the order. If the user did not provide her ID number the error START_FAILED will be returned in this situation.',
                'action' => 'If RP tried to start the client automatically: This status indicates that the start failed or the users BankID was not available in the started client. RP should inform the user. Message RFA1 should be used. If RP did not try to start the client automatically: This status indicates that the user not yet has started her client. RP should inform the user. Message RFA1 should be used.',
                'message'=> 'RFA1'
            ),
            'STARTED' => array(
                'reason' => 'A client has been started with the autostarttoken but a usable ID has not yet been found in the started client. When the client starts there may be a short delay until all ID:s are registered. The user may not have any usable ID:s at all, or has not yet inserted their smart card.',
                'action' => 'If RP does not require the autoStartToken to be used and the user provided her ID number the RP should inform the user of possible solutions. Message RFA14 should be used. If RP require the autostarttoken to be used or the user did not provide her ID number the RP should inform the user of possible solutions. Message RFA15 should be used. Note: STARTED is not an error, RP should keep on polling using collect.',
                'message' => array('RFA14', 'RFA15')
            ),
            'USER_SIGN' => array(
                'reason' => 'The client has received the order.',
                'action' => 'The RP should inform the user. Message RFA9 should be used.',
                'message'=> 'RFA9'
            ),
            'USER_REQ' => array(
                'reason' => 'Not used',
                'action' => ''
            ),
            'COMPLETE' => array(
                'reason' => 'The user has provided the security code and completed the order. Collect response includes the signature, user information and the ocsp response.',
                'action' => 'RP should control the user information returned in userInfo and continue their process.'
            )
        );

        self::$is_initialized = true;
    }

    public static function message_ids_for($type, $identifier) {
        $id = null;
        $description = self::find_description($type, $identifier);
        if(!is_null($description) && array_key_exists('message', $description)) {
            $id = is_array($description['message']) ? $description['message'] : array($description['message']);
        }
        return $id;
    }

    /**
     * Normalize the given input to UTF-8
     *
     * @since       1.0.0
     * @param       string      The data to normalize
     * @return      string      The data converted to UTF-8
     */
    public static function normalize_text( $input )
    {
        return iconv( mb_detect_encoding( $input, mb_detect_order(), true ), "UTF-8", $input );
    }

    /**
     * Get the path and make sure file exists for the specificed certificate
     * name or certficate absolute path.
     *
     * @since       1.0.1
     * @param       string      $name       The certificate name.
     * @return      string                  Full certificate path.
     */
    public static function get_certificate( $name )
    {
        $cert_path = dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'certs' . DIRECTORY_SEPARATOR . $name;

        if ( file_exists( $cert_path ) )
            return $cert_path;
        else if(file_exists( $name ))
            return $name;

        return null;
    }

    public static function is_known_error(\SoapFault $fault) {
        return self::is_known_error_identifier($fault->faultstring);
    }

    private static function is_known_error_identifier($identifier) {
        self::init();
        return array_key_exists($identifier, self::$signing_errors) || array_key_exists($identifier, self::$collect_errors);
    }

    private static function find_description($type, $identifier) {
        self::init();
        switch ($type) {
            case self::COLLECT_ERRORS:
                $definitions = self::$collect_errors;
                break;
            case self::AUTHENTICATION_ERROR:
                $definitions = self::$authentication_errors;
                break;
            case self::SIGNING_ERROR:
                $definitions = self::$signing_errors;
                break;
            case self::PROGRESS_STATUS:
                $definitions = self::$progress_status_types;
                break;
            default:
                return null;
                break;
        }
        return isset($definitions[$identifier]) ? $definitions[$identifier] : null;
    }
}
