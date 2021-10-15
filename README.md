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

$client = WebPurchases::client(string $clientType (stripe || recurly), string $secretKey, string $publicKey, ?string $token, ?string $idfm);

Fields $token and $idfm are needed to send to the subtruck
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


$prices = $client->getPrices();
```


## Customer

#### Fields
- id
- email

#### Methods
```
use Wowmaking\WebPurchases\Resources\Entities\Customer;


$customer = $client->createCustomer($params); 

$customer = $client->getCustomer($customerId);

$customer = $client->updateCustomer($customerId, $data);
```

## Subscription

#### Fields
- transaction_id
- customer_id
- created_at
- expire_at
- state

#### Methods
```
use Wowmaking\WebPurchases\Resources\Entities\Subscription;


$subscription = $client->createSubscription($params);

$subscriptions = $client->getSubscriptions($params);

$subscriptions = $client->cancelSubscription($params);
```