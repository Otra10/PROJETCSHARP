<?php
return [
    'master_key' => env( 'PAYDUNYA_MASTER_KEY' ),
    'public_key' => env( 'PAYDUNYA_PUBLIC_KEY' ),
    'private_key' => env( 'PAYDUNYA_PRIVATE_KEY' ),
    'token' => env( 'PAYDUNYA_TOKEN' ),
    'mode' => env( 'PAYDUNYA_MODE' ),
    'store_name' => env( 'PAYDUNYA_STORE_NAME' ),
    'cancel_url' => env( 'PAYDUNYA_CANCEL_URL' ),
    'return_url' => env( 'PAYDUNYA_RETURN_URL' ),
    'callback_url' => env( 'PAYDUNYA_CALLBACK_URL' ),
];