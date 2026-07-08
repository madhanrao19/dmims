import { test, expect } from '@playwright/test';

test('homepage loads without console errors', async ({ page }) => {
  const errors = [];

  page.on('console', msg => {
    if (msg.type() === 'error') errors.push(msg.text());
  });

  await page.goto('/');
  await expect(page).toHaveTitle(/.+/);

  expect(errors).toEqual([]);
});