import React from 'react';
import {AbsoluteFill, Easing, Img, interpolate, spring, staticFile, useCurrentFrame, useVideoConfig} from 'remotion';
import {FONT, INK, LAPTOP, MUTED} from './theme';

const clamp = {extrapolateLeft: 'clamp', extrapolateRight: 'clamp'} as const;
const ease = Easing.bezier(0.65, 0, 0.25, 1);

// Kamera: masuk ke layar (dan ke bola di dalamnya) saat situs scroll masuk, lalu keluar lagi saat bola mengecil.
const IN = [30, 100];
const OUT = [500, 570];
const NFRAMES = 503;
const DELAY = 20;
const S_MAX = 3.0;               // layar memenuhi tinggi kanvas 4:5

export const Main: React.FC = () => {
  const f = useCurrentFrame();
  const {fps, width: VW, height: VH} = useVideoConfig();

  const K = (VW * 0.84) / LAPTOP.w;
  const lw = LAPTOP.w * K, lh = LAPTOP.h * K;
  const lx = (VW - lw) / 2, ly = VH * 0.47 - lh * 0.54;
  const scr = {x: lx + LAPTOP.screen.x * K, y: ly + LAPTOP.screen.y * K, w: LAPTOP.screen.w * K, h: LAPTOP.screen.h * K};
  const bar = LAPTOP.notchH * K;
  const cx = scr.x + scr.w / 2, cy = scr.y + scr.h / 2;

  const enter = spring({frame: f, fps, config: {damping: 200, mass: 1.1}, durationInFrames: 34});
  const zin = interpolate(f, IN, [0, 1], {...clamp, easing: ease});
  const zout = interpolate(f, OUT, [0, 1], {...clamp, easing: ease});
  const z = zin - zout;                                     // 0 → 1 → 0
  const drift = interpolate(f, [0, 30], [1, 1.04], clamp);  // dorong pelan sebelum zoom
  const s = drift * (1 + (S_MAX - 1) * z);
  const tx = (VW / 2 - cx) * z, ty = (VH / 2 - cy) * z;

  const barIn = interpolate(f, [4, 22], [0, 1], clamp) * (1 - interpolate(z, [0, 0.15], [0, 1], clamp));
  const closeIn = interpolate(f, [574, 604], [0, 1], clamp);
  const shadow = 1 - interpolate(z, [0, 0.2], [0, 1], clamp);

  return (
    <AbsoluteFill style={{background: '#fff', fontFamily: FONT}}>
      <div style={{position: 'absolute', top: 0, left: 0, right: 0, height: 96, padding: '0 64px', display: 'flex', alignItems: 'center',
        justifyContent: 'space-between', fontSize: 20, color: INK, opacity: barIn, textTransform: 'uppercase'}}>
        <span>Cantum Studio</span>
        <span style={{color: MUTED}}>Alterthat — 2026</span>
      </div>

      <AbsoluteFill style={{opacity: enter, transform: `translate(${tx}px, ${ty + (1 - enter) * 120}px) scale(${s})`, transformOrigin: `${cx}px ${cy}px`}}>
        <div style={{position: 'absolute', left: lx + lw * 0.1, top: ly + lh - 40 * (K / 0.3), width: lw * 0.8, height: 36 * (K / 0.3), opacity: shadow,
          background: 'radial-gradient(ellipse at center, rgba(0,0,0,.22), rgba(0,0,0,0) 70%)', filter: 'blur(10px)'}} />
        <div style={{position: 'absolute', left: scr.x, top: scr.y, width: scr.w, height: scr.h, overflow: 'hidden', background: '#ececec'}}>
          <div style={{position: 'absolute', left: 0, right: 0, top: bar, bottom: 0, overflow: 'hidden'}}>
            <Img src={staticFile(`frames/${String(Math.max(1, Math.min(f - DELAY + 1, NFRAMES))).padStart(5, '0')}.jpg`)} style={{position: 'absolute', left: 0, top: 0, width: '100%', height: '100%', objectFit: 'cover'}} />
          </div>
          <div style={{position: 'absolute', left: 0, right: 0, top: 0, height: bar, background: 'rgba(250,250,250,.96)', display: 'flex', alignItems: 'center',
            justifyContent: 'space-between', padding: `0 ${16 * (K / 0.3)}px`, fontSize: 11 * (K / 0.3), color: INK, letterSpacing: '-0.01em'}}>
            <span style={{display: 'flex', gap: 15 * (K / 0.3)}}><b>Chrome</b><span>File</span><span>Edit</span><span>View</span><span>History</span><span>Window</span></span>
            <span style={{display: 'flex', gap: 14 * (K / 0.3)}}><span>Thu 12 Nov</span><span>13.15</span></span>
          </div>
        </div>
        <Img src={staticFile(LAPTOP.file)} style={{position: 'absolute', left: lx, top: ly, width: lw, height: lh}} />
      </AbsoluteFill>

      <div style={{position: 'absolute', left: 0, right: 0, bottom: 250, display: 'flex', justifyContent: 'center', opacity: closeIn,
        transform: `translateY(${(1 - closeIn) * 16}px)`, color: INK, fontSize: 56, letterSpacing: '-0.04em'}}>
        alter-that.com
      </div>
    </AbsoluteFill>
  );
};
