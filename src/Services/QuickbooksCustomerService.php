<?php

namespace Clevpro\LaravelQuickbooks\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;

class QuickbooksCustomerService
{
    protected $client;

    protected $accessToken;

    protected $realmId;

    public function __construct($accessToken, $realmId)
    {
        $this->client = new Client([
            'base_uri' => config('quickbooks.sandbox') ? config('quickbooks.sandbox_base_url') : config('quickbooks.base_url'),
        ]); // Initialize Guzzle client
        $this->accessToken = $accessToken;
        $this->realmId = $realmId;
    }

    /**
     * Create a new customer in QuickBooks.
     *
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
                'FullyQualifiedName' => $customerData['full_name'],
                'PrimaryEmailAddr' => [
                    'Address' => $customerData['email'],
                ],
                'DisplayName' => $customerData['display_name'],
                'PrintOnCheckName' => $customerData['print_on_check_name'],
                'PrimaryPhone' => [
                    'FreeFormNumber' => $customerData['phone'],
                ],
                'CompanyName' => $customerData['company_name'] ?? '',
                'BillAddr' => [
                    'Line1' => $customerData['address_line1'],
                    'City' => $customerData['city'],
                    'CountrySubDivisionCode' => $customerData['state'],
                    'PostalCode' => $customerData['postal_code'],
                    'Country' => $customerData['country'],
                ],
            ],
        ]);

        $customerResp = json_decode((string) $response->getBody(), false);

        if (isset($customerResp->Customer)) {
            return $customerResp->Customer;
        } else {
            return null;
        }
    }

    /**
     * Update an existing customer in QuickBooks.
     *
     * @param  string  $customerId
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
                ],
            ]);
            $customerResp = json_decode((string) $response->getBody(), false);

            if (isset($customerResp->Customer)) {
                $syncToken = $customerResp->Customer->SyncToken;
            } else {
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
                    'Id' => $customerId,
                    'SyncToken' => $syncToken,
                    'FullyQualifiedName' => $customerData['full_name'],
                    'PrimaryEmailAddr' => [
                        'Address' => $customerData['email'],
                    ],
                    // TODO: work out how to update display name and avoid name conflicts (if needed).
                    // "DisplayName" => $customerData['display_name'],
                    'PrintOnCheckName' => $customerData['print_on_check_name'],
                    'PrimaryPhone' => [
                        'FreeFormNumber' => $customerData['phone'],
                    ],
                    'CompanyName' => $customerData['company_name'] ?? '',
                    'BillAddr' => [
                        'Line1' => $customerData['address_line1'],
                        'City' => $customerData['city'],
                        'CountrySubDivisionCode' => $customerData['state'],
                        'PostalCode' => $customerData['postal_code'],
                        'Country' => $customerData['country'],
                    ],
                ],
            ]);

            $customerResp = json_decode((string) $response->getBody(), false);
            if (isset($customerResp->Customer)) {
                return $customerResp->Customer;
            } else {
                return null;
            }
        } catch (ClientException $e) {
            // Get the full response body from the Guzzle exception
            $responseBody = $e->getResponse()->getBody()->getContents();

            Log::error($responseBody);

            return $responseBody;
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return $e->getMessage();
        }
    }

    public function searchCustomer(array $customerData)
    {
        $display_name = addslashes($customerData['display_name']);
        $company_name = addslashes($customerData['company_name']);
        $email = addslashes($customerData['email']);
        $firstName = addslashes($customerData['first_name']);
        $lastName = addslashes($customerData['last_name']);

        $batchRequests = [
            [
                'bId' => 'bid_display_name',
                'Query' => "SELECT * FROM Customer WHERE DisplayName LIKE '%$display_name%'",
            ],
        ];

        if ($company_name) {
            $batchRequests[] = [
                'bId' => 'bid_company_name',
                'Query' => "SELECT * FROM Customer WHERE CompanyName LIKE '%$company_name%'",
            ];
        }

        if ($email) {
            $batchRequests[] = [
                'bId' => 'bid_email',
                'Query' => "SELECT * FROM Customer WHERE PrimaryEmailAddr = '$email'",
            ];
        }

        if ($firstName && $lastName) {
            $batchRequests[] = [
                'bId' => 'bid_name',
                'Query' => "SELECT * FROM Customer WHERE GivenName LIKE '%$firstName%' AND FamilyName LIKE '%$lastName%'",
            ];
        } elseif ($firstName) {
            $batchRequests[] = [
                'bId' => 'bid_name',
                'Query' => "SELECT * FROM Customer WHERE GivenName LIKE '%$firstName%'",
            ];
        } elseif ($lastName) {
            $batchRequests[] = [
                'bId' => 'bid_name',
                'Query' => "SELECT * FROM Customer WHERE FamilyName LIKE '%$lastName%'",
            ];
        }

        $accessToken = $this->accessToken;
        $realmId = $this->realmId;
        try {
            $response = $this->client->post("/v3/company/$realmId/batch", [
                'headers' => [
                    'Authorization' => "Bearer $accessToken",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'BatchItemRequest' => $batchRequests,
                ],
            ]);

            $customerResp = json_decode((string) $response->getBody(), true);

            if (isset($customerResp['BatchItemResponse'])) {
                $data = $customerResp['BatchItemResponse'];
                $customers = [];
                foreach ($data as $batch) {
                    if (isset($batch['QueryResponse']) && isset($batch['QueryResponse']['Customer'])) {
                        $customers = array_merge($customers, $batch['QueryResponse']['Customer']);
                    } elseif (isset($batch['Fault'])) {
                        Log::error($batch);
                    }
                }

                return [
                    'success' => true,
                    'data' => collect($customers)->unique('Id')->toArray(),
                    'code' => $response->getStatusCode(),
                ];
            } else {
                return [
                    'success' => true,
                    'data' => null,
                    'code' => $response->getStatusCode(),
                ];
            }
        } catch (ClientException $e) {
            // Get the full response body from the Guzzle exception
            $responseBody = $e->getResponse()->getBody()->getContents();

            Log::error($responseBody);

            return [
                'success' => false,
                'data' => $responseBody,
                'code' => $e->getCode(),
            ];
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return [
                'success' => false,
                'data' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        }
    }
}
