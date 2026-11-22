import { Page, expect } from '@playwright/test';

export async function startFrontendOAuth2Login(page: Page, loginPath: string, providerId: string): Promise<void> {
    await page.goto(loginPath);
    await page.click(`button[name="oauth2-provider"][value="${providerId}"]`);
}

/**
 * Submit the felogin form with a provider that has no button, as a tampered request would.
 */
export async function startFrontendOAuth2LoginWithTamperedProvider(page: Page, loginPath: string, providerId: string): Promise<void> {
    await page.goto(loginPath);
    const button = page.locator('button[name="oauth2-provider"]').first();
    await button.evaluate((element: HTMLButtonElement, value: string) => { element.value = value; }, providerId);
    await button.click();
}

/**
 * felogin renders its logout form with the username once a frontend user is logged in.
 */
export async function expectFrontendLoggedIn(page: Page, username: string): Promise<void> {
    await expect(page.locator('input[name="logintype"][value="logout"]')).toHaveCount(1);
    await expect(page.locator('body')).toContainText(username);
}

export async function expectFrontendNotLoggedIn(page: Page, loginPath: string): Promise<void> {
    await page.goto(loginPath);
    await expect(page.locator('button[name="oauth2-provider"]').first()).toBeVisible();
    await expect(page.locator('input[name="logintype"][value="logout"]')).toHaveCount(0);
}
