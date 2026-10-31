import React from 'react';
import {interpolate, spring, useVideoConfig} from 'remotion';
import {Status, TableArt} from './TableArt';
import {FONT, INK, TABLE_RATIO} from './theme';

// The isometric floor from the landing page: a flat plane tilted 60 degrees and turned 45, with the table drawing
// standing on each tile (undoing the tilt so it faces the viewer).
const S = 150;           // tile size
const G = 75;            // gap between tiles
const PAD = 45;          // plane padding
const COLS = 4;
const ROWS = 2;
export const PLANE_W = COLS * S + (COLS - 1) * G + PAD * 2;
export const PLANE_H = ROWS * S + (ROWS - 1) * G + PAD * 2;
const SW = Math.round(S * 1.25);
const SH = Math.round(SW / TABLE_RATIO);

const PAD_STYLE: Record<Status, React.CSSProperties> = {
  free: {background: '#fff', border: '2px solid #d6d6d6'},
  making: {background: '#fff', border: `2px dashed ${INK}`},
  served: {background: '#e4e4e4', border: '2px solid #e4e4e4'},
  new: {background: INK, border: `2px solid ${INK}`},
};

export type FloorProps = {
  statuses: Status[];          // 8 tables, row by row
  target: number;              // index of the table that receives the new order
  frame: number;               // local frame of the scene
  dropAt: number;              // frame the order lands
  speakAt: number;             // frame the typing dots start
  heading: string;
  items: string[];
};

export const Floor: React.FC<FloorProps> = ({statuses, target, frame, dropAt, speakAt, heading, items}) => {
  const {fps} = useVideoConfig();
  const sinceDrop = frame - dropAt;
  const drop = spring({frame: sinceDrop, fps, config: {damping: 13, stiffness: 110}});
  const landed = sinceDrop >= 0;
  const dots = frame >= speakAt && frame < speakAt + 20;
  const talk = spring({frame: frame - speakAt, fps, config: {damping: 11, stiffness: 160}});
  const talkOut = interpolate(frame, [speakAt + 20, speakAt + 28], [0, 1], {extrapolateLeft: 'clamp', extrapolateRight: 'clamp'});

  const planeTop = (760 - PLANE_H) / 2 + 40;
  const tr = Math.floor(target / COLS);
  const tc = target % COLS;
  const dx = PAD + tc * (S + G) + S / 2 - PLANE_W / 2;
  const dy = PAD + tr * (S + G) + S / 2 - PLANE_H / 2;
  const rx = (dx - dy) * Math.SQRT1_2;
  const ry = (dx + dy) * Math.SQRT1_2 * 0.5;
  const bx = 540 + rx;                          // screen position of the table's foot
  const by = planeTop + PLANE_H / 2 + ry;

  return (
    <div style={{position: 'relative', width: 1080, height: 760}}>
      <div
        style={{
          position: 'absolute', left: (1080 - PLANE_W) / 2, top: planeTop, width: PLANE_W, height: PLANE_H,
          transformStyle: 'preserve-3d', transform: 'rotateX(60deg) rotateZ(45deg)', background: '#f6f6f6',
          backgroundImage: `repeating-linear-gradient(0deg, transparent 0 ${S / 2 - 1}px, rgba(0,0,0,.05) ${S / 2 - 1}px ${S / 2}px)`,
          border: '2px solid #d8d8d8', borderRadius: S * 0.2,
          boxShadow: `0 0 0 ${S * 0.1}px #e9e9e9, ${S * 0.1}px ${S * 0.1}px ${S * 0.3}px rgba(0,0,0,.18)`,
        }}
      >
        {statuses.map((base, i) => {
          const r = Math.floor(i / COLS);
          const c = i % COLS;
          const isTarget = i === target;
          const status: Status = isTarget && landed ? 'new' : base;
          const lift = isTarget ? Math.max(0, (1 - drop) * S * 2.6) : 0;
          const opacity = isTarget ? interpolate(sinceDrop, [0, 5], [landed ? 0 : 1, 1], {extrapolateLeft: 'clamp', extrapolateRight: 'clamp'}) : 1;
          const ping = isTarget && landed ? (sinceDrop % 40) / 40 : 0;
          return (
            <div
              key={i}
              style={{position: 'absolute', left: PAD + c * (S + G), top: PAD + r * (S + G), width: S, height: S, transformStyle: 'preserve-3d'}}
            >
              <div style={{position: 'absolute', inset: S * 0.04, borderRadius: S * 0.16, ...PAD_STYLE[status]}} />
              {isTarget && landed && (
                <div
                  style={{
                    position: 'absolute', inset: S * 0.04, borderRadius: S * 0.16, border: `2px solid ${INK}`,
                    transform: `scale(${1 + ping * 0.4})`, opacity: (1 - ping) * 0.8,
                  }}
                />
              )}
              <div
                style={{
                  position: 'absolute', left: '50%', top: '50%', width: S * 0.58, height: S * 0.58, borderRadius: '50%',
                  background: 'rgba(0,0,0,.2)', filter: 'blur(8px)', transform: `translate(-50%, -50%) translateZ(1px) scale(${isTarget ? drop : 1})`,
                  opacity: status === 'new' ? 0 : 1,
                }}
              />
              <div
                style={{
                  position: 'absolute', left: '50%', top: '50%', width: SW, height: SH, marginLeft: -SW / 2, marginTop: -SH,
                  transformOrigin: '50% 100%', opacity,
                  transform: `translateZ(${lift}px) rotateZ(-45deg) rotateX(-60deg)`,
                }}
              >
                <TableArt width={SW} status={status} invertInk={status === 'new'} time={frame / 30} grid={3} />
                <div
                  style={{
                    position: 'absolute', left: '52%', top: '22%', transform: 'translate(-50%, -50%)', padding: '2px 14px', borderRadius: 999,
                    background: '#fff', color: INK, border: `2px solid ${INK}`, fontFamily: FONT, fontWeight: 500, fontSize: 34, lineHeight: 1.2,
                  }}
                >
                  {i + 1}
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {frame >= speakAt && (
        <div
          style={{
            position: 'absolute', left: bx - 56, top: by - SH - 10, transform: `translateY(-100%) scale(${Math.min(1.08, talk)})`,
            transformOrigin: '56px 100%', opacity: Math.min(1, talk * 1.4),
          }}
        >
          <div
            style={{
              position: 'relative', whiteSpace: 'nowrap', background: '#fff', border: `5px solid ${INK}`, borderRadius: 36,
              padding: dots ? '24px 34px' : '20px 32px 22px', fontFamily: FONT, color: INK, boxShadow: '0 30px 40px -28px rgba(0,0,0,.45)',
            }}
          >
            {dots ? (
              <div style={{display: 'flex', gap: 10}}>
                {[0, 1, 2].map((d) => (
                  <i
                    key={d}
                    style={{
                      width: 16, height: 16, borderRadius: '50%', background: INK, display: 'block',
                      opacity: 0.35 + 0.65 * Math.max(0, Math.sin((frame - speakAt) / 3 - d * 0.9)),
                      transform: `translateY(${-8 * Math.max(0, Math.sin((frame - speakAt) / 3 - d * 0.9))}px)`,
                    }}
                  />
                ))}
              </div>
            ) : (
              <div style={{opacity: talkOut}}>
                <div style={{fontSize: 40, fontWeight: 600, letterSpacing: '-0.01em'}}>{heading}</div>
                <div style={{fontSize: 38, marginTop: 8, lineHeight: 1.3}}>
                  {items.map((it) => (<div key={it}>{it}</div>))}
                </div>
              </div>
            )}
            <div
              style={{
                position: 'absolute', left: 36, bottom: -20, width: 32, height: 32, background: '#fff', borderRight: `5px solid ${INK}`,
                borderBottom: `5px solid ${INK}`, transform: 'rotate(45deg)', borderBottomRightRadius: 6,
              }}
            />
          </div>
        </div>
      )}
    </div>
  );
};
