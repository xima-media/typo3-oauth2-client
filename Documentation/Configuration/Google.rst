.. include:: ../Includes.txt

.. _configuration-google:

======
Google
======

At Google
=========

#. Google Cloud console: :guilabel:`APIs & Services` > :guilabel:`Credentials`
   > :guilabel:`Create credentials` > :guilabel:`OAuth client ID`.
   Configure the consent screen first if Google asks for it.
#. :guilabel:`Application type`: ``Web application``.
#. :guilabel:`Authorized redirect URIs`: add the four
   :ref:`redirect URIs <configuration-redirect-uris>`.
#. Copy client ID and client secret.

In TYPO3
========

Google supports OpenID Connect, so the bundled ``GenericProvider`` works:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['oauth2_client']['providers']['google'] = [
       'label' => 'Google',
       'options' => [
           'clientId' => getenv('GOOGLE_CLIENT_ID'),
           'clientSecret' => getenv('GOOGLE_CLIENT_SECRET'),
           'urlAuthorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
           'urlAccessToken' => 'https://oauth2.googleapis.com/token',
           'urlResourceOwnerDetails' => 'https://openidconnect.googleapis.com/v1/userinfo',
           'responseResourceOwnerId' => 'sub',
           'scopes' => ['openid', 'email', 'profile'],
           'scopeSeparator' => ' ',
       ],
   ];

With Composer you can use ``league/oauth2-google`` instead
(``'implementationClassName' => \League\OAuth2\Client\Provider\Google::class``,
only ``clientId`` and ``clientSecret`` as options). It stores the same
``sub`` value.
