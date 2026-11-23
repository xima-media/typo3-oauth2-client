.. include:: ../Includes.txt

.. _configuration-github:

======
GitHub
======

At GitHub
=========

#. :guilabel:`Settings` > :guilabel:`Developer settings` >
   :guilabel:`OAuth Apps` > :guilabel:`New OAuth App`
   (https://github.com/settings/developers).
#. :guilabel:`Authorization callback URL`: GitHub allows one URL and accepts
   every redirect URI below its path. Enter the root of your site, for example
   ``https://example.com/``, to cover the backend and the frontend
   :ref:`redirect URIs <configuration-redirect-uris>`.
#. Copy the client ID and generate a client secret.

In TYPO3
========

With Composer, use the GitHub provider package:

.. code-block:: bash

   composer require league/oauth2-github

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['oauth2_client']['providers']['github'] = [
       'label' => 'GitHub',
       'implementationClassName' => \League\OAuth2\Client\Provider\Github::class,
       'options' => [
           'clientId' => getenv('GITHUB_CLIENT_ID'),
           'clientSecret' => getenv('GITHUB_CLIENT_SECRET'),
       ],
   ];

Without Composer, use the bundled ``GenericProvider``:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['oauth2_client']['providers']['github'] = [
       'label' => 'GitHub',
       'options' => [
           'clientId' => getenv('GITHUB_CLIENT_ID'),
           'clientSecret' => getenv('GITHUB_CLIENT_SECRET'),
           'urlAuthorize' => 'https://github.com/login/oauth/authorize',
           'urlAccessToken' => 'https://github.com/login/oauth/access_token',
           'urlResourceOwnerDetails' => 'https://api.github.com/user',
       ],
   ];

Both store the numeric GitHub user ID (``id``), so you can switch between them.
