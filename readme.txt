=== Split Pay for Stripe on WooCommerce | Create a Multi-Vendor Marketplace in Minutes ===
Contributors: gauchoplugins, brandonfire, freemius
Donate link: https://splitpayplugin.com
Tags: marketplace, payments, stripe, connect, woocommerce
Requires at least: 5.2.3
Tested up to: 6.7
Stable tag: 3.5.1
Requires PHP: 7.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0

Split payments made in WooCommerce stores between a Stripe Connected Account and a Stripe Platform Account.

== Description ==

The Split Pay Plugin is used by Stripe Connect Platform accounts to transfer a percentage or fixed amount of WooCommerce sales to Connected Stripe Accounts. This is often referred to as split payments and are called Transfers in the Stripe Dashboard. WooCommerce Store owners can set the percentage or a fixed amount of each sale to be transferred to a Stripe Connected Account. 

Splitting payments are useful in a wide variety of cases, such as **multi-vendor marketplaces**, **business partnerships**, **supplier/vendor relationships**, and other situations where WooCommerce store owners may want to automatically transfer a portion of sales to another person or business. 

## Required Plugins
The following plugins are required to use the Split Pay Plugin: 
* WooCommerce
* WooCommerce Stripe Payment Gateway

## Other Requirements
* Transfer amounts must be $1 minimum, per Stripe limitations. 
* Transfers can only be made to Stripe accounts within your Stripe country/region, though any currency can be accepted for payment. This will change soon with new features we are building, so connected Stripe accounts in any country can receive transfers. 
* Proper Webhook configuration, including API keys, Webhook Events, and Webhook Signing Secret

## Free Features

## ％ PERCENTAGE-BASED TRANSFERS
* Transfer a percentage of every WooCommerce sale to a single connected Stripe account. This means every sale will be split based on a predefined percentage. 

## 💱 CURRENCY AGNOSTIC
* Sell with any currency supported by Stripe + WooCommerce. The calculations all happen in the background, regardless of the selected store currency. 

**PRO Features**

## 🔀 SPLIT PAYMENTS ACROSS MULTIPLE CONNECTED ACCOUNTS
* Split payments across multiple vendors, suppliers, or service providers. Just onboard their Stripe account to your platform and configure their transfer amount in the plugin settings. 

## 🏢 EASILY ONBOARD VENDORS TO CONNECT STRIPE
* Invite Vendors or allow them to register. The Vendor login page offers the option for them to connect their Stripe account to your platform, no additional configuration required. 

## 🔍 PRODUCT-LEVEL TRANSFER PERCENTAGES
* Set transfer percentages globally or at the product level. For instance, split all sales with 5% to the connected account, or split Product X with 10% and Product Y with 25%. 

## 📊 GLOBAL OR PRODUCT-LEVEL FIXED TRANSFERS
* Set fixed transfer amounts globally or at the product level. For instance, transfer $5 of each sale to a connected account, or transfer $10 from Product X and $25 from Product Y. 

## 🃏 COMBINE PERCENTAGES AND FIXED AMOUNTS
* Sell some products with a percentage split and some products with a fixed amount split. For instance, transfer $10 from sales of Product X and 25% from sales of Product Y. 

## 🍒 VARIABLE PRODUCTS SUPPORTED 
* Support for variable WooCommerce products - set transfer values for each variation you have configured in WooCommerce. Percentages and fixed amounts can be combined. 

## 🔁 WOO SUBSCRIPTIONS SUPPORTED
* The official Woo Subscriptions plugin is fully supported, so every recurring payment in a subscription automatically transfers the same amount as the first transaction. 

## 🚢 OPTIONALLY TRANSFER SHIPPING FEES
* Are your vendors offering product fulfillment and shipping? Transfer a percentage or fixed amount of shipping fees associated with each product to connected accounts. 

## 💸 OPTIONALLY TRANSFER TAXES
* Transfer 100% of taxes to connected Vendor accounts or transfer partial taxes based on product transfer values. 

## 📃 VIEW TRANSFERS EASILY & EXPORT VIA CSV
* The Transfers tab in the WordPress Dashboard displays a summary of all split pay transactions that can be easily exported into CSV format. 

## ⏩ RELIABLE AND PERFORMANT
* With extensive testing and support for High-Performance Order Storage, you can rest assured that all your transfers will work consistently. 

## 🕜 BULK EDITOR 
* Have hundreds or even thousands of products? Save time by editing their transfer values and connected accounts spreadsheet-style! 

## ✉️ TRANSFER CONFIRMATION EMAIL
* Enable a Transfer Confirmation email that details the Transfer amounts for all items in the transaction, a link to the Transfer in Stripe, and any failures that may occur. 

🌱 [WEBSITE & PRICING](https://splitpayplugin.com/)

📕 [DOCUMENTATION](https://docs.splitpayplugin.com/)

## GAUCHO PLUGINS PORTFOLIO

**[Domain Mapping System](https://wordpress.org/plugins/domain-mapping-system/)**: Create microsites with alias domains

**[Payment Page](https://wordpress.org/plugins/payment-page/)**: Start accepting payments in a beautiful payment form in less than 60 seconds

**[Split Pay Plugin](https://wordpress.org/plugins/bsd-woo-stripe-connect-split-pay/)**: Split WooCommerce payments across multiple connected Stripe accounts. 

**[China Payments Plugin](https://wordpress.org/plugins/wp-stripe-global-payments/)**: Accept WeChat Pay and Alipay payments from Chinese customers.   

**[Blocked in China](https://wordpress.org/plugins/blocked-in-china/)**: Check if your website is available in the Chinese mainland.  

**Speed in China**: Check your website’s speed in the Chinese mainland - coming soon!

== Installation ==

1. Upload "bsd-split-pay-stripe-connect-woo.zip" through the WordPress plugins menu.
2. Click **Activate**.
3. Navigate to **WooCommerce** > **Split Payments** to configure options.
4. Check out our [documentation](https://docs.splitpayplugin.com) for additional information.

== Frequently Asked Questions ==

= What plugins are required to use the Split Pay Plugin? =

To use the Split Pay Plugin, you need the WooCommerce plugin and the WooCommerce Stripe Payment Gateway plugin.

= What is the minimum transfer amount supported by the plugin? =

The minimum transfer amount is $1, per Stripe's limitations.

= Can I transfer payments to Stripe accounts in different countries? = 

Currently, transfers can only be made to Stripe accounts within your Stripe country/region, though any currency can be accepted for payment. However, future updates will allow connected Stripe accounts in any country to receive transfers.

= What are the key features available in the free version of the plugin? =

The free version supports percentage-based transfers to a single connected Stripe account and is currency agnostic.

= What additional features are available in the Pro version of the plugin? =

The Pro version includes features like splitting payments across multiple connected accounts, product-level transfer percentages, fixed transfer amounts, variable product support, Woo Subscriptions support, transferring shipping fees, and a bulk editor for managing transfers.

= How can vendors connect their Stripe accounts to my WooCommerce store? =

Vendors can be invited or allowed to register via a Vendor login page, where they can easily connect their Stripe accounts to your platform without additional configuration.

= Can I set different transfer amounts for different products? =

Yes, you can set transfer percentages or fixed amounts globally or at the product level. For instance, you can split sales with a global percentage or configure specific products with different transfer values.

= Is there a way to view and export the transfer data? =

Yes, the Transfers tab in the WordPress Dashboard provides a summary of all split pay transactions, which can be easily exported to CSV format.

== Screenshots ==

* Multiple Connected Accounts
* Product-level Transfers
* Transfer Shipping Fees
* View Transfers
* Onboarding Vendors

== Changelog ==

= 3.5.1 = 
* Shipping tax handling improvements. 
* Update Freemius SDK. 

See our full changelog in our [documentation](https://docs.splitpayplugin.com/support/changelog). 

== Upgrade Notice ==

