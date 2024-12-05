<?php

namespace Clevpro\LaravelQuickbooks\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class QuickbooksOAuthService
{
    protected $client;

    protected $clientId;

    protected $clientSecret;

    protected $redirectUri;

    public function __construct()
    {
        $this->client = new Client; // Initialize Guzzle client
        $this->clientId = config('quickbooks.client_id');
        $this->clientSecret = config('quickbooks.client_secret');
        $this->redirectUri = config('quickbooks.redirect_uri');
    }

    public function connect()
    {
        $authUrl = $this->generateAuthUrl();

        return $authUrl;
    }

    public function disconnect($quickbooks_access_token = null, $quickbooks_refresh_token = null)
    {

        try {
            // Step 1: Revoke QuickBooks access token (Optional but recommended)
            if ($quickbooks_access_token) {
                $response = $this->client->post('https://developer.api.intuit.com/v2/oauth2/tokens/revoke', [
                    'auth' => [$this->clientId, $this->clientSecret],
                    'form_params' => [
                        'token' => $quickbooks_refresh_token,
                    ],
                ]);

                if ($response->getStatusCode() !== 200) {
                    return true;
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return false;
        }
    }

    /**
     * Handle the callback and exchange the authorization code for tokens.
     */
    public function getTokens(Request $request)
    {
        $authorizationCode = $request->input('code');
        $realmId = $request->input('realmId');

        // Extract tokens and return them or store them somewhere
        $tokens = $this->getAccessToken($authorizationCode);
        if (! $tokens) {
            return;
        }
        $accessToken = $tokens['access_token'];
        $refreshToken = $tokens['refresh_token'];
        $refreshToken = $tokens['refresh_token'];
        $expires_in = $tokens['expires_in'];
        //add 3600 seconds to the current time
        $expiration = Carbon::now()->addSeconds($expires_in);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'realm_id' => $realmId,
            'expires_at' => $expiration,
        ];
    }

    /**
     * Generate the QuickBooks OAuth URL to redirect the user to.
     */
    private function generateAuthUrl()
    {
        $queryParams = http_build_query([
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'scope' => 'com.intuit.quickbooks.accounting',
            'redirect_uri' => $this->redirectUri,
            'state' => 'random_state_string', // Generate a random state for security
        ]);

        return 'https://appcenter.intuit.com/connect/oauth2?'.$queryParams;
    }

    /**
     * Exchange the authorization code for an access token.
     */
    private function getAccessToken($authorizationCode)
    {
        $response = $this->client->post('https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer', [
            'auth' => [$this->clientId, $this->clientSecret],
            'form_params' => [
                'grant_type' => 'authorization_code',
                'code' => $authorizationCode,
                'redirect_uri' => $this->redirectUri,
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * Refresh the access token using the refresh token.
     */
    public function refreshAccessToken($refreshToken)
    {
        try {
            $response = $this->client->post('https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer', [
                'auth' => [$this->clientId, $this->clientSecret],
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ],
            ]);

            // Extract tokens and return them or store them somewhere
            $tokens = json_decode((string) $response->getBody(), true);
            if (! $tokens) {
                return;
            }
            $accessToken = $tokens['access_token'];
            $refreshToken = $tokens['refresh_token'];
            $expires_in = $tokens['expires_in'];
            //add 3600 seconds to the current time
            $expiration = Carbon::now()->addSeconds($expires_in);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 401 || $e->getResponse()->getStatusCode() === 400) {
                $accessToken = null;
                $refreshToken = null;
                $expires_in = 0;
                //add 3600 seconds to the current time
                $expiration = Carbon::now()->addSeconds($expires_in);
            } else {
                Log::error($e->getMessage());
            }
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiration,
        ];
    }
}
