import { test, expect } from '@playwright/test';

test('visit login page', async ({ page }) => {
  await page.goto('http://127.0.0.1:8000/login', { waitUntil: 'networkidle' });
  
  const emailInput = await page.$('input[name="email"]');
  expect(emailInput).toBeTruthy();
  
  const body = await page.content();
  expect(body).toContain('Login');
});

test('check page has form', async ({ page }) => {
  await page.goto('http://127.0.0.1:8000/login');
  
  const form = await page.$('form');
  expect(form).toBeTruthy();
});
