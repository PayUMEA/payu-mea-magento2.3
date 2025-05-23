# PayU MEA Magento 2.X extension #

This guide details how to install the PayU MEA Magento v2.X extension
This plugin is also compatible with Magento v2.3

For Magento 2.4.X please use https://github.com/PayUMEA/gateway-magento

## Prerequisites
* Magento 2.X installed application
* SSH access

## Dependencies

The extension requires the following extension in order to work properly:

- [`soap`](https://php.net/manual/en/book.soap.php)
- [`json`](https://php.net/manual/en/book.json.php)

## Installation

## Via Composer

You can install the extension via [Composer](http://getcomposer.org/). Run the following command:

```bash
composer require payumea/payu-mea-magento2.3
```
or add
```bash
payumea/payu-mea-magento2.3
```
to the **require** section of your composer.json and run `composer update`. After installing the extension you need 
to enable the extension by excuting the following command.

```bash
bin/magento module:enable --clear-static-content PayU_EasyPlus
bin/magento setup:upgrade
bin/magento cache:clean
```

## Manualy from GitHub repository

1) Download newest version of plugin from GitHub repository
2) Unpack the downloaded archive
3) Copy the files from "payu-mea-magento2-master" folder to your Magento 2 entity to folder \app\code\PayU\EasyPlus\. If such directory/path doesn't exist you will need to create it

After copying the files you need to enable the extension by excuting the following command:
```bash
php bin/magento module:enable PayU_EasyPlus
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:clean
```

## Configuration
To configure the extension, you have to navigate to **Stores > Configuration > Sales > Payment Methods** and find PayU 
extension listed among other payment methods

When configuring/enablind any payment method please remember to turn off the "Default" setting on every option in given payment method and choose specific value from a dropdown menu:
![image](https://github.com/PayUMEA/payu-mea-magento2.3/assets/51436301/27190236-b254-4b6f-878b-d33c8dbc5e38)


For Kenyan payment methods (Mpesa, Equitel, Airtel Money, Mobile Banking) - configuration in Stores->Configuration->Customers->Customer Configuration->Name and Address Options->Show Telephone must be set to "Required"
