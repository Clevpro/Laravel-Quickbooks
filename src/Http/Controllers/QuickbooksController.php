<?php

namespace Clevpro\LaravelQuickbooks\Http\Controllers;

use QuickBooksOnline\API\DataService\DataService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class QuickbooksController extends Controller
{
    public function connect()
    {

        // Redirect to QuickBooks OAuth URL
        $dataService = DataService::Configure([
            'auth_mode' => 'oauth2',
            'ClientID' => config('quickbooks.client_id'),
            'ClientSecret' => config('quickbooks.client_secret'),
            'RedirectURI' => config('quickbooks.redirect_uri'),
            'scope' => config('quickbooks.scope'),
            'baseUrl' => config('quickbooks.base_url')
        ]);


        $OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
        $authUrl = $OAuth2LoginHelper->getAuthorizationCodeURL();
        return redirect($authUrl);
    }

    public function callback(Request $request)
    {
        // Handle callback and save tokens
        $dataService = DataService::Configure([
            'auth_mode' => 'oauth2',
            'ClientID' => config('quickbooks.client_id'),
            'ClientSecret' => config('quickbooks.client_secret'),
            'RedirectURI' => config('quickbooks.redirect_uri'),
            'scope' => config('quickbooks.scope'),
            'baseUrl' => config('quickbooks.base_url')
        ]);

        $OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
        // $accessToken = $OAuth2LoginHelper->exchangeAuthorizationCodeForToken($request->code, $request->realmId);



        // Store token details in DB
        // e.g., store $accessToken->getAccessToken() and refresh token
    }
}
