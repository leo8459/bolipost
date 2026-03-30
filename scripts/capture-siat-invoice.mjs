import fs from 'node:fs/promises';
import { accessSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import puppeteer from 'puppeteer';

const args = parseArgs(process.argv.slice(2));
const qrUrl = String(args.url || '').trim();
const outputPath = String(args.output || '').trim();
const format = normalizeFormat(args.format);

if (!qrUrl || !outputPath) {
  printAndExit({
    ok: false,
    error: 'Usage: node scripts/capture-siat-invoice.mjs --url <siat-qr-url> --output <pdf-path> [--format rollo|media]',
  }, 1);
}

const browserPath = findChromeExecutable();
if (!browserPath) {
  printAndExit({
    ok: false,
    error: 'No se encontro Chrome o Edge instalado para automatizar la descarga del SIAT.',
  }, 1);
}

let browser = null;

try {
  browser = await puppeteer.launch({
    headless: true,
    executablePath: browserPath,
    ignoreHTTPSErrors: true,
    timeout: 90000,
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--ignore-certificate-errors',
    ],
  });

  const page = await browser.newPage();
  page.setDefaultTimeout(45000);
  await page.setViewport({ width: 1440, height: 1200, deviceScaleFactor: 1 });
  await page.setUserAgent(
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 ' +
    '(KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36'
  );

  let popupPage = null;
  const popupPromise = new Promise((resolve) => {
    const listener = async (target) => {
      if (target.type() !== 'page') {
        return;
      }

      try {
        const candidate = await target.page();
        if (!candidate || candidate === page) {
          return;
        }

        popupPage = candidate;
        browser.off('targetcreated', listener);
        resolve(candidate);
      } catch {
        // ignorar target no utilizable
      }
    };

    browser.on('targetcreated', listener);
  });

  const navigationUrl = withFormat(qrUrl, format);
  try {
    await page.goto(navigationUrl, { waitUntil: 'domcontentloaded', timeout: 20000 });
  } catch (error) {
    if (!(error instanceof Error) || !error.message.toLowerCase().includes('timeout')) {
      throw error;
    }
  }

  await page.waitForFunction(
    () => document.body && /factura/i.test(document.body.innerText || ''),
    { timeout: 45000 }
  );

  await clickByText(page, 'Descargar Factura');
  await delay(400);
  await clickByText(page, format === 'rollo' ? 'ROLLO' : 'MEDIA PAGINA');

  let ownerPage = page;
  try {
    ownerPage = await Promise.race([
      popupPromise,
      page.waitForFunction(() => location.href.startsWith('blob:'), { timeout: 15000 }).then(() => page),
    ]);
  } catch {
    ownerPage = popupPage || page;
  }

  await ownerPage.waitForFunction(
    () =>
      location.href.startsWith('blob:') ||
      !!document.querySelector('iframe[src^="blob:"], embed[src^="blob:"], pdf-viewer, viewer-toolbar'),
    { timeout: 45000 }
  );

  const blobUrl = await resolveBlobUrl(ownerPage);
  if (!blobUrl) {
    throw new Error('No se pudo localizar el blob del PDF descargado desde SIAT.');
  }

  const base64 = await ownerPage.evaluate(async (url) => {
    const response = await fetch(url);
    const buffer = await response.arrayBuffer();
    const bytes = new Uint8Array(buffer);
    let binary = '';
    const chunkSize = 0x8000;
    for (let index = 0; index < bytes.length; index += chunkSize) {
      const chunk = bytes.subarray(index, index + chunkSize);
      binary += String.fromCharCode(...chunk);
    }

    return btoa(binary);
  }, blobUrl);

  const binary = Buffer.from(base64, 'base64');
  await fs.mkdir(path.dirname(outputPath), { recursive: true });
  await fs.writeFile(outputPath, binary);

  printAndExit({
    ok: true,
    outputPath,
    blobUrl,
    bytes: binary.byteLength,
    format,
  }, 0);
} catch (error) {
  printAndExit({
    ok: false,
    error: error instanceof Error ? error.message : String(error),
  }, 1);
} finally {
  if (browser) {
    await browser.close().catch(() => {});
  }
}

function parseArgs(rawArgs) {
  const parsed = {};

  for (let index = 0; index < rawArgs.length; index += 1) {
    const token = rawArgs[index];
    if (!token.startsWith('--')) {
      continue;
    }

    const key = token.slice(2);
    const next = rawArgs[index + 1];
    if (!next || next.startsWith('--')) {
      parsed[key] = true;
      continue;
    }

    parsed[key] = next;
    index += 1;
  }

  return parsed;
}

function normalizeFormat(value) {
  return String(value || '').toLowerCase() === 'media' ? 'media' : 'rollo';
}

function withFormat(url, format) {
  const current = new URL(url);
  current.searchParams.set('t', format === 'media' ? '2' : '1');
  return current.toString();
}

function findChromeExecutable() {
  const candidates = [
    process.env.PUPPETEER_EXECUTABLE_PATH,
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
  ].filter(Boolean);

  for (const candidate of candidates) {
    try {
      accessSync(candidate);
      return candidate;
    } catch {
      // siguiente candidato
    }
  }

  return null;
}

async function clickByText(page, label) {
  const wanted = label.trim().toLowerCase();
  const clicked = await page.evaluate((textWanted) => {
    const elements = Array.from(document.querySelectorAll('button, a, span, div'));
    const target = elements.find((element) => {
      const text = (element.textContent || '').trim().toLowerCase();
      return text === textWanted || text.includes(textWanted);
    });

    if (!target) {
      return false;
    }

    target.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
    return true;
  }, wanted);

  if (!clicked) {
    throw new Error(`No se encontro el control "${label}" en la pagina del SIAT.`);
  }
}

async function resolveBlobUrl(page) {
  return page.evaluate(() => {
    if (location.href.startsWith('blob:')) {
      return location.href;
    }

    const embedded = document.querySelector('iframe[src^="blob:"], embed[src^="blob:"]');
    if (embedded instanceof HTMLIFrameElement || embedded instanceof HTMLEmbedElement) {
      return embedded.src || null;
    }

    return null;
  });
}

function delay(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function printAndExit(payload, code) {
  process.stdout.write(`${JSON.stringify(payload)}\n`);
  process.exit(code);
}
