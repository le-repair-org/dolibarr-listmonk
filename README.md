# Listmonk module for [Dolibarr ERP & CRM](https://www.dolibarr.org)

Integrates the [Listmonk](https://listmonk.app) self-hosted email marketing platform with Dolibarr.

Provides a generic API wrapper and admin configuration page (API endpoint, username, token). Other modules can declare a dependency on this module and use the `listmonk_*` functions from `lib/listmonk.lib.php`.

Only tested with Dolibarr v22.
