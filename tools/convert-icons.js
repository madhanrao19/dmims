import sharp from 'sharp';
import fs from 'fs';
import path from 'path';

const sourceIconsDir = path.resolve(process.cwd(), 'public', 'icons');
const outputIconsDir = path.resolve(process.cwd(), 'public', 'build', 'icons');
const pairs = [
  { svg: 'icon-192.svg', png: 'icon-192.png', size: 192 },
  { svg: 'icon-512.svg', png: 'icon-512.png', size: 512 },
  { svg: 'icon-192.svg', png: 'apple-touch-icon.png', size: 180 },
];

async function convert() {
  if (!fs.existsSync(sourceIconsDir)) {
    console.error('icons directory missing:', sourceIconsDir);
    process.exit(1);
  }

  fs.mkdirSync(outputIconsDir, { recursive: true });

  for (const p of pairs) {
    const svgPath = path.join(sourceIconsDir, p.svg);
    const pngPath = path.join(outputIconsDir, p.png);
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
