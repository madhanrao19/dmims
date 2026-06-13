import sharp from 'sharp';
import fs from 'fs';
import path from 'path';

const iconsDir = path.resolve(process.cwd(), 'public', 'build', 'icons');
const pairs = [
  { svg: 'icon-192.svg', png: 'icon-192.png', size: 192 },
  { svg: 'icon-512.svg', png: 'icon-512.png', size: 512 },
  { svg: 'icon-192.svg', png: 'apple-touch-icon.png', size: 180 },
];

async function convert() {
  if (!fs.existsSync(iconsDir)) {
    console.error('icons directory missing:', iconsDir);
    process.exit(1);
  }

  for (const p of pairs) {
    const svgPath = path.join(iconsDir, p.svg);
    const pngPath = path.join(iconsDir, p.png);
    if (!fs.existsSync(svgPath)) {
      console.warn('SVG missing:', svgPath);
      continue;
    }
    try {
      await sharp(svgPath).resize(p.size, p.size).png().toFile(pngPath);
      console.log('Wrote', pngPath);
    } catch (err) {
      console.error('Failed to convert', svgPath, err);
    }
  }
}

convert();
