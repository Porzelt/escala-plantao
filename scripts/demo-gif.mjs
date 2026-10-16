// Grava os quadros do GIF do README (gerar escala → exportar → aprovar troca).
// Uso: node scripts/demo-gif.mjs && python scripts/frames-to-gif.py
import { chromium } from 'playwright-core';
import { mkdirSync, rmSync } from 'node:fs';

const BASE = process.env.BASE_URL || 'http://localhost';
const DIR = 'storage/app/gif-frames';
rmSync(DIR, { recursive: true, force: true });
mkdirSync(DIR, { recursive: true });

const browser = await chromium.launch({ channel: 'chrome' });
const page = await (await browser.newContext({ viewport: { width: 1280, height: 760 }, locale: 'pt-BR', acceptDownloads: true })).newPage();

let n = 0;
const frames = [];
const snap = async (holdMs = 120) => {
    const file = `${DIR}/${String(n++).padStart(4, '0')}.png`;
    await page.screenshot({ path: file });
    frames.push([file, holdMs]);
};
const hold = async (ms) => snap(ms);

await page.goto(`${BASE}/login`);
await page.fill('input[type=email]', 'gestor1@escala.test');
await page.fill('input[type=password]', 'password');
await hold(1200);
await Promise.all([page.waitForURL('**/dashboard'), page.click('button:has-text("Entrar")')]);
await hold(2200);

await page.click('nav >> text=Escalas');
await page.waitForLoadState('networkidle');
await hold(1400);

await page.click('table a >> nth=0'); // escala do próximo mês (rascunho)
await page.waitForLoadState('networkidle');
await hold(1400);

await page.click('button:has-text("Gerar distribuição")');
await page.waitForSelector('text=plantões distribuídos');
await hold(2600);

await page.waitForLoadState('networkidle');
await page.click('button:has-text("Excel")');
await page.waitForSelector('text=Exportação', { timeout: 15000 });
for (let i = 0; i < 40; i++) {
    await snap(150);
    if (await page.locator('text=100%').count()) break;
    await page.waitForTimeout(120);
}
await page.waitForTimeout(1500); // deixa o download disparar antes de sair da página
await hold(1800);

await page.goto(`${BASE}/trocas`).catch(() => page.goto(`${BASE}/trocas`));
await page.waitForLoadState('networkidle');
await hold(1600);
const approve = page.locator('button:has-text("Aprovar")').first();
if (await approve.count()) {
    await approve.click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(400);
    await hold(2400);
}

await page.goto(`${BASE}/auditoria`);
await page.waitForLoadState('networkidle');
await page.click('text=ver diff >> nth=0');
await hold(2600);

await browser.close();
const { writeFileSync } = await import('node:fs');
writeFileSync(`${DIR}/frames.json`, JSON.stringify(frames));
console.log(`${frames.length} quadros`);
