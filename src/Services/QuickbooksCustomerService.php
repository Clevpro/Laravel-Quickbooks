<?php

namespace Clevpro\LaravelQuickbooks\Services;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class QuickbooksCustomerService
{
    protected $client;
    protected $accessToken;
    protected $realmId;

    public function __construct($accessToken,  $realmId)
    {
        $this->client = new Client([
            'base_uri' => config('quickbooks.sandbox') ? config('quickbooks.sandbox_base_url') : config('quickbooks.base_url'),
        ]); // Initialize Guzzle client
        $this->accessToken = $accessToken;
        $this->realmId = $realmId;
    }

    public function connect()
    {
        $OAuth2LoginHelper = $this->dataService->getOAuth2LoginHelper();
        $authUrl = $OAuth2LoginHelper->getAuthorizationCodeURL();
        return $authUrl; // Return the OAuth URL so the user can redirect
    }

    /**
     * Create a new customer in QuickBooks.
     *
     * @param array $customerData
     * @return object
     */
    public function createCustomer(array $customerData)
    {
        $accessToken = $this->accessToken;
        $realmId = $this->realmId;

        $response = $this->client->post("/v3/company/$realmId/customer", [
            'headers' => [
                'Authorization' => "Bearer $accessToken",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                "FullyQualifiedName" => $customerData['full_name'],
                "PrimaryEmailAddr" => [
                    "Address" => $customerData['email']
                ],
                "DisplayName" => $customerData['display_name'],
                "PrimaryPhone" => [
                    "FreeFormNumber" => $customerData['phone']
                ],
                "BillAddr" => [
                    "Line1" => $customerData['address_line1'],
                    "City" => $customerData['city'],
                    "CountrySubDivisionCode" => $customerData['state'],
                    "PostalCode" => $customerData['postal_code'],
                    "Country" => $customerData['country'],
                ]
            ]
        ]);

        $customerResp = json_decode((string) $response->getBody(), false);

        if(isset($customerResp->Customer)) {
            return $customerResp->Customer;
        }else{
            return null;
        }
    }


    /**
     * Update an existing customer in QuickBooks.
     *
     * @param string $customerId
     * @param array $customerData
     * @return object
     */
    public function updateCustomer($customerId, array $customerData)
    {
        try {
            //get the $syncToken
            $response = $this->client->get("/v3/company/{$this->realmId}/customer/$customerId", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
                ]);
            $customerResp = json_decode((string) $response->getBody(), false);

            if(isset($customerResp->Customer)) {
                $syncToken = $customerResp->Customer->SyncToken;
            }else{
                $syncToken = null;
            }
            // Make the POST request to the QuickBooks API
            $response = $this->client->post("/v3/company/{$this->realmId}/customer", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    "Id" => $customerId,
                    "SyncToken" => $syncToken,
                    "FullyQualifiedName" => $customerData['full_name'],
                    "PrimaryEmailAddr" => [
                        "Address" => $customerData['email']
                    ],
                    "DisplayName" => $customerData['display_name'],
                    "PrimaryPhone" => [
                        "FreeFormNumber" => $customerData['phone']
                    ],
                    "BillAddr" => [
                        "Line1" => $customerData['address_line1'],
                        "City" => $customerData['city'],
                        "CountrySubDivisionCode" => $customerData['state'],
                        "PostalCode" => $customerData['postal_code'],
                        "Country" => $customerData['country'],
                    ]
                ]
            ]);

            $customerResp = json_decode((string) $response->getBody(), false);
            if(isset($customerResp->Customer)) {
                return $customerResp->Customer;
            }else{
                return null;
            }
        } catch (ClientException $e) {
            // Get the full response body from the Guzzle exception
            $responseBody = $e->getResponse()->getBody()->getContents();
            return $responseBody;
        } catch (\Exception $e) {
            return  $e->getMessage();
        }
    }

}
