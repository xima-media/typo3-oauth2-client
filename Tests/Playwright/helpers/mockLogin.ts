import { Page, expect } from '@playwright/test';

/**
 * Sign in on the login page of navikt/mock-oauth2-server.
 * The username selects the persona (claims) from Tests/Fixtures/MockOAuth2/config.json;
 * any other username becomes the `sub` of an unknown identity.
 */
export async function mockLogin(page: Page, username: string): Promise<void> {
    await expect(page).toHaveURL(/\/\/oidc\.[^/]+\/default\/authorize/);
    await page.fill('input[name="username"]', username);
    await page.click('input[type="submit"]');
}

/**
 * Sign in at the IdP, but stop before its redirect back to TYPO3 and return that callback URL
 * (with code and state), so a test can replay or tamper with it.
 */
export async function mockLoginAndCatchCallback(page: Page, username: string): Promise<URL> {
    const callback = new Promise<URL>((resolve) => {
        page.route((url) => /\/default\/authorize$/.test(url.pathname), async (route) => {
            if (route.request().method() !== 'POST') {
                await route.continue();
                return;
            }
            const response = await route.fetch({ maxRedirects: 0 });
            resolve(new URL(response.headers()['location']));
            await route.fulfill({ status: 200, contentType: 'text/plain', body: 'callback intercepted' });
        });
    });
    await mockLogin(page, username);
    const url = await callback;
    await page.unrouteAll({ behavior: 'ignoreErrors' });
    return url;
}

export function withQueryParameter(url: URL, name: string, value: string | null): string {
    const changed = new URL(url.href);
    if (value === null) {
        changed.searchParams.delete(name);
    } else {
        changed.searchParams.set(name, value);
    }
    return changed.href;
}
