export default {
  testDir: './tests/playwright',
  timeout: 60000,
  // ponytail: php artisan serve is single-threaded; parallel workers just
  // queue and time out. Raise workers only with a multi-process server.
  workers: 1,
  use: {
    baseURL: process.env.QA_BASE_URL || 'http://127.0.0.1:8000',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
  },
};