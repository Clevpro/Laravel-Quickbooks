<?php
namespace App\Http\Controllers;

use App\Models\Store;
use Clevpro\LaravelQuickbooks\Services\QuickbooksOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuickbooksWebhookController extends Controller
{
    public function handle(Request $request)
    {
  
        Log::info('QuickBooks Webhook Request: ' . json_encode($request->all()));
        try {
            // Verify the webhook request (Intuit provides a way to verify payloads)
            $this->verifyQuickbooksWebhook($request);

            // Process the webhook event
            $webhookEvent = $request->input('eventNotifications', []);

            foreach ($webhookEvent as $event) {
                Log::info('QuickBooks Webhook Event: ' . json_encode($event));
                foreach ($event['dataChangeEvent']['entities'] as $entity) {
                    // Handle different events, e.g., disconnects or token invalidation
                    if ($entity['operation'] === 'Delete' && $entity['name'] === 'Authorization') {
                        // User disconnected QuickBooks from their panel
                        $this->handleDisconnection($entity);
                    }
                }
            }

            return response()->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            Log::error('QuickBooks Webhook Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    protected function verifyQuickbooksWebhook(Request $request)
    {
        $webhook_verify_token = config('quickbooks.webhook_verify_token');
        $intuitSignature = $request->header('intuit-signature');

        if (!$intuitSignature) {
            Log::warning('Missing Intuit-Signature header in webhook request.');
            abort(400, 'Missing Intuit-Signature header.');
        }

        // Retrieve the raw request body
        $payload = $request->getContent();

        // Generate the HMAC hash using the client secret
        $generatedHash = base64_encode(hash_hmac('sha256', $payload, $webhook_verify_token, true));

        // Compare the generated hash with the Intuit-Signature
        if (!hash_equals($generatedHash, $intuitSignature)) {
            Log::warning('Intuit-Signature verification failed.');
            abort(400, 'Webhook verification failed.');
        }

        Log::info('Intuit-Signature verification passed.');
    }

    protected function handleDisconnection($entity)
    {
        // Find the store by realm ID
        $store = Store::where('quickbooks_realm_id', $entity['realmId'])->first();

        if ($store) {
            $store->quickbooks_access_token = null;
            $store->quickbooks_refresh_token = null;
            $store->quickbooks_token_expires_at = null;
            $store->quickbooks_realm_id = null;
            $store->save();

            Log::info('User-initiated disconnection handled for realm ID: ' . $entity['realmId']);
        }
    }
}
