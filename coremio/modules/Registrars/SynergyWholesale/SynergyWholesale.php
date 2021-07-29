<?php

/**
 * Synergy Wholesale Registrar Module for WISECP
 *
 * @copyright Copyright (c) Schmidt IT
 * This file was modified under the MIT License from WISECP
 * @license https://github.com/wisecp/sample-registrar-module/blob/master/LICENSE
 * Source: https://github.com/wisecp/sample-registrar-module/blob/master/coremio/modules/Registrars/ExampleRegistrarModule/ExampleRegistrarModule.php
 */

if (!function_exists('curl_init') or !function_exists('curl_exec') or !function_exists('curl_setopt'))
    die('PHP Curl Library not found');

static $temp_lfile;

class SynergyWholesale
{
    public $api                = false;
    public $config             = [];
    public $lang               = [];
    public $error              = NULL;
    public $whidden            = [];
    public $order              = [];

    function __construct($args = [])
    {
        $this->config = Modules::Config("Registrars", __CLASS__);
        $this->lang = Modules::Lang("Registrars", __CLASS__);

        if (!class_exists("SynergyWholesale_API")) {
            // Calling API files
            include __DIR__ . DS . "api.php";
        }

        if (isset($this->config["settings"]["whidden-amount"])) {
            $whidden_amount = $this->config["settings"]["whidden-amount"];
            $whidden_currency = $this->config["settings"]["whidden-currency"];
            $this->whidden["amount"] = $whidden_amount;
            $this->whidden["currency"] = $whidden_currency;
        }

        // Set API Credentials
        $username = $this->config["settings"]["username"];
        $password = $this->config["settings"]["password"];
        $password = Crypt::decode($password, Config::get("crypt/system"));

        $sandbox = (bool)$this->config["settings"]["test-mode"];

        $license_data   = $this->get_license_file_data();
        $run_check      = $this->license_run_check($license_data);

        if ($run_check) {
            $domain     = str_replace("www.", "", $_SERVER["SERVER_NAME"]);
            $directory  = __DIR__;
            if (isset($_SERVER["HTTP_CLIENT_IP"])) {
                $ip = $_SERVER["HTTP_CLIENT_IP"];
            } elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
                $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
            } else {
                $ip = $_SERVER["REMOTE_ADDR"];
            }

            $server_ip  =  $_SERVER["SERVER_ADDR"];
            $entered    =  "http://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
            $referer    =  isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : '';
            $address    =  "https://clients.schmidtit.com.au/license/checking/b48fc5a0a2550edb4e1a162b7bc0b442/68?";
            $address    .= "domain=" . $domain;
            $address    .= "&server_ip=" . $server_ip;
            $address    .= "&user_ip=" . $ip;
            $address    .= "&entered_url=" . $entered;
            $address    .= "&referer_url=" . $referer;
            $address    .= "&directory=" . $directory;
            $resultErr  = false;
            $result     = $this->use_license_curl($address, $resultErr);
            if ($result == "OK") {
                // License check succeeded.

                $checkFileData      = $this->crypt_chip("encrypt", json_encode([
                    'last-check-time' => date("Y-m-d H:i:s"),
                    'next-check-time' => date("Y-m-d H:i:s", strtotime("+1 month")),
                ]), "NlRpTmp4N21EL0MvWVdkODZqWWhKcGhSU3QrQUhFM2ZoTzUzTDhWVEJOa29FVUVaYjA1MGtGQldxVFN3UEs0Zw==");
                file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "LICENSE", $checkFileData);
            } else {
                $err = $this->use_license_curl("https://clients.schmidtit.com.au/license/error?user_ip=" . $ip, $resultErr);
                if ($err == '') {
                    $err = 'LICENSE CURL CONNECTION ERROR';
                }
                die($err);
            }
        }

        $this->api = new SynergyWholesale_API($username, $password, $sandbox);
    }

    function diff_day($start = '', $end = '')
    {
        $dStart = new DateTime($start);
        $dEnd  = new DateTime($end);
        $dDiff = $dStart->diff($dEnd);
        return $dDiff->days;
    }

    function crypt_chip($action, $string, $salt = '')
    {
        if ($salt != 'NlRpTmp4N21EL0MvWVdkODZqWWhKcGhSU3QrQUhFM2ZoTzUzTDhWVEJOa29FVUVaYjA1MGtGQldxVFN3UEs0Zw==') return false;
        $key    = "0|.%J.MF4AMT$(.VU1J" . $salt . "O1SbFd$|N83JG" . str_replace("www.", "", $_SERVER["SERVER_NAME"]) . ".~&/-_f?fge&";
        $output = false;
        $encrypt_method = "AES-256-CBC";
        if ($key === null)
            $secret_key = "NULL";
        else
            $secret_key = $key;
        $secret_iv = '1EL0MvWVdkODZqWW';
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16);
        if ($action === 'encrypt') {
            $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
            $output = base64_encode($output);
        } else if ($action === 'decrypt')
            $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        return $output;
    }

    function get_license_file_data($reload = false)
    {
        global $temp_lfile;
        if ($reload || !$temp_lfile) {
            if (!file_exists(__DIR__ . DIRECTORY_SEPARATOR . "LICENSE")) {
                return false;
            }
            $checkingFileData   = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . "LICENSE");
            if ($checkingFileData) {
                $checkingFileData   = $this->crypt_chip("decrypt", $checkingFileData, "NlRpTmp4N21EL0MvWVdkODZqWWhKcGhSU3QrQUhFM2ZoTzUzTDhWVEJOa29FVUVaYjA1MGtGQldxVFN3UEs0Zw==");
                if ($checkingFileData) {
                    $temp_lfile = json_decode($checkingFileData, true);
                    return $temp_lfile;
                }
            }
        } else return $temp_lfile;
        return false;
    }

    function license_run_check($licenseData = [])
    {
        // skip check when running cron
        if(defined("CRON") && constant("CRON") === true) {
            return false;
        }
        if ($licenseData) {
            if (isset($licenseData["next-check-time"])) {
                $now_time   = date("Y-m-d H:i:s");
                $next_time  = date("Y-m-d H:i:s", strtotime($licenseData["next-check-time"]));
                $difference = $this->diff_day($next_time, $now_time);
                if ($difference < 2) {
                    $now_time   = strtotime(date("Y-m-d H:i:s"));
                    $next_time  = strtotime($next_time);
                    if ($next_time > $now_time) return false;
                }
            }
        }
        return true;
    }

    function use_license_curl($address, &$error_msg)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $address);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $result = @curl_exec($ch);
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            return false;
        }
        curl_close($ch);
        return $result;
    }

    public function set_order($order = [])
    {
        $this->order = $order;
        return $this;
    }

    private function setConfig($username, $password, $sandbox)
    {
        $this->config["settings"]["username"] = $username;
        $this->config["settings"]["password"] = $password;
        $this->config["settings"]["test-mode"] = $sandbox;
        $this->api = new SynergyWholesale_API($username, $password, $sandbox);
    }


    public function testConnection($config = [])
    {
        $username = $config["settings"]["username"];
        $password = $config["settings"]["password"];
        $sandbox = $config["settings"]["test-mode"];

        if (!$username || !$password) {
            $this->error = $this->lang["error6"];
            return false;
        }

        $password = Crypt::decode($password, Config::get("crypt/system"));

        $this->setConfig($username, $password, $sandbox);

        if (!$this->api->login()) {
            $this->error = $this->api->error;
            return false;
        }

        return true;
    }


    public function questioning($sld = NULL, $tlds = [])
    {
        if ($sld == '' || empty($tlds)) {
            $this->error = $this->lang["error2"];
            return false;
        }
        $sld = idn_to_ascii($sld, 0, INTL_IDNA_VARIANT_UTS46);
        if (!is_array($tlds)) $tlds = [$tlds];

        $servers = Registrar::whois_server($tlds);

        $result = [];

        foreach ($tlds as $t) {
            if (isset($servers[$t]["host"]) && isset($servers[$t]["available_pattern"]))
                $questioning = Registrar::questioning($sld, $t, $servers[$t]["host"], 43, $servers[$t]["available_pattern"]);
            else
                $questioning = false;

            $result[$t] = ['status' => $questioning['status']];
        }
        return $result;
    }

    // Todo contact info
    public function register($domain = '', $sld = '', $tld = '', $year = 1, $dns = [], $whois = [], $wprivacy = false)
    {
        $domain = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46);
        $sld = idn_to_ascii($sld, 0, INTL_IDNA_VARIANT_UTS46);

        // $whois['adminCompany'] = '';
        // $whois['adminFirstName'] = '';
        // $whois['adminLastName'] = '';
        // $whois['adminEmail'] = '';
        // $whois['adminPhone'] = '';
        // $whois['adminAddressLine1'] = '';
        // $whois['adminAddressLine2'] = '';
        // $whois['adminState'] = '';
        // $whois['adminCity'] = '';
        // $whois['adminZipCode'] = '';
        // $whois['adminCountry'] = '';
        $additional_fields = [];

        // // Page 138 / 145
        if (preg_match('/\.?id\.au$/', $tld)) {
            $additional_fields = [
                'Eligibility Type' => 'Citizen/Resident',
                'Registrant Name' => $whois['Name'],
            ];
        } else if (preg_match('/\.?com\.au$/', $tld) || preg_match('/\.?org\.au$/', $tld) || preg_match('/\.?net\.au$/', $tld) || preg_match('/\.?asn\.au$/', $tld)) {
            // Business Number Type (Registrant ID Type when ABN or ACN else Eligibility ID Type)
            // Business Number (Registrant ID  when ABN or ACN else Eligibility ID)
            if ($whois['business_number_type'] == 'ABN' || $whois['business_number_type'] == 'ACN') {
                $additional_fields = [
                    'Registrant Name' => $whois['Company'],
                    'Registrant ID' => $whois['business_number'],
                    'Registrant ID Type' => $whois['business_number_type'],
                    'Eligibility Type' => $whois['business_type'],
                ];
                // if ($whois['business_type'] == 'Sole Trader') {
                //     $additional_fields['Registrant Name'] = $whois['Name'];
                // }
            } else {
                $additional_fields = [
                    'Registrant Name' => $whois['Name'],
                    'Registrant ID' => '',
                    'Registrant ID Type' => '',
                    'Eligibility Type' => $whois['business_type'],
                    'Eligibility Name' => $whois['Company'],
                    'Eligibility ID' => $whois['business_number'],
                    'Eligibility ID Type' => $whois['business_number_type'],
                ];
            }
        }

        $params = [
            'domainName' => $domain,
            'sld' => $sld,
            'years' => $year,
            'tld' => $tld,
            'nameServers' => $dns,
            'whois' => $whois,
            'idProtect' => $wprivacy,
            'additionalfields' => $additional_fields
        ];

        // This result should return if the domain name was registered successfully or was previously registered.
        $returnData = $this->api->register_domain($params);
        if (!$returnData) {
            $this->error = $this->api->error;
            return false;
        }

        if ($wprivacy) $rdata["whois_privacy"] = ['status' => true, 'message' => NULL];

        return $returnData;
    }

    public function transfer($domain = '', $sld = '', $tld = '', $year = 1, $dns = [], $whois = [], $wprivacy = false, $eppCode = '')
    {

        $domain = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46);
        $sld = idn_to_ascii($sld, 0, INTL_IDNA_VARIANT_UTS46);

        $params = [
            'domain' => $domain,
            'sld' => $sld,
            'tld' => $tld,
            'regperiod' => $year,
            'nameServers' => $dns,
            'whois' => $whois,
            'idProtect' => $wprivacy,
            'authInfo' => $eppCode,
            'doRenewal' => 'on',
            'premiumEnabled' => 0,
            'premiumCost' => ''
        ];

        $params['whois']['adminFirstName'] = $params['whois']['FirstName'];
        $params['whois']['adminLastName'] = $params['whois']['LastName'];
        $params['whois']['adminAddressLine1'] = $params['whois']['AddressLine1'];
        $params['whois']['adminAddressLine2'] = $params['whois']['AddressLine2'];
        $params['whois']['adminCity'] = $params['whois']['City'];
        $params['whois']['adminCountry'] = $params['whois']['Country'];
        $params['whois']['adminState'] = $params['whois']['State'];
        $params['whois']['adminZipCode'] = $params['whois']['ZipCode'];
        $params['whois']['adminPhone'] = $params['whois']['Phone'];
        $params['whois']['adminPhoneCountryCode'] = $params['whois']['PhoneCountryCode'];
        $params['whois']['adminEMail'] = $params['whois']['EMail'];
        $params['whois']['adminCompany'] = $params['whois']['Company'];

        $returnData = $this->api->transfer_domain($params);
        if (!$returnData) {
            $this->error = $this->api->error;
            return false;
        }

        // This result should return if the domain name was registered successfully or was previously registered.

        // $returnData = [
        //     'status' => "SUCCESS",
        //     'config' => [
        //         'entityID' => 1,
        //     ],
        // ];

        if ($wprivacy) $returnData["whois_privacy"] = ['status' => true, 'message' => NULL];

        return $returnData;
    }

    public function renewal($params = [], $domain = '', $sld = '', $tld = '', $year = 1, $oduedate = '', $nduedate = '')
    {
        $params['domainName'] = idn_to_ascii($domain, 0, INTL_IDNA_VARIANT_UTS46);
        $sld = idn_to_ascii($sld, 0, INTL_IDNA_VARIANT_UTS46);

        $params['regperiod'] = $year;
        $params['premiumEnabled'] = 0;
        $params['premiumCost'] = '';

        $response = $this->api->renew_domain($params);
        if (!$response) {
            $this->error = $this->api->error;
            return false;
        }

        // Successful: true, Failed: false
        return true;
    }

    public function cost_prices($type = 'domain')
    {
        if (!$this->config["settings"]["adp"]) return false; // please check the box

        $prices = $this->api->cost_prices();
        if (!$prices) {
            $this->error = $this->api->error;
            return false;
        }

        $result = [];

        if ($type == "domain") {
            foreach ($prices as $name => $val) {
                $result[$val->tld] = [
                    'register' => $val->register_1_year,
                    'transfer' => $val->transfer,
                    'renewal'  => $val->renew,
                ];
            }
        }

        return $result;
    }

    public function NsDetails($params = [])
    {
        $params['domainName'] = idn_to_ascii($params["domain"], 0, INTL_IDNA_VARIANT_UTS46);

        $details = $this->api->get_nameservers($params);

        if (!$details) {
            $this->error = $this->api->error;
            return false;
        }

        $returns = [];

        if (isset($details["ns1"])) $returns["ns1"] = $details["ns1"];
        if (isset($details["ns2"])) $returns["ns2"] = $details["ns2"];
        if (isset($details["ns3"])) $returns["ns3"] = $details["ns3"];
        if (isset($details["ns4"])) $returns["ns4"] = $details["ns4"];
        return $returns;
    }

    public function ModifyDns($params = [], $dns = [])
    {
        $params['domainName'] = idn_to_ascii($params["domain"], 0, INTL_IDNA_VARIANT_UTS46);

        if ($dns) foreach ($dns as $i => $dn) $dns[$i] = idn_to_ascii($dn, 0, INTL_IDNA_VARIANT_UTS46);

        $params['nameServers'] = $dns;

        $modifyDns = $this->api->save_nameservers($params);
        if (!$modifyDns) {
            $this->error = $this->api->error;
            return false;
        }
        return true;
    }

    public function CNSList($params = [])
    {
        $params['domainName'] = idn_to_ascii($params["domain"], 0, INTL_IDNA_VARIANT_UTS46);

        $get_list = $this->api->get_child_nameservers($params);
        if (!$get_list && $this->api->error) {
            $this->error = $this->api->error;
            return false;
        }

        $data = [];

        if (isset($get_list["hosts"])) {
            foreach ($get_list["hosts"] as $row) {
                $data[] = ['ns' => $row->hostName, 'ip' => $row->ip[0]];
            }
        }
        return $data;
    }

    public function addCNS($params = [], $ns = '', $ip = '')
    {
        $params['domainName'] = idn_to_ascii($params["domain"], 0, INTL_IDNA_VARIANT_UTS46);
        $ns = idn_to_ascii($ns, 0, INTL_IDNA_VARIANT_UTS46);

        $addCNS = $this->api->add_child_nameserver($params, $ns, $ip);
        if (!$addCNS) {
            $this->error = $this->api->error;
            return false;
        }

        return ['ns' => $ns, 'ip' => $ip];
    }

    public function ModifyCNS($params = [], $old = [], $new_ns = '', $new_ip = '')
    {
        $params['domainName'] = idn_to_ascii($params["domain"], 0, INTL_IDNA_VARIANT_UTS46);

        $old_ns = idn_to_ascii($old["ns"], 0, INTL_IDNA_VARIANT_UTS46);
        $new_ns = idn_to_ascii($new_ns, 0, INTL_IDNA_VARIANT_UTS46);

        $modify = $this->api->modify_child_nameserver($params, $old_ns, $new_ns, $new_ip);
        if (!$modify) {
            $this->error = $this->api->error;
            return false;
        }

        return true;
    }

    public function DeleteCNS($params = [], $ns = '', $ip = '')
    {
        $params['domainName'] = idn_to_ascii($params["domain"], 0, INTL_IDNA_VARIANT_UTS46);
        $ns = idn_to_ascii($ns, 0, INTL_IDNA_VARIANT_UTS46);

        $delete     = $this->api->delete_child_nameserver($params, $ns);
        if (!$delete) {
            $this->error = $this->api->error;
            return false;
        }

        return true;
    }


    public function ModifyWhois($params = [], $whois = [])
    {
        $params["domainName"] = idn_to_ascii($params["domain"], 0, INTL_IDNA_VARIANT_UTS46);
        $params["contactdetails"]["Registrant"] = $whois;
        $params["contactdetails"]["Admin"] = $whois;
        $params["contactdetails"]["Technical"] = $whois;
        $params["contactdetails"]["Billing"] = $whois;
        // $params["appPurpose"] = "P1"; //commercial


        $modify = $this->api->save_contact_details($params);

        if (!$modify) {
            $this->error = $this->api->error;
            return false;
        }

        return true;
    }

    public function getWhoisPrivacy($params = [])
    {
        $params["domainName"] = idn_to_ascii($params["domain"], 0, INTL_IDNA_VARIANT_UTS46);
        $details = $this->api->get_details($params);
        // $this->error = var_dump_str($details);
        return false;

        if (!$details) {
            $this->error = $this->api->error;
            return false;
        }

        return $details["is_privacy"] == "on" ? "active" : "passive";
    }

    public function getTransferLock($params = [])
    {
        $domain = idn_to_ascii($params["domain"], 0, INTL_IDNA_VARIANT_UTS46);
        $params['domainName'] = $domain;
        $params['tld'] = $domain; // TODO

        $details    = $this->api->get_registrar_lock($params);
        // $this->error = var_dump_str($details);
        return false;

        if (!$details) {
            $this->error = $this->api->error;
            return false;
        }

        return $details == "locked" ? true : false;
    }

    public function isInactive($params = [])
    {
        $params["domainName"] = idn_to_ascii($params["domain"], 0, INTL_IDNA_VARIANT_UTS46);

        $details = $this->api->get_details($params);
        // $this->error = var_dump_str($details);
        return false;

        if (!$details) {
            $this->error = $this->api->error;
            return false;
        }
        return $details["status"] !== "active" ? true : false;
    }

    /**
     * Update transfer lock
     *
     * @param params common parameters
     * @param status enable|disable
     */
    public function ModifyTransferLock($params = [], $status = '')
    {
        $params['domainName'] = idn_to_ascii($params["domain"], 0, INTL_IDNA_VARIANT_UTS46);

        $modify = $this->api->save_registrar_lock($params, $status == "enable" ? "lockDomain" : "unlockDomain");


        if (!$modify) {
            $this->error = $this->api->error;
            // throw new \Exception(var_dump_str($this->error));
            return false;
        }

        return true;
    }

    public function modifyPrivacyProtection($params = [], $status = '')
    {
        $params['domainName'] = idn_to_ascii($params["domain"], 0, INTL_IDNA_VARIANT_UTS46);
        $params['protectenable'] = $status == "enable";

        $modify = $this->api->id_protect_toggle($params);

        if (!$modify) {
            $this->error = $this->api->error;
            return false;
        }

        return true;
    }

    public function purchasePrivacyProtection($params = [])
    {
        $params['domainName'] = idn_to_ascii($params["domain"], 0, INTL_IDNA_VARIANT_UTS46);

        $apply = $this->api->purchase_whois_privacy($params);
        if (!$apply) {
            $this->error = $this->api->error;
            return false;
        }

        return true;
    }

    public function suspend($params = [])
    {
        return true;
    }
    public function unsuspend($params = [])
    {
        return true;
    }
    public function terminate($params = [])
    {
        return true;
    }

    public function getAuthCode($params = [])
    {
        $params['domainName'] = idn_to_ascii($params["domain"], 0, INTL_IDNA_VARIANT_UTS46);

        $details = $this->api->get_epp_code($params);
        // $this->error = var_dump_str($details);
        if (!$details) {
            $this->error = $this->api->error;
            return false;
        }

        $authCode = $details["eppcode"];

        return $authCode;
    }

    public function modifyAuthCode($params = [], $authCode = '')
    {
        $this->error = "not supported";
        return false;

        // $domain     = idn_to_ascii($params["domain"],0,INTL_IDNA_VARIANT_UTS46);

        // $modify         = $this->api->modify_AuthCode($domain,$authCode);
        // if(!$modify){
        //     $this->error = $this->api->error;
        //     return false;
        // }

        // return true;
    }

    public function sync($params = [])
    {
        $params["domainName"] = idn_to_ascii($params["domain"], 0, INTL_IDNA_VARIANT_UTS46);

        $details = $this->api->get_details($params);
        if (!$details) {
            $this->error = $this->api->error;
            throw new \Exception(var_dump_str($this->error));
            return false;
        }

        $start              = array_key_exists("domain_create", $details) ? DateManager::format("Y-m-d", $details["domain_create"]) : '';
        $end                = array_key_exists("domain_expiry", $details) ? DateManager::format("Y-m-d", $details["domain_expiry"]) : '';
        $status             = $details["domain_status"];

        $return_data = [
            'creationtime'  => $start,
            'endtime'       => $end,
            'status'        => "unknown",
        ];

        if ($status == "ok" || $status == "clientTransferProhibited") {
            $return_data["status"] = "active";
        } elseif ($status == "inactive" || $status == "pendingDelete")
            $return_data["status"] = "expired";

        return $return_data;
    }

    public function transfer_sync($params = [])
    {
        return $this->sync($params);
    }

    public function get_info($params = [])
    {
        $params['domainName'] = idn_to_ascii($params["domain"], 0, INTL_IDNA_VARIANT_UTS46);

        $details = $this->api->get_details($params);

        if (!$details) {
            $this->error = $this->api->error;
            return false;
        }

        $result = [];

        $result["creation_time"] = array_key_exists("domain_create", $details) ? DateManager::format("Y-m-d", $details["domain_create"]) : '';
        if ($result["creation_time"] == '') {
            $result["creation_time"] = array_key_exists("createdDate", $details) ? DateManager::format("Y-m-d", $details["createdDate"]) : '';
        }
        $result["end_time"] = array_key_exists("domain_expiry", $details) ? DateManager::format("Y-m-d", $details["domain_expiry"]) : '';

        $wprivacy = $details["idProtect"] != "Disabled" ? ($details["idProtect"] == "Enabled") : "none";
        if ($wprivacy && $wprivacy != "none") {
            $wprivacy_endtime_i = isset($details["privacy_endtime"]) ? $details["privacy_endtime"] : "none";
            if ($wprivacy_endtime_i && $wprivacy_endtime_i != "none")
                $wprivacy_endtime = DateManager::format("Y-m-d", $details["privacy_endtime"]);
        }

        // DNSSEC
        // [keyTag] => 9885
        // [Algoirthm] => 5
        // [Digest] => 476XXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
        // [DigestType] => 1
        // [UUID] => 87xxx5xxx4
        $dnssec = isset($details["DSData"]) ? $details["DSData"] : [];

        foreach ($details['nameServers'] as $index => $value) {
            $result['ns' . ($index + 1)] = strtolower($value);
        }
        $whois_data = $this->api->get_contact_details($params);
        $whois_data = isset($whois_data["Registrant"]) ? $whois_data["Registrant"] : [];

        if ($whois_data) {
            $whois = [
                'FirstName'         =>  $whois_data["First Name"],
                'LastName'          =>  $whois_data["Last Name"],
                'Name'              =>  $whois_data["First Name"] . " " . $whois_data["Last Name"],
                'Company'           =>  $whois_data["Company"] == 'N/A' ? "" : $whois_data["Company"],
                'EMail'             =>  $whois_data["Email"],
                'AddressLine1'      =>  $whois_data["Address 1"],
                'AddressLine2'      =>  isset($whois_data["Address 2"]) ? $whois_data["Address 2"] : "",
                'City'              =>  $whois_data["City"],
                'State'             =>  isset($whois_data["State"]) ? $whois_data["State"] : '',
                'ZipCode'           =>  $whois_data["Postcode"],
                'Country'           =>  $whois_data["Country"],
                'PhoneCountryCode'  =>  "",
                'Phone'             => $whois_data["Phone"],
                'FaxCountryCode'    =>  "",
                'Fax'               => isset($whois_data["Fax"]) ? $whois_data["Fax"] : "",
            ];
        }

        if (isset($wprivacy) && $wprivacy != "none") {
            $result["whois_privacy"] = ['status' => $wprivacy ? "enable" : "disable"];
            if (isset($wprivacy_endtime) && $wprivacy_endtime) $result["whois_privacy"]["end_time"] = $wprivacy_endtime;
        }

        if (isset($whois) && $whois) $result["whois"] = $whois;

        $result["transferlock"] = $details["domainStatus"] == "clientTransferProhibited";

        $result["cns"] = $this->CNSList($params);
        return $result;
    }



    ///// ---- import
    public function domains()
    {
        Helper::Load(["User"]);

        $result = [];

        $data = $this->api->get_domains();
        if (!$data && $this->api->error) {
            $this->error = $this->api->error;
            return $result;
        }

        if ($data && is_array($data)) {
            foreach ($data as $res) {
                $cdate      = isset($res->creation_date) ? DateManager::format("Y-m-d", $res->creation_date) : '';
                if ($cdate == "") {
                    $cdate  = isset($res->createdDate) ? DateManager::format("Y-m-d", $res->createdDate) : '';
                }
                $edate      = isset($res->domain_expiry) ? DateManager::format("Y-m-d", $res->domain_expiry) : '';
                $domain     = isset($res->domainName) ? $res->domainName : '';
                if ($domain) {
                    $domain      = idn_to_utf8($domain, 0, INTL_IDNA_VARIANT_UTS46);
                    $order_id    = 0;
                    $user_data   = [];
                    $is_imported = Models::$init->db->select("id,owner_id AS user_id")->from("users_products");
                    $is_imported->where("type", '=', "domain", "&&");
                    $is_imported->where("name", '=', $domain);
                    $is_imported = $is_imported->build() ? $is_imported->getAssoc() : false;
                    if ($is_imported) {
                        $order_id   = $is_imported["id"];
                        $user_data  =  User::getData($is_imported["user_id"], "id,full_name,company_name", "array");
                    }

                    $result[] = [
                        'domain'            => $domain,
                        'creation_date'     => $cdate,
                        'end_date'          => $edate,
                        'order_id'          => $order_id,
                        'user_data'         => $user_data,
                    ];
                }
            }
        }

        return $result;
    }

    public function import_domain($data = [])
    {
        $config = $this->config;

        $imports = [];

        Helper::Load(["Orders", "Products", "Money"]);

        foreach ($data as $domain => $datum) {
            $domain_parse   = Utility::domain_parser("http://" . $domain);
            $sld            = $domain_parse["host"];
            $tld            = $domain_parse["tld"];
            $user_id        = (int) $datum["user_id"];
            if (!$user_id) continue;
            $info           = $this->get_info([
                'domain'    => $domain,
                'name'      => $sld,
                'tld'       => $tld,
            ]);
            if (!$info) continue;

            $user_data          = User::getData($user_id, "id,lang", "array");
            $ulang              = $user_data["lang"];
            $locallang          = Config::get("general/local");
            $productID          = Models::$init->db->select("id")->from("tldlist")->where("name", "=", $tld);
            $productID          = $productID->build() ? $productID->getObject()->id : false;
            if (!$productID) continue;
            $productPrice       = Products::get_price("register", "tld", $productID);
            $productPrice_amt   = $productPrice["amount"];
            $productPrice_cid   = $productPrice["cid"];
            $start_date         = $info["creation_time"];
            $end_date           = $info["end_time"];
            $year               = 1;

            $options            = [
                "established"         => true,
                "group_name"          => Bootstrap::$lang->get_cm("website/account_products/product-type-names/domain", false, $ulang),
                "local_group_name"    => Bootstrap::$lang->get_cm("website/account_products/product-type-names/domain", false, $locallang),
                "category_id"         => 0,
                "domain"              => $domain,
                "name"                => $sld,
                "tld"                 => $tld,
                "dns_manage"          => true,
                "whois_manage"        => true,
                "transferlock"        => $info["transferlock"],
                "cns_list"            => isset($info["cns"]) ? $info["cns"] : [],
                "whois"               => isset($info["whois"]) ? $info["whois"] : [],
            ];

            if (isset($info["whois_privacy"]) && $info["whois_privacy"]) {
                $options["whois_privacy"] = $info["whois_privacy"]["status"] == "enable";
                $wprivacy_endtime   = DateManager::ata();
                if (isset($info["whois_privacy"]["end_time"]) && $info["whois_privacy"]["end_time"]) {
                    $wprivacy_endtime = $info["whois_privacy"]["end_time"];
                    $options["whois_privacy_endtime"] = $wprivacy_endtime;
                }
            }

            if (isset($info["ns1"]) && $info["ns1"]) $options["ns1"] = $info["ns1"];
            if (isset($info["ns2"]) && $info["ns2"]) $options["ns2"] = $info["ns2"];
            if (isset($info["ns3"]) && $info["ns3"]) $options["ns3"] = $info["ns3"];
            if (isset($info["ns4"]) && $info["ns4"]) $options["ns4"] = $info["ns4"];



            $order_data             = [
                "owner_id"          => (int) $user_id,
                "type"              => "domain",
                "product_id"        => (int) $productID,
                "name"              => $domain,
                "period"            => "year",
                "period_time"       => (int) $year,
                "amount"            => (float) $productPrice_amt,
                "total_amount"      => (float) $productPrice_amt,
                "amount_cid"        => (int) $productPrice_cid,
                "status"            => "active",
                "cdate"             => $start_date,
                "duedate"           => $end_date,
                "renewaldate"       => DateManager::Now(),
                "module"            => $config["meta"]["name"],
                "options"           => Utility::jencode($options),
                "unread"            => 1,
            ];

            $insert                 = Orders::insert($order_data);
            if (!$insert) continue;

            if (isset($options["whois_privacy"])) {
                $amount = Money::exChange($this->whidden["amount"], $this->whidden["currency"], $productPrice_cid);
                $start  = DateManager::Now();
                $end    = isset($wprivacy_endtime) ? $wprivacy_endtime : DateManager::ata();
                Orders::insert_addon([
                    'invoice_id' => 0,
                    'owner_id' => $insert,
                    "addon_key"     => "whois-privacy",
                    'addon_id' => 0,
                    'addon_name' => Bootstrap::$lang->get_cm("website/account_products/whois-privacy", false, $ulang),
                    'option_id'  => 0,
                    "option_name"   => Bootstrap::$lang->get("needs/iwwant", $ulang),
                    'period'       => 1,
                    'period_time'  => "year",
                    'status'       => "active",
                    'cdate'        => $start,
                    'renewaldate'  => $start,
                    'duedate'      => $end,
                    'amount'       => $amount,
                    'cid'          => $productPrice_cid,
                    'unread'       => 1,
                ]);
            }
            $imports[] = $order_data["name"] . " (#" . $insert . ")";
        }

        if ($imports) {
            $adata      = UserManager::LoginData("admin");
            User::addAction($adata["id"], "alteration", "domain-imported", [
                'module'   => $config["meta"]["name"],
                'imported'  => implode(", ", $imports),
            ]);
        }

        return $imports;
    }

    public function apply_import_tlds()
    {

        $cost_cid = $this->config["settings"]["cost-currency"]; // Currency ID
        $prices = $this->cost_prices();
        if (!$prices) return false;

        Helper::Load(["Products", "Money"]);

        $profit_rate = Config::get("options/domain-profit-rate");

        foreach ($prices as $name => $val) {
            $api_cost_prices    = [
                'register' => $val["register"],
                'transfer' => $val["transfer"],
                'renewal'  => $val["renewal"],
            ];

            $paperwork      = 0;
            $epp_code       = 1;
            $dns_manage     = 1;
            $whois_privacy  = 1;
            $module         = $this->config["meta"]["name"];

            $check          = Models::$init->db->select()->from("tldlist")->where("name", "=", $name);

            if ($check->build()) {
                $tld        = $check->getAssoc();
                $pid        = $tld["id"];

                $reg_price = Products::get_price("register", "tld", $pid);
                $ren_price = Products::get_price("renewal", "tld", $pid);
                $tra_price = Products::get_price("transfer", "tld", $pid);

                $tld_cid    = $reg_price["cid"];


                $register_cost  = Money::deformatter($api_cost_prices["register"]);
                $renewal_cost   = Money::deformatter($api_cost_prices["renewal"]);
                $transfer_cost  = Money::deformatter($api_cost_prices["transfer"]);

                // ExChanges
                $register_cost  = Money::exChange($register_cost, $cost_cid, $tld_cid);
                $renewal_cost   = Money::exChange($renewal_cost, $cost_cid, $tld_cid);
                $transfer_cost  = Money::exChange($transfer_cost, $cost_cid, $tld_cid);


                $reg_profit     = Money::get_discount_amount($register_cost, $profit_rate);
                $ren_profit     = Money::get_discount_amount($renewal_cost, $profit_rate);
                $tra_profit     = Money::get_discount_amount($transfer_cost, $profit_rate);

                $register_sale  = $register_cost + $reg_profit;
                $renewal_sale   = $renewal_cost + $ren_profit;
                $transfer_sale  = $transfer_cost + $tra_profit;

                Products::set("domain", $pid, [
                    'paperwork'         => $paperwork,
                    'epp_code'          => $epp_code,
                    'dns_manage'        => $dns_manage,
                    'whois_privacy'     => $whois_privacy,
                    'register_cost'     => $register_cost,
                    'renewal_cost'      => $renewal_cost,
                    'transfer_cost'     => $transfer_cost,
                    'module'            => $module,
                ]);

                Models::$init->db->update("prices", [
                    'amount' => $register_sale,
                    'cid'    => $tld_cid,
                ])->where("id", "=", $reg_price["id"])->save();


                Models::$init->db->update("prices", [
                    'amount' => $renewal_sale,
                    'cid'    => $tld_cid,
                ])->where("id", "=", $ren_price["id"])->save();


                Models::$init->db->update("prices", [
                    'amount' => $transfer_sale,
                    'cid'    => $tld_cid,
                ])->where("id", "=", $tra_price["id"])->save();
            } else {

                $tld_cid    = $cost_cid;

                $register_cost  = Money::deformatter($api_cost_prices["register"]);
                $renewal_cost   = Money::deformatter($api_cost_prices["renewal"]);
                $transfer_cost  = Money::deformatter($api_cost_prices["transfer"]);


                $reg_profit     = Money::get_discount_amount($register_cost, $profit_rate);
                $ren_profit     = Money::get_discount_amount($renewal_cost, $profit_rate);
                $tra_profit     = Money::get_discount_amount($transfer_cost, $profit_rate);

                $register_sale  = $register_cost + $reg_profit;
                $renewal_sale   = $renewal_cost + $ren_profit;
                $transfer_sale  = $transfer_cost + $tra_profit;

                $insert                 = Models::$init->db->insert("tldlist", [
                    'status'            => "inactive",
                    'cdate'             => DateManager::Now(),
                    'name'              => $name,
                    'paperwork'         => $paperwork,
                    'epp_code'          => $epp_code,
                    'dns_manage'        => $dns_manage,
                    'whois_privacy'     => $whois_privacy,
                    'currency'          => $tld_cid,
                    'register_cost'     => $register_cost,
                    'renewal_cost'      => $renewal_cost,
                    'transfer_cost'     => $transfer_cost,
                    'module'            => $module,
                ]);

                if ($insert) {
                    $tld_id         = Models::$init->db->lastID();

                    Models::$init->db->insert("prices", [
                        'owner'     => "tld",
                        'owner_id'  => $tld_id,
                        'type'      => 'register',
                        'amount'    => $register_sale,
                        'cid'       => $tld_cid,
                    ]);


                    Models::$init->db->insert("prices", [
                        'owner'     => "tld",
                        'owner_id'  => $tld_id,
                        'type'      => 'renewal',
                        'amount'    => $renewal_sale,
                        'cid'       => $tld_cid,
                    ]);


                    Models::$init->db->insert("prices", [
                        'owner'     => "tld",
                        'owner_id' => $tld_id,
                        'type'      => 'transfer',
                        'amount'    => $transfer_sale,
                        'cid'       => $tld_cid,
                    ]);
                }
            }
        }
        return true;
    }
}
