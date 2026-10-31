import React, {useEffect, useLayoutEffect, useRef, useState} from 'react';
import {continueRender, delayRender, staticFile} from 'remotion';
import {ditherInto} from './dither';
import {TABLE_RATIO} from './theme';

export type Status = 'free' | 'making' | 'served' | 'new';

// How each status looks once dithered: a flat tint (L) turned into dots of primary on secondary.
export const LOOK: Record<Status, {L: number; mode: 'bayer' | 'noise'; primary: string; secondary: string}> = {
  free: {L: 0.93, mode: 'bayer', primary: '#c4c4c4', secondary: '#ffffff'},
  making: {L: 0.45, mode: 'bayer', primary: '#0a0a0a', secondary: '#ffffff'},
  served: {L: 0.72, mode: 'bayer', primary: '#8c8c8c', secondary: '#e6e6e6'},
  new: {L: 0.2, mode: 'noise', primary: '#000000', secondary: '#f5f5f5'},
};

export const useImage = (src: string): HTMLImageElement | null => {
  const [img, setImg] = useState<HTMLImageElement | null>(null);
  useEffect(() => {
    const handle = delayRender(`image ${src}`);
    const el = new Image();
    el.onload = () => { setImg(el); continueRender(handle); };
    el.onerror = () => continueRender(handle);
    el.src = staticFile(src);
  }, [src]);
  return img;
};

type Props = {
  width: number;
  status?: Status;
  /** 0..1: the body fills with dither from the bottom up (loading / intro). Overrides `status`. */
  rise?: number;
  invertInk?: boolean;
  time?: number;
  grid?: number;
};

/** The table drawing as-is (ink), with its body filled by dither. */
export const TableArt: React.FC<Props> = ({width, status = 'free', rise, invertInk, time = 0, grid = 3}) => {
  const height = Math.round(width / TABLE_RATIO);
  const fill = useImage('table-fill.png');
  const ref = useRef<HTMLCanvasElement>(null);
  const look = LOOK[status];

  useLayoutEffect(() => {
    const target = ref.current;
    if (!target || !fill) return;
    target.width = width;
    target.height = height;
    const src = document.createElement('canvas');
    src.width = width;
    src.height = height;
    const x = src.getContext('2d');
    if (!x) return;
    if (rise !== undefined) {
      const y0 = height * (1.5 * (1 - rise) - 0.5);
      const g = x.createLinearGradient(0, y0, 0, y0 + height * 0.5);
      g.addColorStop(0, '#ffffff');
      g.addColorStop(1, '#0b0b0b');
      x.fillStyle = g;
    } else {
      const v = Math.round(look.L * 255);
      x.fillStyle = `rgb(${v},${v},${v})`;
    }
    x.fillRect(0, 0, width, height);
    x.globalCompositeOperation = 'destination-in';
    x.drawImage(fill, 0, 0, width, height);
    ditherInto(target, src, {
      grid,
      mode: rise !== undefined ? 'bayer' : look.mode,
      primary: rise !== undefined ? '#0b0b0b' : look.primary,
      secondary: rise !== undefined ? '#ffffff' : look.secondary,
      time,
    });
  });

  return (
    <div style={{position: 'relative', width, height}}>
      <canvas ref={ref} style={{position: 'absolute', inset: 0, width, height, imageRendering: 'pixelated'}} />
      <img
        src={staticFile('table-lines.png')}
        width={width}
        height={height}
        style={{position: 'absolute', inset: 0, filter: invertInk ? 'invert(1)' : undefined}}
      />
    </div>
  );
};
