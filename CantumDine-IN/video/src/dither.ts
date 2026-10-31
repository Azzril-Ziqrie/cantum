// Ordered / noise dithering on a canvas. A TypeScript port of the site's assets/js/dither.js (itself a port of
// Aceternity UI's DitherShader), reduced to what the video needs: bayer and noise, duotone.
const B4 = [
  [0, 8, 2, 10],
  [12, 4, 14, 6],
  [3, 11, 1, 9],
  [15, 7, 13, 5],
];

export type DitherOptions = {
  grid: number;
  mode: 'bayer' | 'noise';
  primary: string;     // dark dots
  secondary: string;   // light ground
  time?: number;       // only used by "noise", to make the grain shimmer
};

const parse = (hex: string): [number, number, number] => {
  const h = hex.replace('#', '');
  return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)];
};

/** Dither `source` (any canvas, same size as `target`) into `target`. Pixels with alpha < 10 are skipped. */
export const ditherInto = (target: HTMLCanvasElement, source: HTMLCanvasElement, o: DitherOptions): void => {
  const w = target.width;
  const h = target.height;
  const ctx = target.getContext('2d');
  const sctx = source.getContext('2d');
  if (!ctx || !sctx || !w || !h) return;
  ctx.clearRect(0, 0, w, h);
  const data = sctx.getImageData(0, 0, w, h).data;
  const dark = parse(o.primary);
  const light = parse(o.secondary);
  const t = o.time ?? 0;

  for (let y = 0; y < h; y += o.grid) {
    for (let x = 0; x < w; x += o.grid) {
      const i = (y * w + x) * 4;
      if (data[i + 3] < 10) continue;
      const lum = (0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2]) / 255;
      let d: number;
      if (o.mode === 'noise') {
        const n = Math.sin(x * 12.9898 + y * 78.233 + t * 100) * 43758.5453;
        d = n - Math.floor(n);
      } else {
        d = B4[Math.floor(y / o.grid) % 4][Math.floor(x / o.grid) % 4] / 16;
      }
      d = d * 0.5 + 0.25;   // threshold 0.5, as in the component
      const c = lum < d ? dark : light;
      ctx.fillStyle = `rgb(${c[0]},${c[1]},${c[2]})`;
      ctx.fillRect(x, y, o.grid, o.grid);
    }
  }
};
