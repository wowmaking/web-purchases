# wowmaking web-purchases


## Install

```
composer require wowmaking/web-purchases
```
## Example

```
use Wowmaking\WebPurchases\WebPurchases;

$client = WebPurchases::service('stripe', 'secret_api_key', 'public_api_key')->getClient();

$client->getPrices();

$client->createCustomer($params);
```