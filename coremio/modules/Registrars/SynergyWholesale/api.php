<?php
// https://dev.wisecp.com/en/kb/__construct
// https://dev.wisecp.com/en/kb/create-a-query
// https://dev.wisecp.com/en/kb/cctld-gtld-whois-server
// https://synergywholesale.com/faq/article/api-whmcs-modules/

define('API_ENDPOINT', 'https://api.synergywholesale.com/');
define('FRONTEND', 'https://manage.synergywholesale.com');
define('WHOIS_URL', 'https://manage.synergywholesale.com/home/whmcs-whois-json');
// define('WHATS_MY_IP_URL', 'https://manage.synergywholesale.com/ip');
define('WHATS_MY_IP_URL', 'https://ip.seby.io');
define('SW_MODULE_NAME', 'synergywholesaledomains');
define('SW_MODULE_VERSION', '0');
define('MODULE_VERSION', '0');

function var_dump_str($var) {
    ob_start();
    var_dump($var);
    $result = ob_get_clean();
    return "<pre>" . $result . "</pre><br>";
}

function debug($obj) {
    $file = fopen(getcwd() . "/logfile.html", "w") or die("error writing to write to log");
    fwrite($file, var_dump_str($obj));
    fclose($file);
}

function strlen_check(array $matches, int $subpattern_num, int $i) {
    $num_matches = count($matches);
    if ($num_matches == 0 || $num_matches < $subpattern_num) {
        return 0;
    }
    $num_subpattern_matches = count($matches[$subpattern_num]);
    if ($num_subpattern_matches == 0 || $num_subpattern_matches < $i) {
        return 0;
    }
    return strlen($matches[$subpattern_num][$i]);
}

class SynergyWholesale_API
{

    private $test_mode      = false;
    private $resellerID     = NULL;
    private $apiKey         = NULL;
    public  $error          = NULL;
    private $curl           = false;
    private $_params        = [];

    function __construct($resellerID = '', $apiKey = '', $test_mode = false)
    {
        $this->test_mode    = $test_mode;
        $this->resellerID   = $resellerID;
        $this->apiKey       = $apiKey;
        $this->curl         = curl_init();
        curl_setopt($this->curl, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)');
        curl_setopt($this->curl, CURLOPT_ENCODING, "gzip");
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, 300);
        curl_setopt($this->curl, CURLOPT_HEADER, 0);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array("Content-Type: text/xml; charset=UTF-8"));
    }

    private function synergywholesaledomains_webRequest($url, $method = 'GET', array $params = [])
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_ENCODING, "gzip");
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        if ('POST' === $method) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $response = curl_exec($curl);

        if (0 !== curl_errno($curl)) {
            $info = curl_getinfo($curl);
            $this->error = 'Curl error: ' . $info[CURLINFO_RESPONSE_CODE] . ':' . curl_error($curl);
            return false;
        }

        curl_close($curl);
        return $response;
    }

    function helper_get_domain(array $params)
    {
        return $params['sld'] . '.' . $params['tld'];
    }


    // convert nameservers from flat names (params["nsX"]) to array
    function synergywholesaledomains_helper_getNameservers(array $params)
    {
        $nameservers = [];
        for ($i = 1; $i < 6; $i++) {
            if (empty($params["ns$i"])) {
                continue;
            }

            $nameservers[] = $params["ns$i"];
        }

        return $nameservers;
    }

    /**
     * Sends the API requests to the Synergy Wholesale API.
     *
     * @param string    $command        The Command to run send to the API
     * @param array     $params         The WHMCS parameters that come from the calling function
     * @param array     $request        The data that makes up the API request
     * @param bool      $throw_on_error Throw an exception if the API returns an error
     * @param bool      $force_domain   Insert the "domainName" element if it is not present
     *
     * @throws \Exception
     *
     * @return array
     */
    function synergywholesaledomains_api_request($command, array $params = [], array $request = [], $throw_on_error = false, $force_domain = false)
    {
        $auth = [
            'apiKey' => $this->apiKey,
            'resellerID' => $this->resellerID,
        ];

        if (isset($this->test_mode) && $this->test_mode === true) {
            $request['test_api_connection'] = 'on';
        }

        if (!isset($request['resellerID']) || !isset($request['apiKey'])) {
            $request = array_merge($request, $auth);
        }

        if (!isset($request['domainName']) && isset($params['sld']) && isset($params['tld'])) {
            $request['domainName'] = $params['sld'] . '.' . $params['tld'];
        } else if (!isset($request['domainName']) && isset($params['domainName'])) {
            $request['domainName'] = $params['domainName'];
        } else if (!isset($request['domainName']) && isset($params['domain'])) {
            $request['domainName'] = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);
        }

        $url        = API_ENDPOINT . '/?wsdl';
        $client     = new SoapClient($url, array("trace" => 1, "exception" => 0));


        try {
            $response = $client->{$command}($request);
        } catch (SoapFault $e) {
            if ($throw_on_error) {
                // Convert SOAP Faults to Exceptions
                throw new \Exception($e->getMessage());
            }

            $this->error = $e->getMessage();
            return false;
        }


        if (!preg_match('/^(OK|AVAILABLE).*?/', $response->status)) {
            if ($throw_on_error) {
                throw new \Exception(''.$response->errorMessage);
            }

            $this->error = $response->errorMessage;
            return false;
        }

        return get_object_vars($response);
    }

    public function login()
    {
        $response = $this->synergywholesaledomains_api_request('balanceQuery');
        if (!$response) {
            return false;
        }
        return $response['status'] == 'OK';
    }

    /**
     * Gets the contacts from the provided paramters
     *
     * @param      arrray  $params    The parameters
     * @param      array   $contacts  The requested contacts/contactMap
     *
     * @return     array   The contacts.
     */
    function helper_get_contacts(array $params, array $contacts = [])
    {
        $request = [];

        $contactTypeMap = [
            'registrant_' => '',
            'technical_' => 'admin',
            'admin_' => 'admin',
            'billing_' => 'admin',
        ];

        if (empty($contacts)) {
            $contacts = $contactTypeMap;
        }

        $contactMap = [
            'firstname' => 'FirstName',
            'lastname' => 'LastName',
            'address' => [
                'AddressLine1',
                'AddressLine2',
            ],
            'suburb' => 'City',
            'country' => 'Country',
            'state' => 'State',
            'postcode' => 'ZipCode',
            'phone' => 'Phone',
            'email' => 'EMail',
            'organisation' => 'Company',
        ];

        foreach ($contacts as $sw_contact => $whmcs_contact) {
            foreach ($contactMap as $destination => $source) {
                if (is_array($source)) {
                    $request[$sw_contact . $destination] = [];
                    foreach ($source as $key) {
                        $request[$sw_contact . $destination][] = $params['whois'][$whmcs_contact . $key];
                    }
                    continue;
                }

                if ('phone' === $destination) {
                    $phoneNumber = $this->format_phone_number(
                        $params['whois'][$whmcs_contact . $source],
                        $params['whois'][$whmcs_contact . 'Country'],
                        $params['whois'][$whmcs_contact . 'State'],
                        $params['whois'][$whmcs_contact . 'PhoneCountryCode']
                    );

                    $request[$sw_contact . 'phone'] = $phoneNumber;
                    $request[$sw_contact . 'fax'] = '';
                    continue;
                }

                if ('country' === $destination) {
                    if (!$this->validate_country($params['whois'][$whmcs_contact . $source])) {
                        $this->error =  'Country must be entered as 2 characters - ISO 3166 Standard. EG. AU';
                        return false;
                    }
                }

                if ('state' === $destination && 'AU' === $params['whois'][$whmcs_contact . 'Country']) {
                    $state = $this->validate_au_state($params['whois'][$whmcs_contact . 'State']);
                    if (!$state) {
                        $this->error = 'A Valid Australian State Name Must Be Supplied, EG. NSW, VIC';
                        return false;
                    }

                    $params['whois'][$whmcs_contact . $source] = $state;
                }

                $request[$sw_contact . $destination] = $params['whois'][$whmcs_contact . $source];
            }
        }
        return $request;
    }

    function get_details($params) {
        $request['domainName'] = $params['domainName'];
        return $this->synergywholesaledomains_api_request('domainInfo', $params, $request);
    }


    // list Child Nameservers of a domain
    function get_child_nameservers($params) {
        $request['domainName'] = $params['domainName'];
        return $this->synergywholesaledomains_api_request('listAllHosts', $params, $request);
    }

    // Add a Child Nameserver to a domain
    // Only support 1 IP per hostname
    function add_child_nameserver($params, $ns, $ip) {
        $request['domainName'] = $params['domainName'];
        $request['host'] = $ns;
        $request['ipAddress'][] = $ip;
        return $this->synergywholesaledomains_api_request('addHost', $params, $request);
    }

    // Delete a Child Nameserver from a domain
    function delete_child_nameserver($params, $ns) {
        $request['domainName'] = $params['domainName'];
        // Only remove the firs occurrence of the domain name to accurately get the sub domain.
        // reversing the string to make sure we are removing from the end.
        $count = 1;
        $subdomain = trim(strrev(str_replace(strrev($params['domainName']), "", strrev($ns), $count)), "\.");

        $request['host'] = $subdomain;
        return $this->synergywholesaledomains_api_request('deleteHost', $params, $request);
    }

    // Modify a Child Nameserver from a domain
    // Only support 1 IP per hostname
    function modify_child_nameserver($params, $old_ns, $new_ns, $new_ip) {
        $delete = $this->delete_child_nameserver($params, $old_ns);
        if(!$delete) {
            return false;
        }

        $addCNS = $this->add_child_nameserver($params, $new_ns, $new_ip);
        if(!$addCNS) {
            return false;
        }
        return true;
    }

    /**
     * Get the nameservers from the Synergy Wholesale API via the "domainInfo" command.
     *
     * @param array $params
     *
     * @return array
     */
    function get_nameservers(array $params, $include_dns_config = false)
    {
        $response = $this->synergywholesaledomains_api_request('domainInfo', $params, []);
        if (!$response) {
            return false;
        }

        $values = [];

        if ($include_dns_config) {
            $values['dnsConfigType'] = $response['dnsConfig'];
        }

        foreach ($response['nameServers'] as $index => $value) {
            $values['ns' . ($index + 1)] = strtolower($value);
        }


        return $values;
    }

    /**
     * Updates a domain names nameservers.
     *
     * @param array $params
     *
     * @return array|void
     */
    function save_nameservers(array $params)
    {
        // dnsConfig:
        // 1 Custom Name Servers
        // 2 Email/Web Forwarding
        // 3 Parked
        // 4 DNS Hosting
        // nsX.nameserver.net.au
        // synergywholesaledomains_helper_getNameservers
        $request = [
            'dnsConfig' => 1, // Custom Name Servers
            'nameServers' => $params['nameServers'],
        ];

        // TODO: Add hostname validation onto the provided nameservers.

        $response = $this->synergywholesaledomains_api_request('updateNameServers', $params, $request);
        if (!$response) {
            return false;
        }
        return $response;
    }

    /**
     * Returns the transfer lock status (if supported).
     *
     * @param array $params common module parameters
     *
     * @return string|array|null Lock status or error message, or nothing if not supported.
     */
    function get_registrar_lock(array $params)
    {
        if (!preg_match('/\.(au|uk)$/i', $params['tld'])) {
            try {
                $response = $this->$this->synergywholesaledomains_api_request('domainInfo', $params, [], true);
                $locked = 'clientTransferProhibited' === $response['domain_status'];
                return $locked ? 'locked' : 'unlocked';
            } catch (\Exception $e) {
                $this->error = $e->getMessage();
                return false;
            }
        }

        return null;
    }

    /**
     * Set registrar lock status.
     *
     * @param  $params common module parameters
     *
     * @return array|void
     */
    function save_registrar_lock(array $params, $command)
    {
        $locked = $this->get_registrar_lock($params);
        if ($locked === false) {
            return false;
        } else if (is_null($locked)) {
            $this->error = 'This domain name does not support registrar lock.';
            return false;
        }

        if ($locked == 'locked' && $command == 'unlockDomain' || $locked == 'unlocked' && $command == 'lockDomain') {
            return $this->$this->synergywholesaledomains_api_request($command, $params, [], false);
        } else {
            // no change needed
            return false;
        }
    }

    // /**
    //  * .UK domain push function.
    //  *
    //  * @param array $params
    //  *
    //  * @return array
    //  */
    // function synergywholesaledomains_ReleaseDomain(array $params)
    // {
    //     return $this->$this->synergywholesaledomains_api_request('domainReleaseUK', $params, [
    //         'tagName' => $params['transfertag'],
    //     ], false);
    // }

    /**
     * Domain name registration function.
     *
     * @param array $params
     *
     * @return array
     */
    function register_domain(array $params)
    {
        $request = [
            'nameServers' => $params['nameServers'],
            'years' => $params['years'],
            'idProtect' => $params['idProtect'],
            'specialConditionsAgree' => true,
        ];

        $contactTypeMap = [
            'registrant_' => '',
            'technical_' => '',
            'admin_' => '',
            'billing_' => '',
        ];

        $eligibility = [];
        $contacts = $this->helper_get_contacts($params, $contactTypeMap);
        if (!$contacts) {
            return false;
        }

        $request = array_merge($request, $contacts);

        if (preg_match('/\.?au$/', $params['tld'])) {
            $eligibility['registrantName'] = $params['additionalfields']['Registrant Name'];
            $eligibility['registrantID'] = $params['additionalfields']['Registrant ID'];

            if ('Business Registration Number' === $params['additionalfields']['Registrant ID Type']) {
                $params['additionalfields']['Registrant ID Type'] = 'OTHER';
            }

            $eligibility['registrantIDType'] = $params['additionalfields']['Registrant ID Type'];
            $eligibility['eligibilityType'] = $params['additionalfields']['Eligibility Type'];

            $brn = preg_match(
                '/(\w+) Business Number$|\((.{2,3})\)$|^Other/',
                $params['additionalfields']['Eligibility ID Type'],
                $matches
            );

            list(,, $brn) = $matches;
            if ($this->validate_au_state($brn)) {
                $brn .= ' BN';
            }

            $eligibility['eligibilityIDType'] = strtoupper($brn);
            $eligibility['eligibilityID'] = $params['additionalfields']['Eligibility ID'];
            $eligibility['eligibilityName'] = $params['additionalfields']['Eligibility Name'];
        }

        if (preg_match('/\.?uk$/', $params['tld'])) {
            $eligibility['tradingName'] = $params['additionalfields']['Registrant Name'];
            $eligibility['number'] = $params['additionalfields']['Registrant ID'];
            $eligibility['type'] = $params['additionalfields']['Registrant ID Type'];
            $eligibility['optout'] = $params['additionalfields']['WHOIS Opt-out'];
        }


        if (preg_match('/\.?us$/', $params['tld'])) {
            $eligibility['nexusCategory'] = $params['additionalfields']['Nexus Category'];
            if (!empty($params['additionalfields']['Nexus Country'])) {
                $eligibility['nexusCountry'] = $params['additionalfields']['Nexus Country'];
            }

            switch ($params['additionalfields']['Application Purpose']) {
                case 'Business use for profit':
                    $eligibility['appPurpose'] = 'P1';
                    break;
                case 'Non-profit business':
                case 'Club':
                case 'Association':
                case 'Religious Organization':
                    $eligibility['appPurpose'] = 'P2';
                    break;
                case 'Personal Use':
                    $eligibility['appPurpose'] = 'P3';
                    break;
                case 'Educational purposes':
                    $eligibility['appPurpose'] = 'P4';
                    break;
                case 'Government purposes':
                    $eligibility['appPurpose'] = 'P5';
                    break;
                default:
                    $eligibility['appPurpose'] = '';
                    break;
            }
        }

        if (!empty($eligibility)) {
            $request['eligibility'] = json_encode($eligibility);
        }

        // "premiumCost" is the price the API returned on "CheckAvailability"
        if (isset($params['premiumEnabled']) && $params['premiumEnabled'] && !empty($params['premiumCost'])) {
            $request['costPrice'] = $params['premiumCost'];
            $request['premium'] = true;
        }

        try {
            $this->synergywholesaledomains_api_request('domainRegister', $params, $request, true);
            // $returnData = [
            //     'status' => "SUCCESS",
            //     'config' => [
            //         'entityID' => 1,
            //     ],
            // ];
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * Transfer domain name functionality.
     *
     * @param array $params
     *
     * @return array
     */
    function transfer_domain(array $params)
    {
        // This is a lazy way of getting the contact data in the format we need.
        $contact = $this->helper_get_contacts($params, ['' => '']);
        if (!$contact) {
            return false;
        }

        if (preg_match('/\.uk$/', $params['tld'])) {
            $response = $this->$this->synergywholesaledomains_api_request('transferDomain', $params, $contact);
            if (!$response) {
                return false;
            }
        }

        $request = [
            'authInfo' => $params['authInfo'],
            'doRenewal' => $params['doRenewal'],
        ];

        if (preg_match('/\.au$/', $params['tld'])) {
            $canRenew = $this->synergywholesaledomains_api_request('domainRenewRequired', $params, $request);
            if (!$canRenew) {
                return false;
            }
            $request['doRenewal'] = (int) ('on' === $params['doRenewal'] && 'OK_RENEWAL' === $canRenew['status']);
        }

        /**
         * We don't want to send the idProtect flag with the "can renew"
         * check. So let's append it to the request here.
         */
        $request['idProtect'] = $params['idProtect'];

        // Merge contact data into request
        $request = array_merge($request, $contact);

        if (isset($params['premiumEnabled']) && $params['premiumEnabled'] && !empty($params['premiumCost'])) {
            $request['costPrice'] = $params['premiumCost'];
            $request['premium'] = true;
        }

        return $this->synergywholesaledomains_api_request('transferDomain', $params, $request);
    }

    /**
     * Enable or Disables ID Protection.
     *
     * @param array $params
     *
     * @return array
     */
    function id_protect_toggle(array $params)
    {
        $command = $params['protectenable'] ? 'enableIDProtection' : 'disableIDProtection';
        return $this->synergywholesaledomains_api_request($command, $params, [], false);
    }

    /**
     * Enable ID Protection.
     *
     * @param array $params
     *
     * @return array
     */
    function purchase_whois_privacy(array $params)
    {
        return $this->synergywholesaledomains_api_request('enableIDProtection', $params, [], false);
    }

    /**
     * Renew domain name function.
     *
     * @param array $params
     *
     * @return array
     */
    function renew_domain(array $params)
    {
        $request = [
            'years' => $params['regperiod'],
        ];

        if (isset($params['premiumEnabled']) && $params['premiumEnabled'] && !empty($params['premiumCost'])) {
            $request['costPrice'] = $params['premiumCost'];
            $request['premium'] = true;
        }

        return $this->synergywholesaledomains_api_request('renewDomain', $params, $request, false);
    }

    /**
     * Updates the contacts on a domain name.
     *
     * @param array $params
     *
     * @return array|void
     */
    function save_contact_details(array $params)
    {
        $request = [];
        // .US only
        // P1 Business for profit
        // P2 Nonprofit
        // P3 Personal
        // P4 Educational
        // P5 Governmental
        $request['appPurpose'] = $params['appPurpose'] || "";
        // .US only
        // C11 US Citizen
        // C12 Permanent Resident
        // C21 US Organisation
        // C31/AU Foreign organisation doing business in US
        // C32/AU Foreign organisation with US office
        $request['nexusCategory'] = $params['nexusCategory'] || "";
        // NZ only
        $request['nz_privacy'] = $params['nz_privacy'] || false;

        $contactTypes = [
            'registrant' => 'Registrant',
            'admin' => 'Admin',
            'technical' => 'Tech',
            'billing' => 'Billing',
        ];

        foreach ($contactTypes as $contactType => $whmcs_contact) {
            if (!isset($params['contactdetails'][$whmcs_contact])) {
                continue;
            }
            $request["{$contactType}_firstname"] = $params['contactdetails'][$whmcs_contact]['FirstName'];
            $request["{$contactType}_lastname"]  = $params['contactdetails'][$whmcs_contact]['LastName'];

            $request["{$contactType}_address"] = [
                $params['contactdetails'][$whmcs_contact]['AddressLine1'],
                $params['contactdetails'][$whmcs_contact]['AddressLine2'],
                // $params['contactdetails'][$whmcs_contact]['AddressLine3'],
            ];

            $request["{$contactType}_email"] = $params['contactdetails'][$whmcs_contact]['EMail'];
            $request["{$contactType}_suburb"] = $params['contactdetails'][$whmcs_contact]['City'];
            $request["{$contactType}_postcode"] = $params['contactdetails'][$whmcs_contact]['ZipCode'];

            // Validate the country being specified
            if (!$this->validate_country($params['contactdetails'][$whmcs_contact]['Country'])) {
                $this->error = "$whmcs_contact Country must be entered as 2 characters - ISO 3166 Standard. EG. AU";
                return false;
            }

            $request["{$contactType}_country"] = $params['contactdetails'][$whmcs_contact]['Country'];
            // See if country is AU
            if ('AU' == $request["{$contactType}_country"]) {
                // It is, so check to see if a valid AU State has been specified
                $state = $this->validate_au_state($params['contactdetails'][$whmcs_contact]['State']);
                if (!empty($params['contactdetails'][$whmcs_contact]['State']) && !$state) {
                    $this->error = 'A Valid Australian State Name Must Be Supplied, EG. NSW, VIC';
                    return false;
                }

                // Yes - store the state
                $request["{$contactType}_state"] = $state;
            } else {
                // Country is not Australia, so we can just use whatever has been supplied as we can't validate it
                $request["{$contactType}_state"] = $params['contactdetails'][$whmcs_contact]['State'];
            }

            $request["{$contactType}_phone"] = $this->format_phone_number(
                $params['contactdetails'][$whmcs_contact]['Phone'],
                $params['contactdetails'][$whmcs_contact]['Country'],
                $params['contactdetails'][$whmcs_contact]['State'],
                $params['contactdetails']['Registrant']['PhoneCountryCode']
            );

            $request["{$contactType}_fax"] = $this->format_phone_number(
                $params['contactdetails'][$whmcs_contact]['Fax'],
                $params['contactdetails'][$whmcs_contact]['Country'],
                $params['contactdetails'][$whmcs_contact]['State'],
                $params['contactdetails']['Registrant']['FaxCountryCode']
            );
        }

        try {
            $this->synergywholesaledomains_api_request('updateContact', $params, $request, true);
            return true;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * Get the contacts for a domain name. If ID Protect is enabled,
     * it'll still display the protected contact data.
     *
     * @param array $params
     *
     * @return array
     */
    function get_contact_details(array $params)
    {
        $idProtectStatus = $this->synergywholesaledomains_api_request('domainInfo', $params, [], false);
        $command = ('Enabled' === $idProtectStatus['idProtect'] ? 'listProtectedContacts' : 'listContacts');
        $contacts = $this->synergywholesaledomains_api_request($command, $params, [], false);
        $response = [];

        $map = [
            'firstname' => 'First Name',
            'lastname' => 'Last Name',
            'address1' => 'Address 1',
            'address2' => 'Address 2',
            'address3' => 'Address 3',
            'suburb' => 'City',
            'state' => 'State',
            'country' => 'Country',
            'postcode' => 'Postcode',
            'phone' => 'Phone',
            'email' => 'Email',
        ];

        $contactTypes = ['registrant'];
        foreach (['admin', 'billing', 'tech'] as $otherTypes) {
            if (isset($contacts[$otherTypes])) {
                $contactTypes[] = $otherTypes;
            }
        }

        foreach ($contactTypes as $contact) {
            $whmcs_contact = ucfirst($contact);
            $response[$whmcs_contact] = [];
            foreach ($map as $from => $to) {
                $response[$whmcs_contact][$to] = $contacts[$contact]->$from;
            }
        }

        return $response;
    }

    /**
     * Returns the EPP Code for the domain name.
     *
     * @param array $params
     *
     * @return array
     */
    function get_epp_code(array $params)
    {
        try {
            $eppCode = $this->synergywholesaledomains_api_request('domainInfo', $params, [], true);
            return [
                'eppcode' => $eppCode['domainPassword'],
            ];
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    // /**
    //  * Controller for the "Manage DNSSEC" page.
    //  *
    //  * @param array $params
    //  */
    // function synergywholesaledomains_manageDNSSEC(array $params)
    // {
    //     $errors = $vars = $values = [];

    //     if (isset($_REQUEST['sub'])) {
    //         switch ($_REQUEST['sub']) {
    //             case 'save':
    //                 try {
    //                     $save = $this->synergywholesaledomains_api_request('DNSSECAddDS', $params, [
    //                         'algorithm' => $_REQUEST['algorithm'],
    //                         'digestType' => $_REQUEST['digestType'],
    //                         'digest' => $_REQUEST['digest'],
    //                         'keyTag' => $_REQUEST['keyTag'],
    //                     ], true);

    //                     $vars['info'] = 'DNSSEC Record added successfully';
    //                 } catch (\Exception $e) {
    //                     $errors[] = $e->getMessage();
    //                 }
    //                 break;
    //             case 'delete':
    //                 try {
    //                     $delete = $this->synergywholesaledomains_api_request('DNSSECRemoveDS', $params, [
    //                         'UUID' => $_REQUEST['uuid'],
    //                     ], true);

    //                     $vars['info'] = 'DNSSEC Record deleted successfully';
    //                 } catch (\Exception $e) {
    //                     $errors[] = $e->getMessage();
    //                 }
    //                 break;
    //         }
    //     }

    //     // Get a current list of any dnssec records
    //     try {
    //         $vars['records'] = [];
    //         $response = $this->synergywholesaledomains_api_request('DNSSECListDS', $params);
    //         if (is_array($response['DSData'])) {
    //             foreach ($response['DSData'] as $record) {
    //                 $vars['records'][] = $record;
    //             }
    //         }
    //     } catch (\Exception $e) {
    //         $errors[] = $e->getMessage();
    //     }


    //     if (!empty($errors)) {
    //         $vars['error'] = implode('<br>', $errors);
    //     }

    //     $uri = 'clientarea.php?' . http_build_query([
    //         'action' => 'domaindetails',
    //         'domainid' => $params['domainid'],
    //         'modop' => 'custom',
    //         'a' => 'manageDNSSEC',
    //     ]);

    //     return [
    //         'templatefile' => 'domaindnssec',
    //         'breadcrumb'   => [
    //             $uri => 'Manage DNSSEC Records',
    //         ],
    //         'vars' => $vars,
    //     ];
    // }


    /**
     * @param  $phoneNumber
     * @param  $country
     * @param  $state
     * @return mixed
     */
    function format_phone_number($phoneNumber, $country, $state = '', $countryCode = null)
    {
        // If the phone number is empty, then simply return
        if (empty($phoneNumber)) {
            return $phoneNumber;
        }

        // Remove any white space from the number
        $phoneNumber = preg_replace('/ /', '', $phoneNumber);

        // Remove any dashes from the number
        $phoneNumber = preg_replace('/-/', '', $phoneNumber);

        // First let's see if this is valid international format
        if (preg_match('/\+61\.[2,3,4,7,8]{1}[0-9]{8}/', $phoneNumber)) {
            // Valid International (Australian) Format so simply return
            return $phoneNumber;
        }

        // Now let's see if this is a valid international phone number
        if (preg_match('/\+[0-9]{1,3}\.[0-9]*/', $phoneNumber)) {
            // Valid International (Non Australian) Format so simply return
            return $phoneNumber;
        }

        // See if we can match onto the format 61.404040404
        preg_match_all('/^61\.([2,3,4,7,8]{1})([0-9]{8})$/', $phoneNumber, $result, PREG_PATTERN_ORDER);
        if (strlen_check($result, 1, 0) > 0 && strlen_check($result, 2, 0) > 0) {
            // Create new phone number (formatted)
            $phoneNumber = '+61.' . $result[1][0] . $result[2][0];
        }

        // Now let'e see if this is a psuedo international phone number for Australia
        preg_match_all('/^61([2,3,4,7,8]{1})([0-9]{8})$|^\+61([2,3,4,7,8]{1})([0-9]{8})$/i', $phoneNumber, $result, PREG_PATTERN_ORDER);
        if (strlen_check($result, 1, 0) > 0 && strlen_check($result, 2, 0) > 0) {
            // Create new phone number (formatted)
            $phoneNumber = '+61.' . $result[1][0] . $result[2][0];
            // Return phone number
            return $phoneNumber;
        } elseif (strlen_check($result, 3, 0 > 0) && strlen_check($result, 4, 0) > 0) {
            // Create new phone number (formatted)
            $phoneNumber = '+61.' . $result[3][0] . $result[4][0];
            // Return phone number
            return $phoneNumber;
        }

        // If it doesn't match any of those, it might be in AU specific phone format
        preg_match_all('/^\(0([2,3,7,8])\)([0-9]{8})|^0([2,3,4,7,8])([0-9]{8})/', $phoneNumber, $result, PREG_PATTERN_ORDER);
        if (strlen_check($result, 1, 0) > 0 && strlen_check($result, 2, 0) > 0) {
            // Create the new formatted phone number
            $phoneNumber = '+61.' . $result[1][0] . $result[2][0];
            return $phoneNumber;
        } elseif (strlen_check($result, 3, 0) > 0 && strlen_check($result, 4, 0) > 0) {
            // Create the new formatted phone number
            $phoneNumber = '+61.' . $result[3][0] . $result[4][0];
            return $phoneNumber;
        }

        // Final check before we give up, see if the phone number is only 8 digits long
        if (preg_match('/^([0-9]{8})$/', $phoneNumber, $regs)) {
            $result = $regs[0];

            // If country is AU
            if ('AU' == strtoupper($country)) {
                // Strip any spaces from the state name and change to all UPPER case chars
                $state = preg_replace('/ /', '', $state);
                $state = strtoupper($state);

                // Switch statement on the state
                switch (strtoupper($state)) {
                    case 'VICTORIA':
                    case 'VIC':
                    case 'TASMANIA':
                    case 'TAS':
                        $areacode = '3';
                        break;

                    case 'QUEENSLAND':
                    case 'QLD':
                        $areacode = '7';
                        break;

                    case 'AUSTRALIANCAPITALTERRITORY':
                    case 'AUSTRALIACAPITALTERRITORY':
                    case 'ACT':
                    case 'NEWSOUTHWALES':
                    case 'NSW':
                        $areacode = '2';
                        break;

                    case 'SOUTHAUSTRALIA':
                    case 'SA':
                    case 'NORTHERNTERRITORY':
                    case 'NT':
                    case 'WESTERNAUSTRALIA':
                    case 'WA':
                        $areacode = '8';
                        break;
                }
            } else {
                // Not Australia, so simply return
                return $phoneNumber;
            }

            // Format the phone number
            $phoneNumber = '+61.' . $areacode . $result;

            // Return the formatted phone number
            return $phoneNumber;
        }

        // If we get here we have no idea what type of number has been supplied
        // Simply return it
        if (is_null($countryCode)) {
            return $phoneNumber;
        } else {
            // Country code can be inserted as integer because it should never exceed three digits.
            // Phone number could potentially be bigger than PHP_MAX_INT so let's insert it
            // into the string as a string to be ont he safe side.
            return sprintf('+%d.%s', $countryCode, $phoneNumber);
        }
    }

    /*
        This function will validate the supplied country code
         */

    /**
     * @param  $country
     * @return bool
     */
    function validate_country($country)
    {
        // Set a list of valid country codes
        $cc = 'AF,AX,AL,DZ,AS,AD,AO,AI,AQ,AG,AR,AM,AW,AU,AT,AZ,BS,BH,BD,BB,BY,BE,BZ,BJ,BM,BT,BO,BQ,BA,BW,BV,BR,IO,BN,BG,BF,BI,
                    KH,CM,CA,CV,KY,CF,TD,CL,CN,CX,CC,CO,KM,CG,CD,CK,CR,CI,HR,CU,CW,CY,CZ,DK,DJ,DM,DO,EC,EG,SV,GQ,ER,EE,ET,FK,FO,FJ,FI,FR,
                    GF,PF,TF,GA,GM,GE,DE,GH,GI,GR,GL,GD,GP,GU,GT,GG,GN,GW,GY,HT,HM,VA,HN,HK,HU,IS,IN,ID,IR,IQ,IE,IM,IL,IT,JM,JP,JE,JO,KZ,KE,
                    KI,KP,KR,KW,KG,LA,LV,LB,LS,LR,LY,LI,LT,LU,MO,MK,MG,MW,MY,MV,ML,MT,MH,MQ,MR,MU,YT,MX,FM,MD,MC,MN,ME,MS,MA,MZ,MM,NA,NR,NP,
                    NL,NC,NZ,NI,NE,NG,NU,NF,MP,NO,OM,PK,PW,PS,PA,PG,PY,PE,PH,PN,PL,PT,PR,QA,RE,RO,RU,RW,BL,SH,KN,LC,MF,PM,VC,WS,SM,ST,SA,SN,
                    RS,SC,SL,SG,SX,SK,SI,SB,SO,ZA,GS,SS,ES,LK,SD,SR,SJ,SZ,SE,CH,SY,TW,TJ,TZ,TH,TL,TG,TK,TO,TT,TN,TR,TM,TC,TV,UG,UA,AE,GB,US,
                    UM,UY,UZ,VU,VE,VN,VG,VI,WF,EH,YE,ZM,ZW';

        // Explode into an array
        $ccArray = explode(',', $cc);

        return in_array(strtoupper($country), $ccArray);
    }

    /*

        This function will return a valid au state name

         */

    /**
     * @param string $state
     * @return bool|string
     */
    function validate_au_state($state)
    {

        // Remove any spaces from the state
        $state = preg_replace('/\s|\./', '', $state);

        switch (strtoupper($state)) {
            case 'VICTORIA':
            case 'VIC':
                return 'VIC';

            case 'NEWSOUTHWALES':
            case 'NSW':
                return 'NSW';

            case 'QUEENSLAND':
            case 'QLD':
                return 'QLD';

            case 'AUSTRALIANCAPITALTERRITORY':
            case 'AUSTRALIACAPITALTERRITORY':
            case 'ACT':
                return 'ACT';

            case 'SOUTHAUSTRALIA':
            case 'SA':
                return 'SA';

            case 'WESTERNAUSTRALIA':
            case 'WA':
                return 'WA';

            case 'NORTHERNTERRITORY':
            case 'NT':
                return 'NT';

            case 'TASMANIA':
            case 'TAS':
                return 'TAS';

            default:
                return false;
        }
    }

    function cost_prices() {
        $response = $this->synergywholesaledomains_api_request('getDomainPricing');
        if ($response['status'] != 'OK') {
            $this->error = $response['errorMessage'];
        }
        return $response['pricing'];
    }

    // function synergywholesaledomains_sync_adhoc(array $params)
    // {
    //     try {
    //         $domainInfo = Capsule::table('tbldomains')
    //             ->where('id', $params['domainid'])
    //             ->first();
    //     } catch (\Exception $e) {
    //         $this->error = $e->getMessage();
    //         return false;
    //     }

    //     if ('Pending Transfer' === $domainInfo->status) {
    //         return $this->synergywholesaledomains_adhocTransferSync($params, $domainInfo);
    //     }

    //     return $this->synergywholesaledomains_adhocSync($params, $domainInfo);
    // }

    // /**
    //  * This function syncs domain transfers via "Sync" button in the admin panel.
    //  *
    //  * @param      array   $params      The parameters
    //  * @param      object  $domainInfo  The domain information
    //  *
    //  * @return     array   ( description_of_the_return_value )
    //  */
    // function synergywholesaledomains_adhocTransferSync(array $params, $domainInfo)
    // {
    //     global $_LANG, $CONFIG;

    //     $response = $this->synergywholesaledomains_TransferSync($params);
    //     $update = $syncMessages = [];
    //     if (isset($response['error'])) {
    //         return $response;
    //     }

    //     if ($response['failed'] && 'Cancelled' != $domainInfo->status) {
    //         $update['status'] = 'Cancelled';
    //         $errorMessage = (isset($response['reason']) ? $response['reason'] : $_LANG['domaintrffailreasonunavailable']);
    //     } elseif ($response['completed']) {
    //         $response = $this->synergywholesaledomains_Sync($params);
    //         if ($response['active'] && 'Active' != $domainInfo->status) {
    //             $update['status'] = 'Active';
    //             $syncMessages[] = sprintf('Status updated from %s to Active', $domainInfo->status);
    //             //sendMessage('Domain Transfer Completed', $domainInfo->id);
    //         }

    //         if ($response['expirydate']) {
    //             $newBillDate = $update['expirydate'] = $response['expirydate'];
    //             if ($CONFIG['DomainSyncNextDueDate'] && $CONFIG['DomainSyncNextDueDateDays']) {
    //                 $unix_expiry = strtotime($response['expirydate']);
    //                 $newBillDate = date('Y-m-d', strtotime(sprintf('-%d days', $CONFIG['DomainSyncNextDueDateDays']), $unix_expiry));
    //             }

    //             $update['nextinvoicedate'] = $update['nextduedate'] = $newBillDate;
    //         }
    //     }

    //     if (!empty($update)) {
    //         try {
    //             $update['synced'] = 1;

    //             Capsule::table('tbldomains')
    //                 ->where('id', $params['domainid'])
    //                 ->update($update);
    //         } catch (\Exception $e) {
    //             $this->error = 'Error updating domain; ' . $e->getMessage();
    //             return false;
    //         }
    //     }

    //     if (isset($errorMessage)) {
    //         $this->error = $errorMessage;
    //         return false;
    //     }

    //     global $domainstatus, $nextduedate, $expirydate;
    //     if (isset($update['status'])) {
    //         $domainstatus = $update['status'];
    //     }

    //     if (isset($update['nextduedate'])) {
    //         $nextduedate = str_replace('-', '/', $update['nextduedate']);
    //         $nextduedate = date('d/m/Y', strtotime($nextduedate));
    //     }

    //     if (isset($update['expirydate'])) {
    //         $expirydate = str_replace('-', '/', $update['expirydate']);
    //         $expirydate = date('d/m/Y', strtotime($expirydate));
    //     }

    //     $hookName = '';
    //     switch ($update['status']) {
    //         case 'Active':
    //             $hookName = 'DomainTransferCompleted';
    //             break;
    //         case 'Cancelled':
    //             $hookName = 'DomainTransferFailed';
    //             break;
    //     }

    //     if (!empty($hookName)) {
    //         run_hook(
    //             $hookName,
    //             [
    //                 'domainId' => $params['domainid'],
    //                 'domain' => $params['domainname'],
    //                 'expiryDate' => $update['expirydate'],
    //                 'registrar' => $params['registrar'],
    //             ]
    //         );
    //     }

    //     return [
    //         'message' => nl2br(
    //             empty($syncMessages) ?
    //                 'Domain Sync successful.' :
    //                 'Updated;\n    - ' . implode('\n    - ', $syncMessages)
    //         )
    //     ];
    // }

    // /**
    //  * This function syncs domain names via "Sync" button in the admin panel.
    //  *
    //  * Most of the stuff we are updating here is to actually update the interface. This is
    //  * because the interface has the data fetched prior to this function running.
    //  *
    //  * @param      array   $params      The parameters
    //  * @param      object  $domainInfo  The domain information from the DB
    //  *
    //  * @return     array   Returns a message containing the updated information.
    //  */
    // function synergywholesaledomains_adhocSync(array $params, $domainInfo)
    // {
    //     global $CONFIG;

    //     $response = $this->synergywholesaledomains_Sync($params);
    //     $syncMessages = $update = [];
    //     if (isset($response['error'])) {
    //         return $response;
    //     }

    //     if ($response['active'] && 'Active' != $domainInfo->status) {
    //         $update['status'] = 'Active';
    //     }

    //     if ($response['expired'] && 'Expired' != $domainInfo->status) {
    //         $update['status'] = 'Expired';
    //     }

    //     if ($response['cancelled'] && 'Active' == $domainInfo->status) {
    //         $update['status'] = 'Cancelled';
    //     }

    //     if (isset($response['transferredAway']) && $response['transferredAway'] && 'Transferred Away' != $domainInfo->status) {
    //         $update['status'] = 'Transferred Away';
    //     }

    //     if (isset($update['status'])) {
    //         $syncMessages[] = sprintf("Status from '%s' to '%s'", $domainInfo->status, $update['status']);
    //         $domainstatus = $update['status'];
    //     }

    //     if ($response['expirydate'] && $domainInfo->expirydate != $response['expirydate']) {
    //         $update['expirydate'] = $response['expirydate'];
    //         $diExpiryFormat = date('d/m/Y', strtotime($domainInfo->expirydate));
    //         $updateExpiryFormat = date('d/m/Y', strtotime($update['expirydate']));
    //         $syncMessages[] = sprintf("Expiry date from '%s' to '%s'", $diExpiryFormat, $updateExpiryFormat);
    //     }

    //     if ($response['expirydate']) {
    //         $newBillDate = $update['expirydate'] = $response['expirydate'];
    //         if ($CONFIG['DomainSyncNextDueDate'] && $CONFIG['DomainSyncNextDueDateDays']) {
    //             $unix_expiry = strtotime($response['expirydate']);
    //             $newBillDate = date('Y-m-d', strtotime(sprintf('-%d days', $CONFIG['DomainSyncNextDueDateDays']), $unix_expiry));
    //         }

    //         if ($newBillDate != $domainInfo->nextinvoicedate) {
    //             $update['nextinvoicedate'] = $update['nextduedate'] = $newBillDate;
    //             $diInvoiceDateFormat = date('d/m/Y', strtotime($domainInfo->nextinvoicedate));
    //             $updateBillDateFormat = date('d/m/Y', strtotime($newBillDate));
    //             $syncMessages[] = sprintf("Next Due Date from '%s' to '%s'", $diInvoiceDateFormat, $updateBillDateFormat);
    //         }
    //     }

    //     if (!empty($update)) {
    //         try {
    //             $update['synced'] = 1;

    //             Capsule::table('tbldomains')
    //                 ->where('id', $params['domainid'])
    //                 ->update($update);
    //         } catch (\Exception $e) {
    //             $this->error = 'Error updating domain; ' . $e->getMessage();
    //             return false;
    //         }
    //     }

    //     global $domainstatus, $nextduedate, $expirydate, $recurringamount, $isPremium, $idprotection;
    //     if (isset($update['status'])) {
    //         $domainstatus = $update['status'];
    //     }

    //     if (isset($update['nextduedate'])) {
    //         $nextduedate = fromMySQLDate($update['nextduedate']);
    //     }

    //     if (isset($update['expirydate'])) {
    //         $expirydate = fromMySQLDate($update['expirydate']);
    //     }

    //     $domain = Capsule::table('tbldomains')
    //         ->where('id', $params['domainid'])
    //         ->first();

    //     if ($isPremium != $domain->is_premium) {
    //         if ($domain->is_premium) {
    //             $syncMessages[] = 'Domain has been identified as premium.';
    //         } else {
    //             $syncMessages[] = 'Domain is no longer identified as premium.';
    //         }
    //     }

    //     $idprotection = $domain->idprotection;
    //     $recurringamount = $domain->recurringamount;
    //     $isPremium = $domain->is_premium;

    //     return [
    //         'message' => nl2br(
    //             empty($syncMessages) ?
    //                 'Domain Sync successful.' :
    //                 "Updated;\n    - " . implode("\n    - ", $syncMessages)
    //         )
    //     ];
    // }

    function get_domains($page=1, $pageSize=100) {
        // paginated request
        $request['page'] = $page;
        $request['limit'] = $pageSize;
        // $request['status']=('ok', 'clienthold', 'dropped','transferredaway', 'deleted', 'inactive', 'clientTransferProhibited','cilentUpdatedProhibited', 'pendingDelete', 'policyDelete', 'redemption', etc.)
        $response = $this->synergywholesaledomains_api_request('listDomains');
        // throw new Exception(var_dump_str($response));

        if ($response === false) {
            throw new Exception($this->error);
            return false;

        } if (in_array("errorMessage", $response)) {
            $this->error = $response['errorMessage'];
            throw new Exception($response['errorMessage']);
            return false;
        }
        return $response['domainList'];
    }




}
