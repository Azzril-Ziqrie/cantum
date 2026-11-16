import React from 'react';
import {AbsoluteFill, Img, Sequence, interpolate, spring, staticFile, useCurrentFrame, useVideoConfig, Easing} from 'remotion';
import {CONTENT_END, FADE, FONT, FPS, INK, K as K_BASE, LAPTOP, LAPTOP_Y, MUTED, OUTRO, timeline} from './theme';

const clamp = {extrapolateLeft: 'clamp', extrapolateRight: 'clamp'} as const;

type Variant = 'wide' | 'narrow' | 'portrait';   // 16:9, 4:3, 4:5

/** Ukuran dan posisi laptop untuk kanvas tertentu. Portrait: laptop selebar ±84% kanvas, di tengah vertikal. */
const layoutFor = (VW: number, VH: number) => {
  const variant: Variant = VW / VH < 1 ? 'portrait' : VW / VH < 1.5 ? 'narrow' : 'wide';
  const K = variant === 'portrait' ? (VW * 0.84) / LAPTOP.w : K_BASE;
  const laptopW = LAPTOP.w * K;
  const laptopH = LAPTOP.h * K;
  const laptopX = (VW - laptopW) / 2;
  const laptopY = variant === 'portrait' ? VH * 0.49 - laptopH * 0.54 : LAPTOP_Y;
  return {
    variant, K, laptopW, laptopH, laptopX, laptopY,
    screen: {x: laptopX + LAPTOP.screen.x * K, y: laptopY + LAPTOP.screen.y * K, w: LAPTOP.screen.w * K, h: LAPTOP.screen.h * K},
    bar: LAPTOP.notchH * K,   // menubar setinggi notch, jadi halaman tidak tertutup notch
  };
};
type Layout = ReturnType<typeof layoutFor>;

/** Menubar macOS tipis di bawah notch. */
const MenuBar: React.FC<{L: Layout}> = ({L}) => (
  <div style={{position: 'absolute', left: 0, right: 0, top: 0, height: L.bar, background: 'rgba(250,250,250,.96)', display: 'flex', alignItems: 'center',
    justifyContent: 'space-between', padding: `0 ${16 * (L.K / K_BASE)}px`, fontFamily: FONT, fontSize: 11 * (L.K / K_BASE), color: INK, letterSpacing: '-0.01em'}}>
    <span style={{display: 'flex', gap: 15 * (L.K / K_BASE)}}><b>Chrome</b><span>File</span><span>Edit</span><span>View</span><span>History</span><span>Window</span></span>
    <span style={{display: 'flex', gap: 14 * (L.K / K_BASE)}}><span>Tue 10 Nov</span><span>13.15</span></span>
  </div>
);

const pad = (n: number) => String(n).padStart(5, '0');

/** Satu adegan = urutan JPEG hasil ekstraksi ffmpeg (public/frames/<num>/), sudah dipotong dan dipercepat. */
const Clip: React.FC<{seg: (typeof timeline)[number]; L: Layout}> = ({seg, L}) => {
  const f = useCurrentFrame();
  const opacity = seg.fadeIn ? interpolate(f, [0, FADE], [0, 1], clamp) : 1;
  return (
    <AbsoluteFill style={{opacity}}>
      <Img
        src={staticFile(`frames/${seg.num}/${pad(Math.min(f + 1, seg.len))}.jpg`)}
        style={{position: 'absolute', left: 0, top: 0, width: L.screen.w, height: (L.screen.w * 936) / 1440, objectFit: 'cover'}}
      />
    </AbsoluteFill>
  );
};

const Label: React.FC<{L: Layout}> = ({L}) => {
  const f = useCurrentFrame();
  const cur = [...timeline].reverse().find((s) => f >= s.start + (s.fadeIn ? FADE / 2 : 0)) ?? timeline[0];
  const no = cur.num.slice(0, 2);                                 // "03a", "03b" = satu adegan, tampil sebagai [03]
  const groupStart = timeline.find((s) => s.num.slice(0, 2) === no)!.start;
  const p = interpolate(f - groupStart, [0, 10], [0, 1], clamp);
  const out = interpolate(f, [CONTENT_END - 8, CONTENT_END], [1, 0], clamp);
  const portrait = L.variant === 'portrait';
  return (
    <div key={no} style={{position: 'absolute', left: portrait ? 64 : 96, bottom: portrait ? 120 : 52, fontFamily: FONT, color: INK, opacity: p * out,
      transform: `translateY(${(1 - p) * 8}px)`, display: 'flex', gap: 18, alignItems: 'baseline'}}>
      <span style={{fontSize: portrait ? 18 : 20, color: MUTED, letterSpacing: '0.02em'}}>[{no}]</span>
      <span style={{fontSize: portrait ? 24 : 26, letterSpacing: '-0.02em', textTransform: 'uppercase'}}>{cur.label}</span>
    </div>
  );
};

export const Main: React.FC = () => {
  const f = useCurrentFrame();
  const {fps, width: VW, height: VH} = useVideoConfig();
  const L = layoutFor(VW, VH);
  const {variant} = L;

  // Masuk: laptop naik pelan. Keluar: 16:9 bergeser ke kiri dan mengecil (teks di kanan); 4:3 dan 4:5 naik sedikit (teks di bawah).
  const enter = spring({frame: f, fps, config: {damping: 200, mass: 1.1}, durationInFrames: 34});
  const outP = interpolate(f, [CONTENT_END, CONTENT_END + 36], [0, 1], {...clamp, easing: Easing.bezier(0.5, 0, 0.2, 1)});
  const zoom = interpolate(f, [0, CONTENT_END], [1, 1.045], clamp);   // zoom sangat pelan sepanjang video
  const shrink = {wide: 0.24, narrow: 0.3, portrait: 0.1}[variant];
  const scale = zoom * (1 - shrink * outP);
  const tx = variant === 'wide' ? -430 * outP : 0;
  const ty = (1 - enter) * 120 + (variant === 'narrow' ? -150 * outP : variant === 'portrait' ? -110 * outP : 0);

  const barIn = interpolate(f, [4, 22], [0, 1], clamp);
  const closeIn = interpolate(f, [CONTENT_END + 26, CONTENT_END + 50], [0, 1], clamp);
  const portrait = variant === 'portrait';

  return (
    <AbsoluteFill style={{background: '#fff', fontFamily: FONT}}>
      {/* top bar, seperti .bar di situs Cantum */}
      <div style={{position: 'absolute', top: 0, left: 0, right: 0, height: portrait ? 96 : 84, padding: portrait ? '0 64px' : '0 96px', display: 'flex',
        alignItems: 'center', justifyContent: 'space-between', fontSize: portrait ? 20 : 24, color: INK, opacity: barIn, textTransform: 'uppercase'}}>
        <span>Cantum Studio</span>
        <span style={{color: MUTED}}>Noirlab Collective — 2026</span>
      </div>

      <AbsoluteFill style={{opacity: enter, transform: `translate(${tx}px, ${ty}px) scale(${scale})`, transformOrigin: `${VW / 2}px ${L.laptopY + L.laptopH * 0.5}px`}}>
        {/* bayangan lembut di bawah laptop */}
        <div style={{position: 'absolute', left: L.laptopX + L.laptopW * 0.1, top: L.laptopY + L.laptopH - 40 * (L.K / K_BASE), width: L.laptopW * 0.8, height: 36 * (L.K / K_BASE),
          background: 'radial-gradient(ellipse at center, rgba(0,0,0,.22), rgba(0,0,0,0) 70%)', filter: 'blur(10px)'}} />
        {/* layar: rekaman di belakang foto laptop */}
        <div style={{position: 'absolute', left: L.screen.x, top: L.screen.y, width: L.screen.w, height: L.screen.h, overflow: 'hidden', background: '#fff'}}>
          <div style={{position: 'absolute', left: 0, right: 0, top: L.bar, bottom: 0, overflow: 'hidden'}}>
            {timeline.map((s) => (
              <Sequence key={s.num} from={s.start} durationInFrames={s.len + (s.num === timeline[timeline.length - 1].num ? OUTRO : 0)} layout="none">
                <Clip seg={s} L={L} />
              </Sequence>
            ))}
          </div>
          <MenuBar L={L} />
        </div>
        <Img src={staticFile(LAPTOP.file)} style={{position: 'absolute', left: L.laptopX, top: L.laptopY, width: L.laptopW, height: L.laptopH}} />
      </AbsoluteFill>

      <Label L={L} />

      {/* penutup: hanya alamat situs */}
      <div style={variant === 'wide'
        ? {position: 'absolute', right: 120, top: 0, bottom: 0, width: 700, display: 'flex', alignItems: 'center', opacity: closeIn,
           transform: `translateY(${(1 - closeIn) * 16}px)`, color: INK, fontSize: 56, letterSpacing: '-0.04em'}
        : {position: 'absolute', left: 0, right: 0, bottom: portrait ? 250 : 150, display: 'flex', justifyContent: 'center', opacity: closeIn,
           transform: `translateY(${(1 - closeIn) * 16}px)`, color: INK, fontSize: portrait ? 56 : 64, letterSpacing: '-0.04em'}}>
        noirlabcollective.com
      </div>
    </AbsoluteFill>
  );
};
