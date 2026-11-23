.. include:: ../Includes.txt

.. _configuration-authentik:

=========
authentik
=========

authentik is an OpenID Connect provider and works with the bundled
``GenericProvider``.

At authentik
============

#. :guilabel:`Applications` > :guilabel:`Applications` > :guilabel:`New Application`.
   Name the application, choose :guilabel:`OAuth2/OIDC` as provider type.
#. Configure the provider:

   *  :guilabel:`Client type`: ``Confidential``
   *  :guilabel:`Redirect URIs`: add the four
      :ref:`redirect URIs <configuration-redirect-uris>` as ``Strict`` entries,
      the frontend ones per site language. A ``Regex`` entry can cover several
      sites; escape the dots (``\.``).
   *  Keep the scope mappings ``openid``, ``email`` and ``profile``.

#. Copy :guilabel:`Client ID` and :guilabel:`Client Secret` of the provider.

In TYPO3
========

The endpoints are the same for all applications of an authentik instance:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['oauth2_client']['providers']['authentik'] = [
       'label' => 'authentik',
       'options' => [
           'clientId' => getenv('AUTHENTIK_CLIENT_ID'),
           'clientSecret' => getenv('AUTHENTIK_CLIENT_SECRET'),
           'urlAuthorize' => 'https://auth.example.com/application/o/authorize/',
           'urlAccessToken' => 'https://auth.example.com/application/o/token/',
           'urlResourceOwnerDetails' => 'https://auth.example.com/application/o/userinfo/',
           'responseResourceOwnerId' => 'sub',
           'scopes' => ['openid', 'email', 'profile'],
           'scopeSeparator' => ' ',
       ],
   ];

TYPO3 stores ``sub`` as the identity. Its value depends on the
:guilabel:`Subject mode` of the provider: changing the mode later breaks
existing links.

See the `authentik OAuth2 provider documentation
<https://docs.goauthentik.io/add-secure-apps/providers/oauth2/>`__.
