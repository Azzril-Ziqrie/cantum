// Potong dan percepat rekaman sesuai src/segments.json, simpan sebagai JPEG 30 fps di public/frames/<num>/.
import {readFileSync, rmSync, mkdirSync, readdirSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const segs = JSON.parse(readFileSync(path.join(root, 'src/segments.json'), 'utf8'));
rmSync(path.join(root, 'public/frames'), {recursive: true, force: true});
for (const s of segs) {
  const dir = path.join(root, 'public/frames', s.num);
  mkdirSync(dir, {recursive: true});
  execFileSync('ffmpeg', ['-y', '-loglevel', 'error', '-ss', String(s.from), '-i', path.join(root, 'rec', `${s.src}.mp4`),
    '-t', ((s.to - s.from) / s.rate).toFixed(4), '-vf', `setpts=PTS/${s.rate},fps=30`, '-vsync', 'vfr', '-q:v', '2', path.join(dir, '%05d.jpg')]);
  const n = readdirSync(dir).length;
  const want = Math.round(((s.to - s.from) / s.rate) * 30);
  console.log(`${s.num} ${s.src}: ${n} frame (rencana ${want})`);
}
