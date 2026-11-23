.. include:: ../Includes.txt

.. _upgrade:

===================
Upgrading to 5.0
===================

Version 5.0 supports TYPO3 13.4 and 14.3. The configuration and the database
tables are unchanged: linked accounts keep working.

#. Check the requirements: TYPO3 13.4 or 14.3, PHP 8.2 or newer.
   TYPO3 12.4 is no longer supported, stay on 4.x there.
#. Update the extension (``composer require co-stack/typo3-oauth2-client:^5.0``
   or the TER version).
#. **TYPO3 13.4 with Composer:** the extension no longer requires
   ``typo3/cms-setup``. Without it, backend users cannot link providers.
   Run ``composer require typo3/cms-setup`` if your project does not require it
   itself. On 14.3 the user settings are part of EXT:backend.
#. Run the database analyzer or ``vendor/bin/typo3 extension:setup``, then
   flush the caches.

Coming from 1.x
===============

The upgrade wizard that renamed the 1.x table ``tx_oauth2_client_configs`` to
``tx_oauth2_beuser_provider_configuration`` is gone. Update to 4.x first, run
its wizard :guilabel:`Migrate OAuth2 table tx_oauth2_client_configs to
tx_oauth2_beuser_provider_configuration`, then update to 5.0.

User TSconfig on TYPO3 14.3
===========================

On TYPO3 14.3 the "Manage OAuth2 providers" user setting is registered as
``oauth2_providers``. If you hide it with user TSconfig, use
``setup.fields.oauth2_providers.disabled = 1`` there.
On 13.4 the name stays ``tx_oauth2_client_configs``.

Classic mode
============

The TER package now bundles ``league/oauth2-client``. Remove copies of the
library that you loaded yourself for this extension, unless other extensions
need them.

See the :ref:`changelog <changelog>` for all changes.
