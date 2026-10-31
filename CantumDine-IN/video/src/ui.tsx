import React from 'react';
import {Img, interpolate, spring, staticFile, useCurrentFrame, useVideoConfig} from 'remotion';
import {FONT, INK, MUTED} from './theme';

/** A pretend QR code (illustration only): seeded pattern plus the three finder squares. */
export const FakeQR: React.FC<{size: number; color?: string}> = ({size, color = INK}) => {
  const n = 25;
  const cell = size / n;
  let seed = 7;
  const rnd = () => ((seed = (seed * 16807) % 2147483647) / 2147483647);
  const rects: React.ReactNode[] = [];
  const finder = (x: number, y: number) => x < 8 && y < 8 || x > n - 9 && y < 8 || x < 8 && y > n - 9;
  for (let y = 0; y < n; y++) for (let x = 0; x < n; x++) {
    if (finder(x, y)) continue;
    if (rnd() > 0.52) rects.push(<rect key={`${x}-${y}`} x={x * cell} y={y * cell} width={cell + 0.5} height={cell + 0.5} />);
  }
  const eye = (cx: number, cy: number) => (
    <g key={`e${cx}${cy}`} transform={`translate(${cx * cell} ${cy * cell})`}>
      <rect width={7 * cell} height={7 * cell} />
      <rect x={cell} y={cell} width={5 * cell} height={5 * cell} fill="#fff" />
      <rect x={2 * cell} y={2 * cell} width={3 * cell} height={3 * cell} />
    </g>
  );
  return (
    <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} fill={color}>
      {rects}
      {eye(0, 0)}
      {eye(n - 7, 0)}
      {eye(0, n - 7)}
    </svg>
  );
};

/** Phone frame: white body, hairline border, soft shadow. */
export const Phone: React.FC<{width: number; height: number; children: React.ReactNode}> = ({width, height, children}) => (
  <div
    style={{
      position: 'relative', width, height, borderRadius: 84, background: '#fff', border: '3px solid #d9d9d9', overflow: 'hidden',
      boxShadow: '0 60px 90px -40px rgba(0,0,0,.35), 0 2px 0 #fff inset', fontFamily: FONT, color: INK,
    }}
  >
    {children}
  </div>
);

/** Small logo + name used at the top of every scene. */
export const Brand: React.FC<{light?: boolean}> = ({light}) => (
  <div style={{display: 'flex', alignItems: 'center', gap: 22, fontFamily: FONT, fontSize: 46, fontWeight: 500, letterSpacing: '-0.03em', color: light ? '#fff' : INK}}>
    <Img src={staticFile('table-lines.png')} style={{width: 92, height: 'auto', filter: light ? 'invert(1)' : undefined}} />
    CantumDine-IN
  </div>
);

/** Step caption: number, big title, one line. Springs in, fades out near the end of its scene. */
export const Caption: React.FC<{step: string; title: string; text: string; dur: number}> = ({step, title, text, dur}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const p = spring({frame: frame - 6, fps, config: {damping: 200}});
  const out = interpolate(frame, [dur - 10, dur], [1, 0], {extrapolateLeft: 'clamp', extrapolateRight: 'clamp'});
  return (
    <div
      style={{
        position: 'absolute', left: 80, right: 80, top: 1360, fontFamily: FONT, color: INK,
        opacity: p * out, transform: `translateY(${(1 - p) * 40}px)`, filter: `blur(${(1 - p) * 10}px)`,
      }}
    >
      <div style={{fontSize: 40, color: MUTED, fontVariantNumeric: 'tabular-nums', letterSpacing: '-0.01em'}}>{step}</div>
      <div style={{fontSize: 148, fontWeight: 500, letterSpacing: '-0.055em', lineHeight: 1, margin: '14px 0 22px'}}>{title}</div>
      <div style={{fontSize: 48, lineHeight: 1.28, letterSpacing: '-0.02em', maxWidth: 860}}>{text}</div>
    </div>
  );
};

/** Scene wrapper: quick fade in and out so cuts feel soft. */
export const Fade: React.FC<{dur: number; children: React.ReactNode}> = ({dur, children}) => {
  const frame = useCurrentFrame();
  const o = interpolate(frame, [0, 8, dur - 8, dur], [0, 1, 1, 0], {extrapolateLeft: 'clamp', extrapolateRight: 'clamp'});
  return <div style={{position: 'absolute', inset: 0, opacity: o}}>{children}</div>;
};
