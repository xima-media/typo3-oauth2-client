import { Page, expect } from '@playwright/test';
import { mockLogin } from './mockLogin';

export const OAUTH2_LOGIN_PROVIDER = '1616569531';

export async function startBackendOAuth2Login(page: Page, providerId: string): Promise<void> {
    await page.goto(`/typo3/login?loginProvider=${OAUTH2_LOGIN_PROVIDER}`);
    await page.click(`button[name="oauth2-provider"][value="${providerId}"]`);
}

/**
 * Submit the OAuth2 login form with a provider that has no button, as a tampered request would.
 */
export async function startBackendOAuth2LoginWithTamperedProvider(page: Page, providerId: string): Promise<void> {
    await page.goto(`/typo3/login?loginProvider=${OAUTH2_LOGIN_PROVIDER}`);
    const button = page.locator('button[name="oauth2-provider"]').first();
    await button.evaluate((element: HTMLButtonElement, value: string) => { element.value = value; }, providerId);
    await button.click();
}

export async function loginToBackend(page: Page, providerId: string, persona: string, realName: string): Promise<void> {
    await startBackendOAuth2Login(page, providerId);
    await mockLogin(page, persona);
    await expectBackendLoggedIn(page, realName);
}

export async function expectBackendLoggedIn(page: Page, realName: string): Promise<void> {
    await expect(page.locator('.scaffold-header')).toBeVisible();
    await expect(page.locator('.scaffold-toolbar')).toContainText(realName);
}

export async function expectBackendLoginFailed(page: Page): Promise<void> {
    await expect(page).toHaveURL(/\/typo3\/login/);
    await expect(page.locator('.scaffold-header')).toHaveCount(0);
    // No backend session exists: the backend sends us back to the login form
    await page.goto('/typo3/main');
    await expect(page).toHaveURL(/\/typo3\/login/);
    await expect(page.locator('button[name="oauth2-provider"]').first()).toBeVisible();
}
