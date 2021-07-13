<?php
ini_set("display_errors", "On");
error_reporting(E_ALL | E_STRICT);

// command line support
if (isset($argv[1])) {
    $_GET['domain'] = $argv[1];
}

$_GET           = filter_var_array($_GET, ['domain'=>FILTER_SANITIZE_STRING]);
$hostname       = "domaincheck.auda.org.au";
$port           = 43;
$full_domain    = strtolower(trim($_GET['domain'] ?? 'auda.org.au'));
$timeout        = 12;

// https://www.php.net/manual/en/function.fsockopen.php
$query = fsockopen($hostname, $port, $error_code, $error_message, $timeout);

if ($error_code != 0) {
    echo 'Unknown';
    echo $error_message;
    return;
}

if (!$query) {
    echo 'Unknown';
    echo "Error: problem initializing the socket";
    return;
}

$data = "";
fputs($query, $full_domain . "\r\n");
socket_set_timeout($query, $timeout);
while (!@feof($query)) {
    $data .= @fread($query, 4096);
}
fclose($query);

if (!!stristr($data, 'Not Available')) {
    echo 'Registered';
    return;
} else if (!!stristr($data, 'NOT SUPPORTED')) {
    echo 'NOT SUPPORTED TLD';
    return;
} else if (!!stristr($response, "Available")) {
    echo "Not Found";
    return;
}
echo "Unknown Error";
