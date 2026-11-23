.. include:: ../Includes.txt

.. _developer_information:

=====================
Developer information
=====================

.. contents::
   :local:
   :depth: 1

.. _developer-events:

PSR-14 events
=============

After TYPO3 has fetched the user from the provider and looked up the linked
TYPO3 user, it dispatches a lookup event. A listener can replace the TYPO3 user,
for example to create users on the fly. Return the full user row; with
:php:`null` (nothing linked, nothing set) the login fails.

``Waldhacker\Oauth2Client\Events\BackendUserLookupEvent``
   Backend login. Getters: ``getProviderId()``, ``getProvider()``,
   ``getAccessToken()``, ``getRemoteUser()``, ``getTypo3User()``,
   ``getRequest()``. Setter: ``setTypo3User(array $typo3User)``.

``Waldhacker\Oauth2Client\Events\FrontendUserLookupEvent``
   Frontend login. Same as above, but ``getSite()`` and ``getLanguage()``
   instead of ``getRequest()``.

.. code-block:: php
   :caption: EXT:site_package/Classes/EventListener/BackendUserLookup.php

   namespace MyVendor\SitePackage\EventListener;

   use TYPO3\CMS\Core\Attribute\AsEventListener;
   use Waldhacker\Oauth2Client\Events\BackendUserLookupEvent;

   #[AsEventListener]
   final class BackendUserLookup
   {
       public function __invoke(BackendUserLookupEvent $event): void
       {
           if ($event->getTypo3User() !== null) {
               return;
           }
           $claims = $event->getRemoteUser()->toArray();
           // find or create a be_users row for $claims, then:
           // $event->setTypo3User($row);
       }
   }

The add-on `xima/xima-oauth2-extended
<https://github.com/xima-media/xima-oauth2-extended>`__ implements user
creation, updates and group mapping with these events.

Other extension points: :confval:`implementationClassName
<provider-implementationclassname>` (own provider classes),
:confval:`collaborators <provider-collaborators>` and the
:ref:`templates <configuration-templates>`.

.. _developer-flows:

Authentication flows
====================

Backend login
-------------

.. uml::

   autonumber

   "TYPO3 Backend" -> "BackendAuthenticationService": Request OAuth2 login
   "BackendAuthenticationService" -> "BackendAuthenticationService": Generate / set state (session)
   "BackendAuthenticationService" -> "OAuth2 Provider": Authentication request (redirect)
   note left of "OAuth2 Provider"
       User enters their provider credentials
       and grants access.
   end note
   "OAuth2 Provider" -> "BackendAuthenticationService": Redirect to callback URL\nwith code & state
   "BackendAuthenticationService" -> "BackendAuthenticationService": Validate state (session)
   "BackendAuthenticationService" -> "OAuth2 Provider": Fetch access token by code
   "BackendAuthenticationService" -> "OAuth2 Provider": Fetch resource owner by access token
   "BackendAuthenticationService" -> "BackendAuthenticationService": Look up identity\n(provider ID & resource owner ID)
   "BackendAuthenticationService" -> "BackendAuthenticationService": Dispatch BackendUserLookupEvent
   "BackendAuthenticationService" -> "TYPO3 Login": Return found user
   "TYPO3 Login" -> "TYPO3 Login": (optional) Evaluate MFA
   "TYPO3 Login" -> "TYPO3 Backend": Redirect to the backend

Backend linking
---------------

.. uml::

   autonumber

   "TYPO3 Backend" -> "AuthorizeController (popup)": "Manage OAuth2 providers" opens a popup
   "AuthorizeController (popup)" -> "AuthorizeController (popup)": Generate / set state (session)
   "AuthorizeController (popup)" -> "OAuth2 Provider": Authorization request (redirect)
   note left of "OAuth2 Provider"
       User enters their provider credentials
       and grants access.
   end note
   "OAuth2 Provider" -> "AuthorizeController (popup)": Redirect to callback URL\nwith code & state
   "AuthorizeController (popup)" -> "VerifyController": Post code & state to the opener
   "VerifyController" -> "VerifyController": Validate state (session)
   "VerifyController" -> "OAuth2 Provider": Fetch access token by code
   "VerifyController" -> "OAuth2 Provider": Fetch resource owner by access token
   "VerifyController" -> "VerifyController": Store identity\n(provider ID, resource owner ID, TYPO3 user)
   "VerifyController" -> "TYPO3 Backend": Redirect to "Manage OAuth2 providers"

The frontend works the same way: ``FrontendAuthenticationService`` handles the
login, ``RegistrationController`` the linking. Middlewares before and after the
frontend authentication route the callback (:file:`Configuration/RequestMiddlewares.php`)
and send the user back to the page the login or linking started on.

.. _developer-local-development:

Local development
=================

Requirements: DDEV and Docker. The project contains a TYPO3 test instance, a
`mock OAuth2 server <https://github.com/navikt/mock-oauth2-server>`__ and
Playwright.

.. code-block:: bash

   ddev start
   ddev init-typo3

``ddev init-typo3`` installs the Composer dependencies if needed, sets up a fresh
database, two sites and the fixtures from :file:`Tests/Acceptance/Fixtures/`,
and configures three mock providers in :file:`config/system/additional.php`:
``mock`` (backend and frontend), ``mock-be`` and ``mock-fe``. Run it again for a
clean state.

*  Backend: ``http://oauth2-client.ddev.site/typo3/``, user ``admin``,
   password ``Passw0rd!``.
*  Frontend login page: ``http://oauth2-client.ddev.site/login``.
*  Mock provider: ``http://oidc.oauth2-client.ddev.site/default``. Its login
   form accepts any user name, which becomes the ``sub`` claim. Linked
   personas from the fixtures are ``be-linked`` (backend user "BE linked")
   and ``fe-linked`` (frontend user "FE linked"); see
   :file:`Tests/Fixtures/MockOAuth2/config.json`.

The instance runs TYPO3 14.3. Switch to 13.4 (and back with ``^14.3``):

.. code-block:: bash

   ddev composer update --with=typo3/cms-core:^13.4
   ddev init-typo3

Tests and checks
----------------

.. code-block:: bash

   ddev composer test:unit         # unit tests
   ddev composer test:functional   # functional tests
   ddev playwright test            # end-to-end tests, after ddev init-typo3
   ddev composer ci:sca            # PHP lint, php-cs-fixer, PHPStan, Composer and YAML checks

GitHub Actions run the same commands for pull requests: unit and functional
tests on TYPO3 13.4 and 14.3 with PHP 8.2 to 8.5, end-to-end tests on both
TYPO3 versions, and static code analysis (:file:`.github/workflows/`).

Bundled libraries
-----------------

The TER package ships ``league/oauth2-client`` in
:file:`Resources/Private/Libs/` for classic mode, and :file:`ext_emconf.php`
contains its autoload configuration. After changing the ``require`` section of
:file:`composer.json`, rebuild both and commit the result:

.. code-block:: bash

   ddev composer libs:bundle

CI fails if the bundle does not match :file:`composer.json`.

Documentation
-------------

Render this manual locally:

.. code-block:: bash

   docker run --rm --pull always -v $(pwd):/project -it ghcr.io/typo3-documentation/render-guides:latest --config=Documentation

Open :file:`Documentation-GENERATED-temp/Index.html`.

.. _developer-release:

Release
=======

#. Update :file:`CHANGELOG.md` and the ``version`` in :file:`ext_emconf.php`.
#. Run ``ddev composer libs:bundle`` if dependencies changed.
#. Merge to ``main`` and push a tag with the version number, for example
   ``5.0.0`` (no ``v`` prefix).

The tag triggers :file:`.github/workflows/release.yml`. It checks that the tag
matches :file:`ext_emconf.php` and publishes the version to the TER.
