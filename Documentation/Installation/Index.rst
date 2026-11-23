.. include:: ../Includes.txt

.. _installation:

============
Installation
============

Requirements
============

=========  ==============  ======  =====================================
Version    TYPO3           PHP     Support
=========  ==============  ======  =====================================
5.x        13.4, 14.3      8.2+    Features and bugfixes
4.x        12.4, 13.4      8.1+    Security fixes only
=========  ==============  ======  =====================================

With Composer
=============

.. code-block:: bash

   composer require co-stack/typo3-oauth2-client
   vendor/bin/typo3 extension:setup -e oauth2_client

``extension:setup`` creates the database tables of the extension.

On **TYPO3 13.4**, backend users link their providers in the user settings of
EXT:setup. Composer does not install it for you, require it if your project
does not have it yet:

.. code-block:: bash

   composer require typo3/cms-setup

On TYPO3 14.3 the user settings are part of EXT:backend, nothing to do.

Composer installs ``league/oauth2-client``. You can add provider packages such
as ``league/oauth2-github`` or ``league/oauth2-google`` and use them with
:confval:`implementationClassName <provider-implementationclassname>`.

Without Composer (classic mode)
===============================

#. Install ``oauth2_client`` from the TER in the :guilabel:`Extensions` module
   (or upload the ZIP file from `extensions.typo3.org
   <https://extensions.typo3.org/extension/oauth2_client>`__).
#. Activate it. TYPO3 creates the database tables.
#. On TYPO3 13.4, activate the system extension ``setup`` (User Settings) too.

The TER package ships ``league/oauth2-client`` in
:file:`Resources/Private/Libs/`. It contains the ``GenericProvider`` only:
configure your provider with its endpoint URLs, as shown in the
:ref:`provider examples <configuration-providers>`.

Next steps
==========

#. Configure at least one provider: :ref:`configuration`.
#. Register the redirect URIs at the provider: :ref:`configuration-redirect-uris`.
#. Frontend only: enable the providers in the site configuration:
   :ref:`configuration-frontend`.
