<?php

define('EVENTREBELS_API_URL', 'https://rptsvr.eventrebels.com/er/API/EROnline/');

class EventRebels_API
{
    protected $curl;

    function __construct($accountApiToken, $activityToken, $verify_ssl = true)
    {
        $this->accountApiToken = $accountApiToken;
        $this->activityToken = $activityToken;
        $this->verify_ssl = $verify_ssl;
    }

    /**
     * Make API request.
     *
     * @access public
     * @param string $action
     * @param array $options (default: array())
     * @param string $method (default: 'GET')
     * @param int $expected_code (default: 200)
     * @return array or int
     */
    function make_request($action, $options = [], $method = 'GET', $expected_code = 200)
    {
        $options = [
            'AccountToken' => $this->accountApiToken,
            'ActivityToken' => $this->activityToken
        ] + $options;
        $request_options = ($method == 'GET') ? '?' . http_build_query($options) : null;

        /* Build request URL. */
        $request_url = EVENTREBELS_API_URL . $action . '.jsp' . $request_options;

        /* Setup request arguments. */
        $args = [
            'headers' => [
                'Accept' => 'text/xml'
            ],
            'method' => $method,
            'sslverify' => $this->verify_ssl
        ];

        /* Add request options to body of POST and PUT requests. */
        if ($method == 'POST' || $method == 'PUT') {
            $args['body'] = $options;
        }

        /* Execute request. */
        $result = wp_remote_request($request_url, $args);

        /* If WP_Error, throw exception */
        if (is_wp_error($result)) {
            throw new Exception('Request failed. '. $result->get_error_message());
        }

        /* If response code does not match expected code, throw exception. */
        if ($result['response']['code'] !== $expected_code) {
            if ($result['response']['code'] == 400) {
                throw new Exception('Input is in the wrong format.');
            } elseif ($result['response']['code'] == 401) {
                throw new Exception('API credentials invalid.');
            } else {
                throw new Exception(sprintf('%s: %s', $result['response']['code'], $result['response']['message']));
            }
        }

        /* If response body contains ERROR in the first chars, throw exception. */
        if (strpos($result['body'], 'ERROR') !== false && strpos($result['body'], 'ERROR') < 6) {
            throw new Exception(sprintf('%s: %s', $result['response']['code'], $result['body']));
        }

        $xml = simplexml_load_string($result['body'], 'SimpleXMLElement', LIBXML_NOCDATA);
        $json = json_encode($xml);

        return json_decode($json, true);
    }

    /**
     * Get all fees.
     *
     * @access public
     * @return void
     */
    function get_fees()
    {
        $fees = $this->make_request('getFeesXML');

        if (isset($fees['Fee'])) {
            return $fees['Fee'];
        }

        return $fees;
    }

    /**
     * Register a contact.
     *
     * @access public
     * @param array $contact
     * @return array $contact
     */
    function register_contact($contact)
    {
        $eventRebelContact = $this->make_request('submitRegistrationXML', $contact);

        return $eventRebelContact;
    }
}
