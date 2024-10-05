<?php
namespace Clevpro\LaravelQuickbooks\Services;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class QuickbooksOAuthService
{
    protected $client;
    protected $clientId;
    protected $clientSecret;
    protected $redirectUri;

    public function __construct()
    {
        $this->client = new Client(); // Initialize Guzzle client
        $this->clientId = config('quickbooks.client_id');
        $this->clientSecret = config('quickbooks.client_secret');
        $this->redirectUri = config('quickbooks.redirect_uri');
    }

    public function connect()
    {
        $authUrl = $this->generateAuthUrl();
        return $authUrl;
    }


    /**
     *
     * Handle the callback and exchange the authorization code for tokens.
     *
     * @param Request $request
     *
     */

    public function getTokens(Request $request)
    {
        $authorizationCode = $request->input('code');
        $realmId = $request->input('realmId');

        // Extract tokens and return them or store them somewhere
        $tokens = $this->getAccessToken($authorizationCode);
        if(!$tokens){
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
            'expires_at' => $expiration
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

        return 'https://appcenter.intuit.com/connect/oauth2?' . $queryParams;
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
            ]
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * Refresh the access token using the refresh token.
     */
    public function refreshAccessToken($refreshToken)
    {
        $response = $this->client->post('https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer', [
            'auth' => [$this->clientId, $this->clientSecret],
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]
        ]);

          // Extract tokens and return them or store them somewhere
          $tokens = json_decode((string) $response->getBody(), true);
          if(!$tokens){
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
              'expires_at' => $expiration
          ];
    }
}
