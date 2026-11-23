.. include:: ../Includes.txt

.. _configuration-entra-id:

==================
Microsoft Entra ID
==================

Microsoft Entra ID (formerly Azure AD) works with the bundled
``GenericProvider`` and the OpenID Connect user info endpoint of Microsoft Graph.

At Microsoft Entra
==================

#. Microsoft Entra admin center: :guilabel:`App registrations` >
   :guilabel:`New registration`.

   *  :guilabel:`Supported account types`: accounts in this organizational
      directory only (or any organizational directory). Registrations that
      also allow personal Microsoft accounts reject redirect URIs with a query
      string, which TYPO3 needs.
   *  :guilabel:`Redirect URI`: platform :guilabel:`Web`, add the four
      :ref:`redirect URIs <configuration-redirect-uris>` (more under
      :guilabel:`Authentication` after the registration). Entra requires
      ``https`` and allows at most 256 characters per URI.

#. :guilabel:`Certificates & secrets` > :guilabel:`New client secret`. Copy the
   secret value. It expires: renew it in time.
#. Copy :guilabel:`Application (client) ID` and :guilabel:`Directory (tenant) ID`
   from the :guilabel:`Overview`.
#. :guilabel:`API permissions`: the Microsoft Graph delegated permissions
   ``openid``, ``email`` and ``profile`` are enough.

In TYPO3
========

Replace ``<tenant-id>`` with the directory (tenant) ID:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['oauth2_client']['providers']['entra'] = [
       'label' => 'Microsoft',
       'options' => [
           'clientId' => getenv('ENTRA_CLIENT_ID'),
           'clientSecret' => getenv('ENTRA_CLIENT_SECRET'),
           'urlAuthorize' => 'https://login.microsoftonline.com/<tenant-id>/oauth2/v2.0/authorize',
           'urlAccessToken' => 'https://login.microsoftonline.com/<tenant-id>/oauth2/v2.0/token',
           'urlResourceOwnerDetails' => 'https://graph.microsoft.com/oidc/userinfo',
           'responseResourceOwnerId' => 'sub',
           'scopes' => ['openid', 'email', 'profile'],
           'scopeSeparator' => ' ',
       ],
   ];

TYPO3 stores ``sub`` as the identity. Microsoft issues a different ``sub`` per
app registration: a new registration means users have to link their accounts
again.

See `OpenID Connect on the Microsoft identity platform
<https://learn.microsoft.com/en-us/entra/identity-platform/v2-protocols-oidc>`__
and the `redirect URI restrictions
<https://learn.microsoft.com/en-us/entra/identity-platform/reply-url>`__.
