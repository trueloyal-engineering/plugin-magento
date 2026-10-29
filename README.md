# TrueLoyal for Magento 2

Connects a Magento 2 store to the [TrueLoyal](https://www.trueloyal.com) loyalty platform.

- Composer package: `trueloyal/extension`
- Module: `TrueLoyal_LoyaltyRewards`

## Features

- **Rewards widget.** Shows the TrueLoyal widget on every storefront page. Logged-in customers are signed in to their loyalty account automatically. You can hide the widget from guests.
- **Activity sync.** Sends store activity to TrueLoyal, which awards or deducts points according to your loyalty program. You choose which activities are sent:
  - customer sign-ups and profile or address changes;
  - orders created, paid, shipped, completed or cancelled;
  - full and partial refunds;
  - product reviews, when submitted or approved;
  - abandoned carts, after a timeout you set.
- **Points on the storefront.** Product and cart pages can show how many points a purchase earns. The wording is configurable.
- **In-cart redemption.** Members can redeem points in the cart for these reward types:
  - fixed amount discount;
  - percentage discount;
  - flexible points discount, where the member chooses how many points to spend;
  - free shipping;
  - a free product.
- **Member identifier.** Identify members by email or by Magento customer ID, with an optional prefix, for example to separate several stores in one program.
- **Profile sync from TrueLoyal.** A REST API lets TrueLoyal update customer attributes in Magento, such as `loyalty_tier` and `available_points`.

## Requirements

- Magento Open Source or Adobe Commerce 2.4. The module is tested on 2.4.9.
- PHP 8.1 to 8.5.
- A TrueLoyal account with its Partner ID, API Key and API Key Identifier.

## Installation

Install the module with Composer from the Magento root:

```bash
composer config repositories.trueloyal vcs https://github.com/trueloyal-engineering/plugin-magento
composer require trueloyal/extension:^2.5
bin/magento module:enable TrueLoyal_LoyaltyRewards
bin/magento setup:upgrade
```

In production mode, also run:

```bash
bin/magento setup:di:compile
bin/magento setup:static-content:deploy
bin/magento cache:flush
```

If the old Zinrelo extension (`Zinrelo_LoyaltyRewards`) is installed, remove it first. Otherwise both modules send the same activity.

### Updating

```bash
composer update trueloyal/extension
bin/magento setup:upgrade
```

Then run the production-mode commands above.

## Configuration

Go to **Stores > Configuration > TrueLoyal Loyalty Rewards > TrueLoyal Settings**:

1. Enable the module.
2. Enter the **Partner ID**, **API Key** and **API Key Identifier** from your TrueLoyal account.
3. Select the **Reward Events** to send. An order earns points only once, so select just one of Order Create, Order Paid, Order Shipped and Order Complete.
4. Click **Save Config**. Magento registers the store with TrueLoyal and fills in the **Webhook URL**. If this fails, the error message explains why.

Save the configuration again after changing the keys or the selected events.

## Support

Contact your TrueLoyal account manager.

## License

OSL-3.0 and AFL-3.0.
