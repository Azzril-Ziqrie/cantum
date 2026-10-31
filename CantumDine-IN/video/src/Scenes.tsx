import React from 'react';
import {Easing, interpolate, spring, useCurrentFrame, useVideoConfig} from 'remotion';
import {Floor} from './Floor';
import {TableArt} from './TableArt';
import {Brand, Caption, FakeQR, Fade, Phone} from './ui';
import {FONT, INK, K, MUTED, SCENES} from './theme';

/** Scene-local time in storyboard frames: one real frame advances 1/K of a storyboard frame. */
const useT = () => useCurrentFrame() / K;

const clamp = {extrapolateLeft: 'clamp', extrapolateRight: 'clamp'} as const;
const PHONE_W = 600;
const PHONE_H = 1100;
const PHONE_TOP = 230;

const Top: React.FC = () => (
  <div style={{position: 'absolute', left: 80, top: 96}}><Brand /></div>
);

const PhoneSlot: React.FC<{children: React.ReactNode}> = ({children}) => {
  const frame = useCurrentFrame();
  const {fps} = useVideoConfig();
  const p = spring({frame, fps, config: {damping: 200}});
  return (
    <div style={{position: 'absolute', left: (1080 - PHONE_W) / 2, top: PHONE_TOP + (1 - p) * 60, opacity: p}}>
      <Phone width={PHONE_W} height={PHONE_H}>{children}</Phone>
    </div>
  );
};

// ---------------------------------------------------------------- intro
export const Intro: React.FC = () => {
  const f = useT();
  const real = useCurrentFrame();
  const {fps} = useVideoConfig();
  const rise = interpolate(f, [4, 52], [0, 1], {...clamp, easing: Easing.inOut(Easing.cubic)});
  const word = spring({frame: f - 38, fps, config: {damping: 200}});
  const tag = spring({frame: f - 50, fps, config: {damping: 200}});
  const out = interpolate(real, [SCENES.intro.dur - 10, SCENES.intro.dur], [1, 0], clamp);
  return (
    <div style={{position: 'absolute', inset: 0, opacity: out, fontFamily: FONT, color: INK}}>
      <div style={{position: 'absolute', left: 220, top: 420}}><TableArt width={640} rise={rise} grid={4} /></div>
      <div style={{position: 'absolute', left: 0, right: 0, top: 1000, textAlign: 'center', opacity: word, transform: `translateY(${(1 - word) * 30}px)`}}>
        <div style={{fontSize: 132, fontWeight: 500, letterSpacing: '-0.06em'}}>CantumDine-IN</div>
      </div>
      <div style={{position: 'absolute', left: 120, right: 120, top: 1190, textAlign: 'center', opacity: tag, transform: `translateY(${(1 - tag) * 24}px)`}}>
        <div style={{fontSize: 56, lineHeight: 1.25, letterSpacing: '-0.025em'}}>Scan, pesan, dan bayar langsung di meja.</div>
        <div style={{fontSize: 38, color: MUTED, marginTop: 26}}>Pemesanan dine-in untuk restoran dan kafe</div>
      </div>
    </div>
  );
};

// ---------------------------------------------------------------- 01 scan
export const Scan: React.FC = () => {
  const f = useT();
  const {fps} = useVideoConfig();
  const dur = SCENES.scan.dur;
  const bracket = spring({frame: f - 8, fps, config: {damping: 14, stiffness: 120}});
  const scanning = f >= 16 && f < 70;
  const lineY = 0.5 + 0.5 * Math.sin((f - 16) / 6.5 - Math.PI / 2);
  const flash = interpolate(f, [70, 74, 84], [0, 0.85, 0], clamp);
  const toast = spring({frame: f - 74, fps, config: {damping: 15, stiffness: 140}});
  const card = {w: 380, h: 470};
  const b = 26 * (1 + (1 - bracket) * 0.9);
  return (
    <Fade dur={dur}>
      <Top />
      <PhoneSlot>
        <div style={{position: 'absolute', inset: 0, background: 'linear-gradient(180deg,#e7e7e7,#f4f4f4)'}} />
        <div style={{position: 'absolute', left: 0, right: 0, top: 70, textAlign: 'center', fontSize: 34, color: MUTED, opacity: 1 - Math.min(1, toast * 1.6)}}>Arahkan kamera ke QR di meja</div>
        <div style={{position: 'absolute', left: (PHONE_W - card.w) / 2, top: 250, width: card.w, height: card.h}}>
          <div style={{position: 'absolute', inset: 0, background: '#fff', borderRadius: 28, boxShadow: '0 30px 50px -30px rgba(0,0,0,.4)', display: 'grid', placeItems: 'center', alignContent: 'center', gap: 18}}>
            <FakeQR size={290} />
            <div style={{fontSize: 30, fontWeight: 500, letterSpacing: '-0.02em'}}>Meja 4</div>
          </div>
          {[[0, 0], [1, 0], [0, 1], [1, 1]].map(([x, y]) => (
            <div
              key={`${x}${y}`}
              style={{
                position: 'absolute', width: 70, height: 70, [x ? 'right' : 'left']: -b, [y ? 'bottom' : 'top']: -b,
                borderStyle: 'solid', borderColor: INK, borderWidth: 0, borderRadius: 18,
                [`border${y ? 'Bottom' : 'Top'}Width`]: 8, [`border${x ? 'Right' : 'Left'}Width`]: 8,
              }}
            />
          ))}
          {scanning && (
            <div style={{position: 'absolute', left: -14, right: -14, top: lineY * card.h, height: 6, borderRadius: 3, background: INK, boxShadow: '0 0 24px rgba(0,0,0,.45)'}} />
          )}
        </div>
        <div style={{position: 'absolute', inset: 0, background: '#fff', opacity: flash}} />
        <div
          style={{
            position: 'absolute', left: 36, right: 36, top: 36, padding: '22px 28px', borderRadius: 34, background: INK, color: '#fff',
            display: 'flex', alignItems: 'center', gap: 20, fontSize: 32, opacity: toast, transform: `translateY(${(1 - toast) * -140}px)`,
          }}
        >
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
          <span>YourCafe · Meja 4</span>
        </div>
      </PhoneSlot>
      <Caption step="01" title="Scan" text="QR di meja membuka menu cabang. Tanpa unduh aplikasi." dur={dur} />
    </Fade>
  );
};

// ---------------------------------------------------------------- 02 order
const MENU: Array<[string, string]> = [
  ['Iced milk coffee', 'Rp 24.000'],
  ['Special fried rice', 'Rp 34.000'],
  ['French fries', 'Rp 18.000'],
];

export const Order: React.FC = () => {
  const f = useT();
  const {fps} = useVideoConfig();
  const dur = SCENES.order.dur;
  const bar = spring({frame: f - 74, fps, config: {damping: 16, stiffness: 130}});
  const pulse = 1 + 0.05 * Math.max(0, Math.sin((f - 86) / 4)) * (f > 86 ? 1 : 0);
  return (
    <Fade dur={dur}>
      <Top />
      <PhoneSlot>
        <div style={{padding: '64px 44px 0'}}>
          <div style={{display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', paddingBottom: 26, borderBottom: '2px solid #e6e6e6'}}>
            <span style={{fontSize: 44, fontWeight: 500, letterSpacing: '-0.03em'}}>YourCafe</span>
            <span style={{fontSize: 30, color: MUTED}}>Meja 4</span>
          </div>
          {MENU.map(([name, price], i) => {
            const enter = spring({frame: f - (10 + i * 8), fps, config: {damping: 200}});
            const tap = 36 + i * 14;
            const added = f >= tap;
            const pop = spring({frame: f - tap, fps, config: {damping: 9, stiffness: 200}});
            return (
              <div
                key={name}
                style={{
                  display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '38px 0', borderBottom: '2px solid #ececec',
                  opacity: enter, transform: `translateY(${(1 - enter) * 40}px)`,
                }}
              >
                <div>
                  <div style={{fontSize: 38, letterSpacing: '-0.02em'}}>{name}</div>
                  <div style={{fontSize: 30, color: MUTED, marginTop: 6}}>{price}</div>
                </div>
                <div
                  style={{
                    minWidth: 78, height: 78, padding: '0 22px', borderRadius: 39, display: 'grid', placeItems: 'center', fontSize: 34,
                    background: added ? INK : '#f0f0f0', color: added ? '#fff' : INK, transform: `scale(${added ? 0.8 + 0.2 * Math.min(1.25, pop) : 1})`,
                  }}
                >
                  {added ? '1×' : '+'}
                </div>
              </div>
            );
          })}
        </div>
        <div
          style={{
            position: 'absolute', left: 24, right: 24, bottom: 24, padding: '20px 20px 20px 36px', borderRadius: 46, display: 'flex',
            justifyContent: 'space-between', alignItems: 'center', fontSize: 33, background: 'rgba(255,255,255,.8)', border: '2px solid #fff',
            boxShadow: '0 20px 40px -20px rgba(0,0,0,.35)', opacity: bar, transform: `translateY(${(1 - bar) * 160}px)`,
          }}
        >
          <span>3 item · Rp 76.000</span>
          <span style={{background: INK, color: '#fff', padding: '22px 44px', borderRadius: 40, transform: `scale(${pulse})`}}>Bayar</span>
        </div>
      </PhoneSlot>
      <Caption step="02" title="Pesan" text="Pilih menu, ukuran, dan topping. Tanpa akun." dur={dur} />
    </Fade>
  );
};

// ---------------------------------------------------------------- 03 pay
export const Pay: React.FC = () => {
  const f = useT();
  const {fps} = useVideoConfig();
  const dur = SCENES.pay.dur;
  const enter = spring({frame: f - 6, fps, config: {damping: 200}});
  const ok = spring({frame: f - 58, fps, config: {damping: 11, stiffness: 150}});
  const tick = interpolate(f, [62, 80], [1, 0], {...clamp, easing: Easing.out(Easing.cubic)});
  return (
    <Fade dur={dur}>
      <Top />
      <PhoneSlot>
        <div style={{padding: '70px 44px 0', textAlign: 'center'}}>
          <div style={{fontSize: 32, color: MUTED}}>Total pembayaran</div>
          <div style={{fontSize: 92, fontWeight: 500, letterSpacing: '-0.05em', margin: '8px 0 34px'}}>Rp 76.000</div>
          <div style={{display: 'inline-block', padding: 28, borderRadius: 34, background: '#fff', border: '2px solid #e6e6e6', opacity: enter * (f >= 58 ? 0.12 : 1), transform: `scale(${0.92 + 0.08 * enter})`}}>
            <FakeQR size={330} />
          </div>
          <div style={{display: 'flex', gap: 16, justifyContent: 'center', marginTop: 34, opacity: enter * (f >= 58 ? 0.12 : 1)}}>
            <span style={{padding: '14px 30px', borderRadius: 999, background: INK, color: '#fff', fontSize: 30}}>QRIS</span>
            <span style={{padding: '14px 30px', borderRadius: 999, background: '#f0f0f0', fontSize: 30}}>Virtual account bank</span>
          </div>
        </div>
        {f >= 58 && (
          <div style={{position: 'absolute', inset: 0, display: 'grid', placeItems: 'center', alignContent: 'center', gap: 34, paddingBottom: 60}}>
            <div style={{width: 260, height: 260, borderRadius: '50%', background: INK, display: 'grid', placeItems: 'center', transform: `scale(${Math.min(1.12, ok)})`}}>
              <svg width="150" height="150" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round">
                <path d="M5 12.5l4.5 4.5L19 7.5" strokeDasharray="20" strokeDashoffset={20 * tick} />
              </svg>
            </div>
            <div style={{fontSize: 52, fontWeight: 500, letterSpacing: '-0.03em', opacity: Math.min(1, ok)}}>Pembayaran berhasil</div>
          </div>
        )}
      </PhoneSlot>
      <Caption step="03" title="Bayar" text="QRIS atau virtual account bank. Dananya masuk ke akun gateway Anda." dur={dur} />
    </Fade>
  );
};

// ---------------------------------------------------------------- 04 served
export const Served: React.FC = () => {
  const f = useT();
  const dur = SCENES.served.dur;
  return (
    <Fade dur={dur}>
      <Top />
      <div style={{position: 'absolute', left: 0, top: 400, transform: 'scale(0.96)', transformOrigin: '50% 50%'}}>
        <Floor
          statuses={['free', 'making', 'served', 'free', 'making', 'free', 'free', 'served']}
          target={5}
          frame={f}
          dropAt={14}
          speakAt={44}
          heading="Pesanan baru, lunas!"
          items={['1× Iced milk coffee', '1× Special fried rice', '1× French fries']}
        />
      </div>
      <Caption step="04" title="Disajikan" text="Meja berkedip di denah dan dapur langsung tahu apa yang harus dibuat." dur={dur} />
    </Fade>
  );
};

// ---------------------------------------------------------------- outro
export const Outro: React.FC = () => {
  const f = useT();
  const {fps} = useVideoConfig();
  const a = spring({frame: f - 4, fps, config: {damping: 200}});
  const b = spring({frame: f - 18, fps, config: {damping: 200}});
  const c = spring({frame: f - 30, fps, config: {damping: 14, stiffness: 140}});
  const bgIn = interpolate(f, [0, 12], [0, 1], clamp);
  return (
    <div style={{position: 'absolute', inset: 0, fontFamily: FONT, color: '#fff'}}>
      <div style={{position: 'absolute', inset: 0, background: INK, opacity: bgIn}} />
      <div style={{position: 'absolute', left: 0, right: 0, top: 360, display: 'grid', justifyItems: 'center', opacity: a, transform: `translateY(${(1 - a) * 30}px)`}}>
        <div style={{width: 360, height: 'auto'}}><TableArt width={360} status="free" invertInk grid={3} /></div>
      </div>
      <div style={{position: 'absolute', left: 90, right: 90, top: 840, textAlign: 'center', opacity: a, transform: `translateY(${(1 - a) * 30}px)`}}>
        <div style={{fontSize: 124, lineHeight: 1, fontWeight: 500, letterSpacing: '-0.06em'}}>Sekali beli.<br />Tanpa langganan.</div>
      </div>
      <div style={{position: 'absolute', left: 60, right: 60, top: 1150, textAlign: 'center', fontSize: 44, lineHeight: 1.3, color: '#bdbdbd', opacity: b, transform: `translateY(${(1 - b) * 24}px)`}}>
        Satu instalasi per restoran, banyak cabang.
      </div>
      <div style={{position: 'absolute', left: 0, right: 0, top: 1330, display: 'grid', justifyItems: 'center', opacity: c, transform: `scale(${0.9 + 0.1 * Math.min(1.1, c)})`}}>
        <div style={{background: '#fff', color: INK, padding: '34px 70px', borderRadius: 40, fontSize: 48, letterSpacing: '-0.02em'}}>Minta demo di cantum-studio.site</div>
      </div>
    </div>
  );
};
