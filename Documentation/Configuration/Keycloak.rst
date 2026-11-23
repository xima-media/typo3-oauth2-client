.. include:: ../Includes.txt

.. _configuration-keycloak:

========
Keycloak
========

Keycloak is an OpenID Connect provider and works with the bundled
``GenericProvider``.

At Keycloak
===========

#. In the admin console, select your realm, then :guilabel:`Clients` >
   :guilabel:`Create client` with client type ``OpenID Connect``.
#. Enable :guilabel:`Client authentication` and the
   :guilabel:`Standard flow`.
#. :guilabel:`Valid redirect URIs`: add the four
   :ref:`redirect URIs <configuration-redirect-uris>`. Keycloak also accepts a
   wildcard at the end, for example ``https://example.com/*``.
#. Copy the client ID and, from the :guilabel:`Credentials` tab, the client
   secret.

In TYPO3
========

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['oauth2_client']['providers']['keycloak'] = [
       'label' => 'Keycloak',
       'options' => [
           'clientId' => getenv('KEYCLOAK_CLIENT_ID'),
           'clientSecret' => getenv('KEYCLOAK_CLIENT_SECRET'),
           'urlAuthorize' => 'https://keycloak.example.com/realms/<realm>/protocol/openid-connect/auth',
           'urlAccessToken' => 'https://keycloak.example.com/realms/<realm>/protocol/openid-connect/token',
           'urlResourceOwnerDetails' => 'https://keycloak.example.com/realms/<realm>/protocol/openid-connect/userinfo',
           'responseResourceOwnerId' => 'sub',
           'scopes' => ['openid', 'email', 'profile'],
           'scopeSeparator' => ' ',
       ],
   ];

Keycloak before version 17 serves these URLs below ``/auth``, for example
``https://keycloak.example.com/auth/realms/<realm>/…``.

TYPO3 stores ``sub``, the Keycloak user ID. Older versions of this manual
added a custom ``id`` mapper that returns the same user ID: it is no longer
needed.
