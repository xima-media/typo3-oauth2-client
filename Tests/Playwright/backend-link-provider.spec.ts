import { test, expect, Page } from '@playwright/test';
import { expectBackendLoggedIn, expectBackendLoginFailed, loginToBackend, startBackendOAuth2Login } from './helpers/backend';
import { mockLogin } from './helpers/mockLogin';

async function openManageProviders(page: Page): Promise<void> {
    const setupModuleHref = await page.locator('a[href*="/module/user/setup"]').first().getAttribute('href');
    await page.goto(setupModuleHref!);
    const manageProvidersHref = await page.locator('a[href*="/oauth2/manage/providers"]').getAttribute('href');
    await page.goto(manageProvidersHref!);
    await expect(page.locator('#mock-provider')).toBeVisible();
}

async function submitVerifyForm(page: Page, providerId: string, code: string, state: string): Promise<void> {
    await page.evaluate(([provider, code, state]) => {
        (document.getElementById('oauth2-provider') as HTMLInputElement).value = provider;
        (document.getElementById('oauth2-code') as HTMLInputElement).value = code;
        (document.getElementById('oauth2-state') as HTMLInputElement).value = state;
        (document.getElementById('oauth2-authorize') as HTMLFormElement).submit();
    }, [providerId, code, state]);
    await page.waitForLoadState();
}

test.describe('Backend provider linking', () => {
    test('a user links a provider in the user settings, logs in with it and deactivates it', async ({ page, browser }) => {
        await loginToBackend(page, 'mock', 'be-linker', 'BE linker');
        await openManageProviders(page);
        await expect(page.locator('#mock-provider .badge-success')).toBeVisible();
        await expect(page.locator('#mock-be-provider .badge-info')).toBeVisible();

        const popupPromise = page.waitForEvent('popup');
        await page.locator('#mock-be-provider button[data-oauth2-provider]').click();
        const popup = await popupPromise;
        await mockLogin(popup, 'be-linker-second-identity');
        await popup.waitForEvent('close');

        await expect(page.locator('#mock-be-provider .badge-success')).toBeVisible();

        const otherContext = await browser.newContext();
        const otherPage = await otherContext.newPage();
        await startBackendOAuth2Login(otherPage, 'mock-be');
        await mockLogin(otherPage, 'be-linker-second-identity');
        await expectBackendLoggedIn(otherPage, 'BE linker');
        await otherContext.close();

        await page.locator('#mock-be-provider a.btn-danger').click();
        await page.locator('.modal').getByRole('button', { name: 'OK' }).click();
        await expect(page.locator('#mock-be-provider .badge-info')).toBeVisible();

        const thirdContext = await browser.newContext();
        const thirdPage = await thirdContext.newPage();
        await startBackendOAuth2Login(thirdPage, 'mock-be');
        await mockLogin(thirdPage, 'be-linker-second-identity');
        await expectBackendLoginFailed(thirdPage);
        await thirdContext.close();
    });

    test('a provider without backend scope cannot be linked', async ({ page }) => {
        await loginToBackend(page, 'mock', 'be-linker', 'BE linker');
        await openManageProviders(page);

        const authorize = (providerId: string) => page.request.get(
            `/typo3/oauth2/callback/handle?action=authorize&oauth2-provider=${providerId}`,
            { maxRedirects: 0 }
        );
        expect((await authorize('mock-be')).status()).toBe(302);
        expect((await authorize('mock-fe')).status()).toBe(401);

        await submitVerifyForm(page, 'mock-fe', 'any-code', 'any-state');
        await expect(page.getByText('Error while adding provider.')).toBeVisible();
        await expect(page.locator('#mock-fe-provider')).toHaveCount(0);
    });

    test('a verification with an invalid state does not link the provider', async ({ page }) => {
        await loginToBackend(page, 'mock', 'be-linker', 'BE linker');
        await openManageProviders(page);
        // A real authorization request puts a state into the OAuth2 session
        await page.request.get('/typo3/oauth2/callback/handle?action=authorize&oauth2-provider=mock-be', { maxRedirects: 0 });

        await submitVerifyForm(page, 'mock-be', 'any-code', 'tampered');

        await expect(page.getByText('Error while adding provider.')).toBeVisible();
        await expect(page.locator('#mock-be-provider .badge-info')).toBeVisible();
    });
});
