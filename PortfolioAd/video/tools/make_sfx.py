"""Interaction sounds for the ad, placed on the same frames as the motion (30 fps). Writes public/sfx.mp3.
Frames mirror src/theme.ts: HOOK=75, START=75, ALTER_HOLD=240, BEAT=75, TRANS=30, CLOSE=620, TOTAL=750."""
import subprocess, wave
import numpy as np

SR, FPS, TOTAL = 44100, 30, 750
rng = np.random.default_rng(7)
out = np.zeros(int(SR * TOTAL / FPS) + SR)

def put(frame, sig, gain=1.0):
    i = int(SR * frame / FPS)
    out[i:i + len(sig)] += sig[: len(out) - i] * gain

def lowpass(x, k):
    c = np.cumsum(np.insert(x, 0, 0)); return (c[k:] - c[:-k]) / k

def whoosh(dur=0.45, peak=0.55):
    n = int(SR * dur); t = np.linspace(0, 1, n)
    env = np.sin(np.pi * t ** (1 / peak * 0.7)) ** 2
    noise = rng.standard_normal(n + 64)
    band = lowpass(noise, 6)[:n] - lowpass(noise, 60)[:n]      # airy band, no harsh highs
    return band * env * 0.9

def tick(freq=1500, dur=0.11):
    n = int(SR * dur); t = np.arange(n) / SR
    return np.sin(2 * np.pi * freq * t) * np.exp(-t * 55) * 0.5

def chime(dur=1.4):
    n = int(SR * dur); t = np.arange(n) / SR
    s = sum(np.sin(2 * np.pi * f * t) * a for f, a in ((880, 1), (1318.5, 0.6), (1760, 0.35)))
    return s * np.exp(-t * 3.2) * 0.22

at = lambda i: 75 + (0 if i == 0 else 240 + (i - 1) * 75)
put(3, whoosh(0.6), 0.5)                       # hook words rise
put(59, whoosh(0.5), 0.45)                     # hook leaves upward
put(90, tick(1200), 0.5)                       # first project lands
for i in range(1, 5):
    put(at(i) - 15, whoosh(0.45), 0.55)        # scroll starts
    put(at(i), tick(1500), 0.55)               # and settles
put(626, chime(), 1.0)                         # close card

peak = np.abs(out).max(); out = out / peak * 0.8
pcm = (out * 32767).astype('<i2')
with wave.open('out/sfx.wav', 'w') as w:
    w.setnchannels(1); w.setsampwidth(2); w.setframerate(SR); w.writeframes(pcm.tobytes())
subprocess.run(['ffmpeg', '-v', 'error', '-y', '-i', 'out/sfx.wav', '-b:a', '128k', 'public/sfx.mp3'], check=True)
print('ok')
