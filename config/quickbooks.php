<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    'sandbox' => env('QUICKBOOKS_SANDBOX', false),
    'client_id' => env('QUICKBOOKS_CLIENT_ID'),
    'client_secret' => env('QUICKBOOKS_CLIENT_SECRET'),
    'redirect_uri' => env('QUICKBOOKS_REDIRECT_URI'),
    'scope' => 'com.intuit.quickbooks.accounting',
    'base_url' => 'https://quickbooks.api.intuit.com',
    'sandbox_base_url' => 'https://sandbox-quickbooks.api.intuit.com'
];
