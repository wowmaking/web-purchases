# Install

```
composer require wowmaking/web-purchases
```

# Supported payment services
- stripe 
- recurly 

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


$prices = $webPurchases->getPurchasesClient()->getPrices();
```


## Customer

#### Fields
- id
- email

#### Methods
```
use Wowmaking\WebPurchases\Resources\Entities\Customer;


$customer = $webPurchases->getPurchasesClient()->createCustomer($params); 

$customer = $webPurchases->getPurchasesClient()->getCustomer($customerId);

$customer = $webPurchases->getPurchasesClient()->updateCustomer($customerId, $data);
```

## Subscription

#### Fields
- transaction_id
- email
- currency
- amount
- customer_id
- created_at
- expire_at
- state
- provider_response

### Methods

#### Create subscription
```
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

$subscription = $webPurchases->getPurchasesClient()->createSubscription($params);

!!!
This method will automatically send an event to Subtruk and FbPixel 
if you specified the correct settings ($subtruckParams, $fbPixelParams) 
when calling the service
```

#### Get subscriptions
```
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

$subscriptions = $webPurchases->getPurchasesClient()->getSubscriptions($params);
```

#### Cancel subscription
```
use Wowmaking\WebPurchases\Resources\Entities\Subscription;

$subscriptions = $webPurchases->getPurchasesClient()->cancelSubscription($params);
```