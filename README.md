# Install

```
composer require wowmaking/web-purchases
```

# Supported payment services
- stripe 
- recurly
- paypal (Not supports customers)

# Clients required parameters
### stripe
- client_type = stripe
- secret_key
### recurly
- client_type = recurly
- secret_key
### paypal
- client_type = paypal
- secret_key
- client_id
- sandbox

# Require
- "php": ">=7.2.0" 
- "stripe/stripe-php": "^7" 
- "recurly/recurly-client": "^4" 
- "guzzlehttp/guzzle": "^7.3" 
- "facebook/php-business-sdk": "^12.0"

# Entities 

## Client
```
use Wowmaking\WebPurchases\WebPurchases;

$clientParams = [
    'client_type' => 'stripe',
    'secret_key' => ...
];

$subtruckParams = [
    'token' => ...,
    'idfm' => ...
];

$fbPixelParams = [
    'token' => ...,
    'pixel_id' => ...,
    'domain' => ...,
    'ip' => ...,
    'user_agent' => ...,
    'fbc' => ...,
    'fbp' => ...,
];

$webPurchases = WebPurchases::service(array $clientParams, ?array $subtruckParams, ?array $fbPixelParams);
```

## Price

#### Fields
- id
- amount
- currency
- trial_period_days
- trial_price_amount

#### Methods
```
use Wowmaking\WebPurchases\Resources\Entities\Price;


$prices = $webPurchases->getPurchasesClient()->getPrices(array $filterPricesIds = []));
```


## Customer

#### Fields
- id
- email
- provider
- provider_response

#### Methods
```
use Wowmaking\WebPurchases\Resources\Entities\Customer;


$customer = $webPurchases->getPurchasesClient()->createCustomer(array $data); 

$customers = $webPurchases->getPurchasesClient()->getCustomers(array $params);

$customer = $webPurchases->getPurchasesClient()->getCustomer(string $customerId);

$customer = $webPurchases->getPurchasesClient()->updateCustomer(string $customerId, array $data);
```

## Subscription

#### Fields
- transaction_id
- plan_name
- email
- currency
- amount
- customer_id
- created_at
- trial_start_at
- trial_end_at
- expire_at
- canceled_at
- state
- is_active
- provider
- provider_response

### Methods

#### Create subscription
```
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

$subscription = $webPurchases->getPurchasesClient()->createSubscription(array $params);

!!!
This method will automatically send an event to Subtruk and FbPixel 
if you specified the correct settings ($subtruckParams, $fbPixelParams) 
when calling the service
```

#### Get subscriptions
```
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

$subscriptions = $webPurchases->getPurchasesClient()->getSubscriptions(string $customerId);
```

#### Cancel subscription
```
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

$subscriptions = $webPurchases->getPurchasesClient()->cancelSubscription(string $subscriptionId);
```