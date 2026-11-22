import { test, expect } from '@playwright/test';
import { startBackendOAuth2Login } from './helpers/backend';
import { mockLogin } from './helpers/mockLogin';

test.describe('Backend login', () => {
    test('a linked user logs in with a backend provider', async ({ page }) => {
        await startBackendOAuth2Login(page, 'mock');
        await mockLogin(page, 'be-linked');

        await expect(page.locator('.scaffold-header')).toBeVisible();
        await expect(page.locator('.scaffold-toolbar')).toContainText('BE linked');
    });
});
