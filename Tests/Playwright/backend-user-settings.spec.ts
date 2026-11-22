import { test, expect } from '@playwright/test';
import { loginToBackend } from './helpers/backend';

test.describe('Backend user settings', () => {
    test('saving the user settings cannot move the identity of another user to the own account', async ({ page, browser }) => {
        await loginToBackend(page, 'mock', 'be-linker', 'BE linker');
        const setupModuleHref = await page.locator('a[href*="/module/user/setup"]').first().getAttribute('href');
        await page.goto(setupModuleHref!);

        // Identity 1 belongs to "BE linked", identity 6 to "BE linker" (Tests/Acceptance/Fixtures/backend.sql)
        const realNameField = page.locator('[name$="realName]"]').first();
        const realNameFieldName = await realNameField.getAttribute('name');
        await realNameField.evaluate((element: HTMLInputElement, name: string) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = '6,1';
            element.form!.appendChild(input);
        }, realNameFieldName!.replace(/realName]$/, 'tx_oauth2_client_configs]'));
        await page.locator('button[name="data[save]"], button[form][name="data[save]"]').first().click();
        await page.waitForLoadState();

        const otherContext = await browser.newContext();
        const otherPage = await otherContext.newPage();
        await loginToBackend(otherPage, 'mock', 'be-linked', 'BE linked');
        await otherContext.close();
    });
});
