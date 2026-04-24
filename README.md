# Listmonk module for [Dolibarr ERP & CRM](https://www.dolibarr.org)

Integrates the [Listmonk](https://listmonk.app) self-hosted email marketing platform with Dolibarr.

Provides a generic API wrapper and admin configuration page (API endpoint, username, token). Other modules can declare a dependency on this module and use the `listmonk_*` functions from `lib/listmonk.lib.php`.

When the Dolibarr **Members** module is also active, a "Newsletter" tab appears on member cards to manage Listmonk subscriptions.

Only tested with Dolibarr v22 and Listmonk v6.
