.. include:: ../Includes.txt

.. _configuration-gitlab:

======
GitLab
======

Works with gitlab.com and self-hosted GitLab, using the bundled
``GenericProvider``.

At GitLab
=========

#. :guilabel:`User settings` > :guilabel:`Applications` (or a group or admin
   application) > :guilabel:`Add new application`.
#. :guilabel:`Redirect URI`: add the four
   :ref:`redirect URIs <configuration-redirect-uris>`, one per line.
#. Keep :guilabel:`Confidential` checked and select the scope ``read_user``.
#. Copy :guilabel:`Application ID` and :guilabel:`Secret`.

In TYPO3
========

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['oauth2_client']['providers']['gitlab'] = [
       'label' => 'GitLab',
       'options' => [
           'clientId' => getenv('GITLAB_CLIENT_ID'),
           'clientSecret' => getenv('GITLAB_CLIENT_SECRET'),
           'urlAuthorize' => 'https://gitlab.example.com/oauth/authorize',
           'urlAccessToken' => 'https://gitlab.example.com/oauth/token',
           'urlResourceOwnerDetails' => 'https://gitlab.example.com/api/v4/user',
           'scopes' => ['read_user'],
           'scopeSeparator' => ' ',
       ],
   ];

``/api/v4/user`` returns the numeric GitLab user ID as ``id``, the default of
``responseResourceOwnerId``.
