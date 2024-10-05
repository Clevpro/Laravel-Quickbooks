# Laravel QuickBooks Integration

## Introduction

**Laravel QuickBooks Integration** is a package that provides an easy and flexible way to integrate **QuickBooks Online** with Laravel. It allows you to manage customers, create invoices, and interact with the QuickBooks Online API directly.

## Features
- OAuth 2.0 authentication for QuickBooks Online.
- Create and update invoices.
- Create and update customers.
- Fetch invoice PDFs.
- Built using **QuickBooks Online REST API** directly, no SDK required.

## Installation

### 1. Install via Composer

First, add the package to your project using Composer:

```bash
composer require clevpro/laravel-quickbooks
```

### 2. Publish the Configuration

After installing the package, publish the configuration file:

```bash
php artisan vendor:publish --provider="Clevpro\LaravelQuickbooks\QuickbooksServiceProvider" --tag=config
```

This will create a `config/quickbooks.php` file where you can define your QuickBooks API credentials.

### 3. Configure Environment Variables

Add the following QuickBooks API credentials to your `.env` file:

```env
QUICKBOOKS_CLIENT_ID=your-client-id
QUICKBOOKS_CLIENT_SECRET=your-client-secret
QUICKBOOKS_REDIRECT_URI=https://yourdomain.com/quickbooks/callback
```

## Usage

### Authentication and OAuth Flow

You will need to authenticate with QuickBooks to get the access token. Redirect users to the QuickBooks OAuth page and handle the callback.

#### Step 1: Redirect to QuickBooks OAuth

To initiate the connection to QuickBooks, call the following service method:

```php
use Clevpro\LaravelQuickbooks\Services\QuickbooksOAuthService;

$quickbooksOAuthService = new QuickbooksOAuthService();
$authUrl = $quickbooksOAuthService->generateAuthUrl();
return redirect($authUrl);
```

#### Step 2: Handle the OAuth Callback

Handle the callback to exchange the authorization code for access tokens:

```php
use Clevpro\LaravelQuickbooks\Services\QuickbooksOAuthService;
use Illuminate\Http\Request;

public function callback(Request $request)
{
    $quickbooksOAuthService = new QuickbooksOAuthService();
    $accessTokenData = $quickbooksOAuthService->getAccessToken($request->input('code'));

    // Store the access token, refresh token, and realm ID in the database
    // Example:
    $user = Auth::user();
    $user->quickbooks_access_token = $accessTokenData['access_token'];
    $user->quickbooks_refresh_token = $accessTokenData['refresh_token'];
    $user->quickbooks_realm_id = $accessTokenData['realm_id'];
    $user->save();

    return redirect()->route('dashboard')->with('success', 'QuickBooks connected successfully!');
}
```

### Creating an Invoice

You can create an invoice by calling the `createInvoice()` method from the `QuickbooksInvoiceService`:

```php
use Clevpro\LaravelQuickbooks\Services\QuickbooksInvoiceService;

$quickbooksInvoiceService = new QuickbooksInvoiceService($user->quickbooks_access_token, $user->quickbooks_realm_id);

$lineItems = [
    [
        "Amount" => 100,
        "DetailType" => "SalesItemLineDetail",
        "SalesItemLineDetail" => [
            "Qty" => 1,
            "UnitPrice" => 100,
            "ItemRef" => [
                "value" => "123" // The ID of a valid QuickBooks item
            ]
        ],
        "Description" => "Consulting services"
    ]
];

$invoiceData = [
    'customer_id' => '1', // Valid customer ID in QuickBooks
    'line_items' => $lineItems
];

$invoice = $quickbooksInvoiceService->createInvoice($invoiceData);
```

### Updating an Invoice

To update an invoice:

```php
$updatedInvoiceData = [
    'customer_id' => '1',
    'line_items' => $lineItems
];

$updatedInvoice = $quickbooksInvoiceService->updateInvoice($invoiceId, $updatedInvoiceData);
```

### Retrieving an Invoice

To fetch an invoice by ID:

```php
$invoice = $quickbooksInvoiceService->getInvoice($invoiceId);
```

### Retrieving Invoice PDF

To fetch the PDF version of an invoice:

```php
$pdf = $quickbooksInvoiceService->getInvoicePdf($invoiceId);
return response($pdf, 200)->header('Content-Type', 'application/pdf');
```

## Advanced Usage

For more advanced usage like error handling, token refreshing, or interacting with other QuickBooks Online API entities, refer to the QuickBooks API documentation: [QuickBooks API Documentation](https://developer.intuit.com/app/developer/qbo/docs/get-started).

## Testing

You can write unit and feature tests for your package by running:

```bash
vendor/bin/phpunit
```

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
