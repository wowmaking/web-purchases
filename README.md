# Install

```
composer require wowmaking/web-purchases
```

# Payment services
- Stripe 
- Recurly 

# Entities 

## Client
```
use Wowmaking\WebPurchases\WebPurchases;

$client = WebPurchases::service('stripe', 'secret_api_key', 'public_api_key')->getClient();
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