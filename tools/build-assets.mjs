// Dev-only asset build. Run with `npm run build:assets` after changing the
// logo, the font choice or the icon list. Everything it writes lives in
// public/ and is committed, so the server never needs Node.
//
//   public/assets/brand/*.png     app icons rendered from the SVG logo files
//   public/favicon.ico            32px small mark (for browsers that ask for /favicon.ico)
//   public/assets/fonts/*.woff2   self-hosted Inter, Space Grotesk
//   public/assets/icons/sprite.svg  Lucide line icons as <symbol id="i-NAME">

import { readFileSync, writeFileSync, copyFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { Resvg } from '@resvg/resvg-js';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const pub = (...p) => join(root, 'public', ...p);
const mod = (...p) => join(root, 'node_modules', ...p);

// ---------------------------------------------------------------- icons ---
// Add a Lucide name here, rerun, then use icon('name') in PHP.
const ICONS = [
  'layout-dashboard', 'presentation', 'cctv', 'laptop', 'users', 'user-plus',
  'user-round-check', 'circle-user-round', 'log-out', 'search', 'plus', 'x',
  'check', 'chevron-right', 'chevron-left', 'chevron-down', 'chevron-up', 'arrow-right',
  'arrow-left-right', 'triangle-alert', 'circle-alert', 'circle-check', 'info',
  'wrench', 'cpu', 'message-square-text', 'refresh-cw', 'sliders-horizontal',
  'pencil', 'trash-2', 'copy', 'upload', 'download', 'menu', 'settings', 'shield-check',
  'clock', 'map-pin', 'ellipsis-vertical', 'loader-circle', 'inbox', 'history',
  'ban', 'rotate-ccw', 'lock', 'tv-minimal',
];

function buildSprite() {
  const symbols = ICONS.map((name) => {
    const svg = readFileSync(mod('lucide-static', 'icons', `${name}.svg`), 'utf8');
    const inner = svg
      .replace(/<!--[\s\S]*?-->/g, '')
      .replace(/^[\s\S]*?<svg[^>]*>/, '')
      .replace(/<\/svg>\s*$/, '')
      .replace(/\s*\n\s*/g, '')
      .trim();
    return `<symbol id="i-${name}" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${inner}</g></symbol>`;
  });
  mkdirSync(pub('assets', 'icons'), { recursive: true });
  writeFileSync(
    pub('assets', 'icons', 'sprite.svg'),
    `<svg xmlns="http://www.w3.org/2000/svg"><!-- Lucide icons (ISC licence), built by tools/build-assets.mjs -->\n${symbols.join('\n')}\n</svg>\n`,
  );
  console.log(`sprite.svg: ${ICONS.length} icons`);
}

// ---------------------------------------------------------------- fonts ---
const FONTS = [
  ['@fontsource-variable/inter', 'inter-latin-wght-normal.woff2'],
  ['@fontsource-variable/inter', 'inter-latin-ext-wght-normal.woff2'],
  ['@fontsource/space-grotesk', 'space-grotesk-latin-700-normal.woff2'],
];

function copyFonts() {
  mkdirSync(pub('assets', 'fonts'), { recursive: true });
  for (const [pkg, file] of FONTS) {
    copyFileSync(mod(pkg, 'files', file), pub('assets', 'fonts', file));
  }
  console.log(`fonts: ${FONTS.length} files`);
}

// ---------------------------------------------------------------- icons ---
function inner(svg) {
  return svg.replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '');
}

function renderPng(svg, size) {
  return new Resvg(svg, { fitTo: { mode: 'width', value: size } }).render().asPng();
}

// The app-icon mark on a white square, scaled to 74% so it sits inside the
// maskable-icon safe zone (a centred circle of 80%).
function appIconSvg() {
  const mark = inner(readFileSync(pub('assets', 'brand', 'app-icon.svg'), 'utf8'));
  const scale = 0.74;
  const offset = (100 - 100 * scale) / 2;
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#FFFFFF"/><g transform="translate(${offset} ${offset}) scale(${scale})">${mark}</g></svg>`;
}

// A .ico file holding one PNG image (supported by every current browser).
function pngToIco(png, size) {
  const header = Buffer.alloc(6 + 16);
  header.writeUInt16LE(0, 0);          // reserved
  header.writeUInt16LE(1, 2);          // type: icon
  header.writeUInt16LE(1, 4);          // image count
  header.writeUInt8(size, 6);          // width
  header.writeUInt8(size, 7);          // height
  header.writeUInt8(0, 8);             // palette
  header.writeUInt8(0, 9);             // reserved
  header.writeUInt16LE(1, 10);         // colour planes
  header.writeUInt16LE(32, 12);        // bits per pixel
  header.writeUInt32LE(png.length, 14);
  header.writeUInt32LE(22, 18);        // offset of image data
  return Buffer.concat([header, png]);
}

function buildIcons() {
  const app = appIconSvg();
  const small = readFileSync(pub('assets', 'brand', 'favicon.svg'), 'utf8');
  const out = {
    'app-icon-512.png': renderPng(app, 512),
    'app-icon-192.png': renderPng(app, 192),
    'apple-touch-icon.png': renderPng(app, 180),
    'favicon-32.png': renderPng(small, 32),
  };
  for (const [file, png] of Object.entries(out)) {
    writeFileSync(pub('assets', 'brand', file), png);
  }
  writeFileSync(pub('favicon.ico'), pngToIco(out['favicon-32.png'], 32));
  console.log(`icons: ${Object.keys(out).join(', ')}, favicon.ico`);
}

buildSprite();
copyFonts();
buildIcons();
