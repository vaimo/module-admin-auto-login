# vaimo/module-admin-auto-login

[![Latest Stable Version](https://poser.pugx.org/vaimo/module-admin-auto-login/v/stable)](https://packagist.org/packages/vaimo/module-admin-auto-login)
[![Total Downloads](https://poser.pugx.org/vaimo/module-admin-auto-login/downloads)](https://packagist.org/packages/vaimo/module-admin-auto-login)
[![Daily Downloads](https://poser.pugx.org/vaimo/module-admin-auto-login/d/daily)](https://packagist.org/packages/vaimo/module-admin-auto-login)
[![License](https://poser.pugx.org/vaimo/module-admin-auto-login/license)](https://packagist.org/packages/vaimo/module-admin-auto-login)

This module bypasses the admin panel login screen, automatically logging in as the user provided in the configuration. Since it doesn't run any validations, this module should be used only on local environments (i.e.: not public).

## Installation

    composer require --dev vaimo/module-admin-auto-login
    php bin/magento module:enable Vaimo_AdminAutoLogin
    php bin/magento setup:upgrade

## Usage

Access the admin URL for the project. You should be logged in automatically.

## Configuration

You can change the user to login in `Stores` > `Settings` > `Configuration` > `Advanced` > `Admin` > `Admin Auto Login`.

If no username is assigned in the admin pages, it will fallback to `admin`. If it is not available, it will fallback to the first user it finds.

## Support

Support will be provided on a best efforts basis through [GitHub Issues](1).
Pull requests are welcome.

 [1]: https://github.com/vaimo/module-admin-auto-login/issues
