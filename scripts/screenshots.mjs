// Gera os prints do README a partir do app rodando localmente (Sail + seed).
// Uso: node scripts/screenshots.mjs   (usa o Chrome instalado na máquina)
import { chromium } from 'playwright-core';

const BASE = process.env.BASE_URL || 'http://localhost';
const browser = await chromium.launch({ channel: 'chrome' });

async function session(email) {
    const ctx = await browser.newContext({ viewport: { width: 1360, height: 860 }, deviceScaleFactor: 1.5, locale: 'pt-BR' });
    const page = await ctx.newPage();
    await page.goto(`${BASE}/login`);
    await page.fill('input[type=email]', email);
    await page.fill('input[type=password]', 'password');
    await Promise.all([page.waitForURL('**/dashboard'), page.click('button:has-text("Entrar")')]);
    return page;
}

const shot = (page, name, opts = {}) => page.screenshot({ path: `docs/img/${name}.png`, ...opts });

const gestor = await session('gestor1@escala.test');
await shot(gestor, 'painel');

await gestor.goto(`${BASE}/escalas`);
await gestor.click('table a >> nth=1'); // escala do mês atual
await gestor.waitForLoadState('networkidle');
await gestor.click('button:has-text("Excel")');
await gestor.waitForTimeout(350);
await shot(gestor, 'escala');

await gestor.goto(`${BASE}/trocas`);
await shot(gestor, 'trocas');

await gestor.goto(`${BASE}/auditoria`);
await gestor.click('text=ver diff >> nth=0');
await shot(gestor, 'auditoria');

const membro = await session('membro1@escala.test');
await shot(membro, 'painel-membro');

await browser.close();
console.log('prints salvos em docs/img/');
