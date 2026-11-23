.. include:: ../Includes.txt

.. _configuration-hydra:

=========
Ory Hydra
=========

Ory Hydra is an OpenID Connect provider and works with the bundled
``GenericProvider``.

At Hydra
========

Create a client with the Hydra CLI or the admin API, for example from a JSON
file with ``hydra import oauth2-client clients.json`` (Hydra v2):

.. code-block:: json

   [
       {
           "client_name": "TYPO3",
           "grant_types": ["authorization_code", "refresh_token"],
           "response_types": ["code"],
           "scope": "openid offline",
           "token_endpoint_auth_method": "client_secret_post",
           "redirect_uris": [
               "https://example.com/typo3/login?loginProvider=1616569531&oauth2-provider=hydra&login_status=login&commandLI=attempt",
               "https://example.com/typo3/oauth2/callback/handle?oauth2-provider=hydra&action=callback",
               "https://example.com/_oauth2?oauth2-provider=hydra&logintype=login",
               "https://example.com/_oauth2?oauth2-provider=hydra&tx_oauth2client%5Baction%5D=verify"
           ]
       }
   ]

Add the frontend URIs once per site and language, see
:ref:`configuration-redirect-uris`. Copy the client ID and secret Hydra
returns.

In TYPO3
========

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['oauth2_client']['providers']['hydra'] = [
       'label' => 'Hydra',
       'options' => [
           'clientId' => getenv('HYDRA_CLIENT_ID'),
           'clientSecret' => getenv('HYDRA_CLIENT_SECRET'),
           'urlAuthorize' => 'https://hydra.example.com/oauth2/auth',
           'urlAccessToken' => 'https://hydra.example.com/oauth2/token',
           'urlResourceOwnerDetails' => 'https://hydra.example.com/userinfo',
           'responseResourceOwnerId' => 'sub',
           'scopes' => ['openid'],
           'scopeSeparator' => ' ',
       ],
   ];
