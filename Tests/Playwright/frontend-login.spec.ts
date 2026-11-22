import { test, expect } from '@playwright/test';
import {
    expectFrontendLoggedIn,
    expectFrontendNotLoggedIn,
    startFrontendOAuth2Login,
    startFrontendOAuth2LoginWithTamperedProvider,
} from './helpers/frontend';
import { mockLogin } from './helpers/mockLogin';

test.describe('Frontend login', () => {
    test('a linked user logs in with the felogin button and returns to the login page', async ({ page }) => {
        await startFrontendOAuth2Login(page, '/login', 'mock');
        await mockLogin(page, 'fe-linked');

        await expect(page).toHaveURL(/^http:\/\/[^/]+\/login\?tx_felogin_login/);
        await expectFrontendLoggedIn(page, 'fe-linked');
    });

    test('a linked user logs in with a login link and returns to the login page', async ({ page }) => {
        await page.goto('/login?logintype=login&oauth2-provider=mock');
        await mockLogin(page, 'fe-linked');

        await expect(page).toHaveURL(/^http:\/\/[^/]+\/login$/);
        await expectFrontendLoggedIn(page, 'fe-linked');
    });

    test('felogin only offers the providers enabled for the site language', async ({ page }) => {
        await page.goto('/login');
        await expect(page.locator('button[name="oauth2-provider"]')).toHaveText(['Mock', 'Mock FE']);

        await page.goto('/de/login');
        await expect(page.locator('button[name="oauth2-provider"]')).toHaveText(['Mock']);
    });

    for (const [loginPath, providerId, reason] of [
        ['/login', 'mock-be', 'a provider without frontend scope'],
        ['/de/login', 'mock-fe', 'a provider not enabled for the site language'],
    ] as const) {
        test(`${reason} cannot start a login`, async ({ page }) => {
            await startFrontendOAuth2LoginWithTamperedProvider(page, loginPath, providerId);

            await expect(page).not.toHaveURL(/\/\/oidc\./);
            await expectFrontendNotLoggedIn(page, loginPath);
        });
    }

    test('a linked user outside the storage folder of the site does not log in', async ({ page }) => {
        await startFrontendOAuth2Login(page, '/login', 'mock');
        await mockLogin(page, 'fe-otherpid');

        await expect(page).toHaveURL(/^http:\/\/[^/]+\/login\?tx_felogin_login/);
        await expectFrontendNotLoggedIn(page, '/login');
    });

    test('a site without frontend user storage fails with an exception', async ({ page }) => {
        await startFrontendOAuth2Login(page, '/second/login', 'mock');
        const callbackResponse = page.waitForResponse((response) => response.url().includes('/second/_oauth2?'));
        await mockLogin(page, 'fe-linked');

        expect((await callbackResponse).status()).toBe(500);
        await expect(page.locator('body')).toContainText('1646040939');
    });

    test('a login from a translated page returns to the translated login page', async ({ page }) => {
        await startFrontendOAuth2Login(page, '/de/login', 'mock');
        await mockLogin(page, 'fe-linked');

        await expect(page).toHaveURL(/^http:\/\/[^/]+\/de\/login\?tx_felogin_login/);
        await expectFrontendLoggedIn(page, 'fe-linked');
    });
});
