export default {
  testDir: './tests-playwright',
  use: {
    baseURL: 'http://127.0.0.1:8000',
    headless: true,
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  timeout: 30000,
  expect: {
    timeout: 5000,
  },
};
