import { test, expect, Page } from '@playwright/test';
import { expectFrontendLoggedIn, expectFrontendNotLoggedIn, startFrontendOAuth2Login } from './helpers/frontend';
import { mockLogin } from './helpers/mockLogin';

async function loginToFrontend(page: Page, providerId: string, persona: string): Promise<void> {
    await startFrontendOAuth2Login(page, '/login', providerId);
    await mockLogin(page, persona);
}

test.describe('Frontend manage providers plugin', () => {
    test('a user links a provider, logs in with it and deactivates it', async ({ page, browser }) => {
        await loginToFrontend(page, 'mock', 'fe-linked');
        await expectFrontendLoggedIn(page, 'fe-linked');

        await page.goto('/account');
        await expect(page.locator('#mock-provider a.btn-danger')).toBeVisible();
        await page.locator('#mock-fe-provider a.btn-success').click();
        await mockLogin(page, 'fe-linked-second-identity');

        await expect(page).toHaveURL(/\/account/);
        await expect(page.locator('#mock-fe-provider a.btn-danger')).toBeVisible();

        const otherContext = await browser.newContext();
        const otherPage = await otherContext.newPage();
        await loginToFrontend(otherPage, 'mock-fe', 'fe-linked-second-identity');
        await expectFrontendLoggedIn(otherPage, 'fe-linked');
        await otherContext.close();

        await page.locator('#mock-fe-provider a.btn-danger').click();
        await expect(page.locator('#mock-fe-provider a.btn-success')).toBeVisible();

        const thirdContext = await browser.newContext();
        const thirdPage = await thirdContext.newPage();
        await loginToFrontend(thirdPage, 'mock-fe', 'fe-linked-second-identity');
        await expectFrontendNotLoggedIn(thirdPage, '/login');
        await thirdContext.close();
    });

    test('the plugin lists no providers for a guest', async ({ page }) => {
        await page.goto('/account');

        await expect(page.locator('#mock-provider')).toHaveCount(0);
    });
});
