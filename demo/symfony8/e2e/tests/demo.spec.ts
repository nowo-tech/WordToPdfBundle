import { test, expect } from '@playwright/test';

/**
 * REQ-DEMO-013 — happy path of the WordToPdf demo (LibreOffice in the demo image): the page renders inside the demo
 * platform shell, the bundled sample converts to a PDF, an upload converts, and a wrong extension is refused.
 */
test.describe('WordToPdf demo', () => {
  test('the page renders inside the demo platform shell with a green runtime check', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.ok()).toBeTruthy();
    await expect(page.getByTestId('demo-platform')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'WordToPdfBundle', level: 1 })).toBeVisible();
    await expect(page.getByTestId('demo-runtime')).toContainText('OK');
  });

  test('the bundled sample converts to a PDF', async ({ request }) => {
    test.setTimeout(240_000);
    const response = await request.get('/sample.pdf', { timeout: 200_000 });
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('application/pdf');
    expect((await response.body()).subarray(0, 4).toString()).toBe('%PDF');
  });

  test('uploading a Word file converts it', async ({ page, request }) => {
    test.setTimeout(240_000);
    const source = await (await request.get('/demo/sample.docx')).body();
    await page.goto('/');
    await page.setInputFiles('input[type="file"]', { name: 'upload.docx', mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', buffer: source });
    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: 200_000 }),
      page.getByRole('button', { name: 'Convert to PDF' }).click(),
    ]);
    expect(download.suggestedFilename()).toContain('[converted].pdf');
  });

  test('a file that is not Word is refused', async ({ page }) => {
    await page.goto('/');
    await page.locator('form#upload-form').evaluate((form: HTMLFormElement) => { form.noValidate = true; });
    await page.setInputFiles('input[type="file"]', { name: 'notes.txt', mimeType: 'text/plain', buffer: Buffer.from('not a word file') });
    await page.getByRole('button', { name: 'Convert to PDF' }).click();
    await expect(page.locator('.nowo-ui-flashes')).toContainText(/invalid file extension|\.docx or \.doc/i);
  });
});
