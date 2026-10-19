<?php
declare(strict_types=1);

return [
    'public_key' => getenv('GESTAOPAY_PUBLIC_KEY') ?: base64_decode('Z2VzdGFvcGF5X2xpdmVfYmlNU09KT21Yd2p0NW1nYTZrQWJTUzdDT093bHZvbGs='),
    'secret_key' => getenv('GESTAOPAY_SECRET_KEY') ?: base64_decode('c2tfbGl2ZV91ZExodkZsQjh6djY3ZWpXZEk2dmd5c3dNZnJ2Z3NvUg=='),
    'api_url' => 'https://api.gestaopayments.com/v1/payment-transaction/create',
];
