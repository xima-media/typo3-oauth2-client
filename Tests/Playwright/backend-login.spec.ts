import { test, expect } from '@playwright/test';
import {
    expectBackendLoggedIn,
    expectBackendLoginFailed,
    startBackendOAuth2Login,
    startBackendOAuth2LoginWithTamperedProvider,
} from './helpers/backend';
import { mockLogin, mockLoginAndCatchCallback, withQueryParameter } from './helpers/mockLogin';

test.describe('Backend login', () => {
    test('a linked user logs in with a backend provider', async ({ page }) => {
        await startBackendOAuth2Login(page, 'mock');
        await mockLogin(page, 'be-linked');

        await expectBackendLoggedIn(page, 'BE linked');
    });

    test('a provider without backend scope cannot start a login, even for a linked identity', async ({ page }) => {
        await startBackendOAuth2LoginWithTamperedProvider(page, 'mock-fe');

        await expect(page).not.toHaveURL(/\/\/oidc\./);
        await expectBackendLoginFailed(page);
    });

    test('a callback for a provider without backend scope does not log in a linked identity', async ({ page }) => {
        await startBackendOAuth2Login(page, 'mock');
        const callback = await mockLoginAndCatchCallback(page, 'be-linked');

        await page.goto(withQueryParameter(callback, 'oauth2-provider', 'mock-fe'));

        await expectBackendLoginFailed(page);
    });

    test('an identity that is not linked does not log in', async ({ page }) => {
        await startBackendOAuth2Login(page, 'mock');
        await mockLogin(page, 'be-unlinked');

        await expectBackendLoginFailed(page);
    });

    test('an identity linked to two users does not log in', async ({ page }) => {
        await startBackendOAuth2Login(page, 'mock');
        await mockLogin(page, 'be-twice');

        await expectBackendLoginFailed(page);
    });

    test('a disabled linked user does not log in', async ({ page }) => {
        await startBackendOAuth2Login(page, 'mock');
        await mockLogin(page, 'be-disabled');

        await expectBackendLoginFailed(page);
    });

    for (const [name, parameter, value] of [
        ['a tampered state', 'state', 'tampered'],
        ['a missing state', 'state', null],
        ['a missing code', 'code', null],
    ] as const) {
        test(`a callback with ${name} does not log in and discards the OAuth2 session`, async ({ page }) => {
            await startBackendOAuth2Login(page, 'mock');
            const callback = await mockLoginAndCatchCallback(page, 'be-linked');

            await page.goto(withQueryParameter(callback, parameter, value));
            await expectBackendLoginFailed(page);

            // The untouched callback fails as well: the state was consumed or removed
            await page.goto(callback.href);
            await expectBackendLoginFailed(page);
        });
    }

    test('a replayed callback does not log in again', async ({ page, browser }) => {
        await startBackendOAuth2Login(page, 'mock');
        const callback = await mockLoginAndCatchCallback(page, 'be-linked');
        await page.goto(callback.href);
        await expectBackendLoggedIn(page, 'BE linked');

        const otherContext = await browser.newContext();
        const otherPage = await otherContext.newPage();
        await otherPage.goto(callback.href);
        await expectBackendLoginFailed(otherPage);
        await otherContext.close();
    });
});
