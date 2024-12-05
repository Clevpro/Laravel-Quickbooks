<?php

namespace Clevpro\LaravelQuickbooks\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;

class QuickbooksInvoiceService
{
    protected $client;

    protected $accessToken;

    protected $realmId;

    public function __construct($accessToken, $realmId)
    {
        $this->client = new Client(
            [
                'base_uri' => config('quickbooks.sandbox') ? config('quickbooks.sandbox_base_url') : config('quickbooks.base_url'),
            ]
        ); // Initialize Guzzle HTTP Client
        $this->accessToken = $accessToken; // Access token for QuickBooks API
        $this->realmId = $realmId; // Company ID (realm ID)
    }

    public function createInvoice(array $invoiceData)
    {

        try {
            // Make the POST request to the QuickBooks API
            $response = $this->client->post("/v3/company/{$this->realmId}/invoice", [
                'headers' => [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'CustomerRef' => [
                        'value' => $invoiceData['customer_id'],
                    ],
                    'CustomerMemo' => [
                        'value' => $invoiceData['customer_memo'],
                    ],
                    'Line' => $invoiceData['line_items'],
                ],
            ]);

            $resp = json_decode((string) $response->getBody(), false);

            if (isset($resp->Invoice)) {
                return $resp->Invoice;
            } else {
                return null;
            }
        } catch (ClientException $e) {
            // Get the full response body from the Guzzle exception
            $responseBody = $e->getResponse()->getBody()->getContents();

            Log::error($responseBody);

            return [
                'error' => 'Client error',
                'details' => $responseBody, // Full error message here
            ];
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return [
                'error' => 'Something went wrong',
                'details' => $e->getMessage(),
            ];
        }
    }

    public function updateInvoice($invoiceId, array $invoiceData)
    {
        // Fetch the existing invoice to get its SyncToken
        $existingInvoice = $this->getInvoice($invoiceId);

        // Make the POST request to the QuickBooks API to update the invoice
        $response = $this->client->post("/v3/company/{$this->realmId}/invoice", [
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'Id' => $invoiceId,
                'SyncToken' => $existingInvoice['SyncToken'], // Must include the current SyncToken
                'Line' => $invoiceData['line_items'],
                'CustomerRef' => [
                    'value' => $invoiceData['customer_id'],
                ],
                'CustomerMemo' => [
                    'value' => $invoiceData['customer_memo'] ?? '',
                ],
                // Additional invoice updates can go here
            ],
        ]);

        return json_decode((string) $response->getBody(), true); // Return response as array
    }

    public function getInvoice($invoiceId)
    {
        // Make the GET request to retrieve the invoice from QuickBooks
        $response = $this->client->get("/v3/company/{$this->realmId}/invoice/{$invoiceId}", [
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'Accept' => 'application/json',
            ],
        ]);

        $resp = json_decode((string) $response->getBody(), false);

        if (isset($resp->Invoice)) {
            return $resp->Invoice;
        } else {
            return null;
        }
    }

    public function getInvoicePdf($invoiceId)
    {
        // Make the GET request to retrieve the invoice PDF from QuickBooks
        $response = $this->client->get("/v3/company/{$this->realmId}/invoice/{$invoiceId}/pdf", [
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}",
                'Accept' => 'application/pdf',
            ],
        ]);

        // Return the raw PDF data
        return $response->getBody();
    }

    public function getInvoiceViewLink($invoiceId)
    {
        if (config('quickbooks.sandbox')) {
            return "https://sandbox.qbo.intuit.com/app/invoice?txnId={$invoiceId}&companyId={$this->realmId}";
        } else {
            return  "https://qbo.intuit.com/app/invoice?txnId={$invoiceId}";
        }
    }
}
