"""Interaction sounds for the CantumDine-IN reel (30 s, synthesized, no samples, no licence).

Bell and marimba accents on the video's key moments only (scan, add item, payment, order lands, table speaks, call to action).
There is no background music; set BED = True to bring back the light marimba loop over C - Am - F - G.
Run:  python3 tools/make_music.py   ->  public/sfx.mp3
Event times come from the storyboard frames in src/theme.ts (scene start + local frame * K, at 30 fps).
"""
import subprocess, numpy as np

SR = 44100
DUR = 30.0
BED = False   # background music loop; the reel uses interaction sounds only
K = 1.5
FPS = 30
N = int(SR * DUR)
L = np.zeros(N); R = np.zeros(N)

def midi(m): return 440.0 * 2 ** ((m - 69) / 12)

def add(sig, t, pan=0.0, gain=1.0):
    i = int(t * SR)
    if i >= N: return
    s = sig[: N - i] * gain
    L[i:i + len(s)] += s * (1 - max(0, pan)) ; R[i:i + len(s)] += s * (1 + min(0, pan))

def env(n, attack, decay):
    t = np.arange(n) / SR
    return np.minimum(1, t / attack) * np.exp(-t / decay)

def marimba(f, dur=0.55):
    n = int(SR * dur); t = np.arange(n) / SR
    s = np.sin(2 * np.pi * f * t) + 0.35 * np.sin(2 * np.pi * f * 4 * t) * np.exp(-t * 28) + 0.12 * np.sin(2 * np.pi * f * 9.2 * t) * np.exp(-t * 60)
    return s * env(n, 0.002, 0.13) * 0.5

def bell(f, dur=1.6):
    n = int(SR * dur); t = np.arange(n) / SR
    parts = [(1, 1.0, 1.1), (2.76, 0.5, 0.7), (5.4, 0.25, 0.4), (8.9, 0.1, 0.25)]
    s = sum(a * np.sin(2 * np.pi * f * r * t) * np.exp(-t / d) for r, a, d in parts)
    return s * env(n, 0.001, 10) * 0.32

def bass(f, dur=0.9):
    n = int(SR * dur); t = np.arange(n) / SR
    return np.sin(2 * np.pi * f * t) * env(n, 0.01, 0.35) * 0.45

def tick(dur=0.05, seed=0):
    rng = np.random.default_rng(seed); n = int(SR * dur)
    x = rng.standard_normal(n); x = np.diff(x, prepend=0)          # crude high-pass
    return x * env(n, 0.001, 0.012) * 0.12

def thud():
    n = int(SR * 0.4); t = np.arange(n) / SR
    f = 130 * np.exp(-t * 9) + 45
    return np.sin(2 * np.pi * np.cumsum(f) / SR) * env(n, 0.003, 0.14) * 0.9

def blip(f0=520, f1=880, dur=0.09):
    n = int(SR * dur); t = np.arange(n) / SR
    f = f0 + (f1 - f0) * t / dur
    return np.sin(2 * np.pi * np.cumsum(f) / SR) * env(n, 0.002, 0.04) * 0.5

# ------------------------------------------------------------ bed: 100 bpm, C - Am - F - G, eighth-note marimba
BPM = 100; beat = 60 / BPM; eighth = beat / 2
chords = [  # (bass root, arpeggio tones)
    (36, [60, 64, 67, 72]), (33, [57, 60, 64, 69]), (41, [60, 65, 69, 72]), (43, [59, 62, 67, 71]),
]
pent = [72, 74, 76, 79, 81, 84]
rng = np.random.default_rng(11)
pattern = [0, 2, 1, 3, 2, 1, 3, 2]
t = 0.0; bar = 0
while BED and t < DUR - 0.3:
    root, tones = chords[bar % 4]
    add(bass(midi(root)), t, 0, 1.0)
    add(bass(midi(root)), t + 2 * beat, 0, 0.8)
    for k, idx in enumerate(pattern):
        tt = t + k * eighth
        vel = 0.9 if k % 2 == 0 else 0.55
        add(marimba(midi(tones[idx])), tt, pan=(-0.35 if k % 2 else 0.35), gain=0.55 * vel)
        if k % 2: add(tick(seed=bar * 8 + k), tt, 0.2, 1.0)
    # the "ting": a bell on beats 1 and 3, a pentatonic note, sometimes answered a beat later
    for b in (0, 2):
        add(bell(midi(int(rng.choice(pent)))), t + b * beat, pan=0.25 * (1 if b else -1), gain=0.55)
    if bar % 2 == 1:
        add(bell(midi(int(rng.choice(pent)))), t + 3.5 * beat, pan=0.1, gain=0.35)
    t += 4 * beat; bar += 1

# ------------------------------------------------------------ accents on the key moments (seconds)
def at(scene_from, local):  # storyboard frame inside a scene -> seconds
    return (scene_from + local * K) / FPS
SC = dict(intro=0, scan=112, order=270, pay=428, served=585, outro=788)
def chime(t0, notes, step=0.07, gain=0.8):
    for i, m in enumerate(notes): add(bell(midi(m), 1.4), t0 + i * step, 0.0, gain)

chime(at(SC['intro'], 38), [76, 83])                         # wordmark lands
chime(at(SC['scan'], 70), [79, 84, 88], 0.06)                # scan succeeds
add(marimba(midi(91)), at(SC['scan'], 74), 0.2, 0.7)
for local, m in ((36, 84), (50, 88), (64, 91)):              # three menu items added, rising
    add(marimba(midi(m), 0.4), at(SC['order'], local), 0.2, 0.9); add(tick(0.04, local), at(SC['order'], local), 0, 1.5)
add(blip(), at(SC['order'], 74), 0, 0.8)                     # cart bar slides up
chime(at(SC['pay'], 58), [72, 76, 79, 84, 88], 0.07, 1.0)    # payment confirmed
add(thud(), at(SC['served'], 14) + 0.25, 0, 0.9)             # the order lands on its table
chime(at(SC['served'], 14) + 0.3, [84, 91], 0.09, 0.8)
add(blip(620, 980), at(SC['served'], 44), 0, 0.9)            # the table starts talking
chime(at(SC['served'], 44) + 0.12, [88, 93], 0.1, 0.5)
chime(at(SC['outro'], 30), [72, 79, 84, 88], 0.08, 0.9)      # call to action

# ------------------------------------------------------------ master: fade in/out, gentle limit, to mp3
fade = np.ones(N)
fi = int(SR * (0.6 if BED else 0.02)); fo = int(SR * (2.2 if BED else 0.4))
fade[:fi] = np.linspace(0, 1, fi); fade[-fo:] = np.linspace(1, 0, fo) ** 1.5
L *= fade; R *= fade
peak = max(np.abs(L).max(), np.abs(R).max())
g = 0.85 / peak
L = np.tanh(L * g * 1.1); R = np.tanh(R * g * 1.1)
pcm = (np.stack([L, R], 1) * 32767 * 0.9).astype('<i2')
open('/tmp/cd_music.raw', 'wb').write(pcm.tobytes())
subprocess.run(['ffmpeg', '-v', 'error', '-y', '-f', 's16le', '-ar', str(SR), '-ac', '2', '-i', '/tmp/cd_music.raw',
                '-c:a', 'libmp3lame', '-b:a', '192k', 'public/sfx.mp3'], check=True)
print('peak before', round(float(peak), 2), 'rms', round(float(np.sqrt((L ** 2 + R ** 2).mean() / 2)), 3))
