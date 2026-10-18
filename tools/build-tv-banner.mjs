// Reuse Nexus's existing emblem and wordmark for the Android TV launcher.
import {readFileSync,writeFileSync,mkdirSync} from 'node:fs';
import {Resvg} from '@resvg/resvg-js';
const mark = readFileSync('public/assets/brand/logo-mark.svg','utf8').replace(/<svg[^>]*>/,'').replace('</svg>','');
const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="320" height="180" viewBox="0 0 320 180"><rect width="320" height="180" fill="#ffffff"/><g transform="translate(30 46) scale(.8)">${mark}</g><text x="112" y="94" fill="#1F4E9F" font-family="Space Grotesk" font-weight="700" font-size="44" letter-spacing="-1.76">nexus</text><text x="114" y="119" fill="#5a6472" font-family="Inter" font-size="18">Signage</text></svg>`;
const output = 'android-tv/app/src/main/res/drawable-nodpi';
mkdirSync(output,{recursive:true});
const fonts = process.argv.slice(2);
writeFileSync(`${output}/tv_banner.png`,new Resvg(svg,{font:{fontFiles:fonts,loadSystemFonts:fonts.length===0}}).render().asPng());
console.log('Built Android TV banner: 320 × 180.');
