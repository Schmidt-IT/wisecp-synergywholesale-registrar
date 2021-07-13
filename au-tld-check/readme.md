# au. domain check

https://www.auda.org.au/au-domain-names/about-au-domain-names

WISECP config file: `/coremio/storage/whois-servers.php`

## Official Whois for .au (rate limited)

```json
    'asn.au,com.au,id.au,net.au,org.au,edu.au,gov.au,csiro.au,act.au,nsw.au,nt.au,qld.au,sa.au,tas.au,vic.au,wa.au' => [
        'host'              => 'whois.auda.org.au',
        'available_pattern' => 'NOT FOUND',
    ],
```

## SynergyWholesale domain checker (IP whitelist)

Supported TLDs: https://manage.synergywholesale.com/home/whmcs-whois-json

```json
    'asn.au,com.au,id.au,net.au,org.au' => [
        'host' => 'https://{MYDOMAIN}/au-tld-check/synergywholesale.php?domain={domain}',
        'available_pattern' => 'Not Found',
    ],
```

## Afilias domain checker

Online Version: https://afilias.com.au/get-au/availability-check/


```json
    'asn.au,com.au,id.au,net.au,org.au' => [
        'host' => 'https://{MYDOMAIN}/au-tld-check/afilias.php?domain={domain}',
        'available_pattern' => 'Not Found',
    ],
```
