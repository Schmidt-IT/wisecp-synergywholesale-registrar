<?php
return [
    'name'              => 'SynergyWholesale',
    'description'       => '',
    'import-tld-button' => 'Import',
    'fields'            => [
        'username'      => 'Reseller ID',
        'password'      => 'API Key',
        'test-mode'     => 'Test Mode',
        'WHiddenAmount' => 'Whois Protection Fee',
        'adp'           => 'Auto Update Of Costs',
        'cost-currency' => 'Cost Currency',
        'import-tld'    => 'Import Extensions',
    ],
    'desc'              => [
        'username'      => '',
        'password'      => '',
        'WHiddenAmount' => '<br>Ask for a fee for whois protection service.',
        'test-mode'     => 'Use API in test mode.',
        'adp'           => 'It automatically pulls costs every day and defines the sales prices for the profit rate you specify.',
        'cost-currency' => '',
        'import-tld-1'  => 'Automatically import all extensions registered on the API.',
        'import-tld-2'  => 'All domain extensions and costs registered on the API will be imported collectively.',
    ],
    'tab-detail'        => 'API Information',
    'tab-import'        => 'Import',
    'test-button'       => 'Test Connection',
    'import-note'       => 'You can easily transfer the domain names found on the service provider to your existing clients. When you do this, the domain name will be defined as an order for your client. The domain names already registered in the system are coloured green.',
    'import-button'     => 'Import',
    'save-button'       => 'Save settings',
    'error1'            => 'The API information is not available.',
    'error2'            => 'Domain and extension information did not come.',
    'error3'            => 'An error occurred while retrieving the contact ID.',
    'error4'            => 'Failed to get status information.',
    'error5'            => 'The transfer information could not be retrieved.',
    'error6'            => 'Please enter the API information.',
    'error7'            => 'The import operation failed',
    'error8'            => 'An error has occurred.',
    'success1'          => 'Settings saved successfully.',
    'success2'          => 'The connection test succeeded.',
    'success3'          => 'Import completed successfully.',
    'success4'          => 'Extensions were successfully imported.',
];
