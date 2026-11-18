// Topographic contour lines behind the hero (vanilla WebGL2, no dependencies).
//  - gradient noise drawn as contour lines, every fifth line is a thicker "index" contour; the noise drifts slowly
//  - on load the relief rises from flat ground (1.8s ease-out)
//  - a peak rises under the cursor on a spring and follows it; leaving the hero lets it sink back
//  - a click or tap drops a wave: a crest and a trough travel out from the point (2.6s) and fade
//  - paused off screen and in hidden tabs; under reduced motion it is a still map (no drift, peak or wave)
(function () {
  var canvas = document.querySelector('.cd-topo');
  var hero = canvas && canvas.parentElement;
  if (!canvas || !hero) return;
  var gl = canvas.getContext('webgl2', { alpha: true, premultipliedAlpha: true, antialias: false });
  if (!gl) return;                                   // no WebGL2: the hero keeps its plain gradient

  var OPT = { levels: 16, speed: 1, scale: 1, peak: 1, stiffness: 120, damping: 20 };
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');

  var VS = '#version 300 es\nin vec2 a;void main(){gl_Position=vec4(a,0.,1.);}';
  var FS = '#version 300 es\nprecision highp float;\n' +
    'uniform vec2 uRes;uniform float uDpr,uTime,uRise,uLevels,uScale,uPeak,uAlpha;uniform vec2 uPtr;uniform vec3 uColor;' +
    'uniform vec4 uWave[4];out vec4 o;\n' +
    'vec3 h3(vec3 p){p=vec3(dot(p,vec3(127.1,311.7,74.7)),dot(p,vec3(269.5,183.3,246.1)),dot(p,vec3(113.5,271.9,124.6)));return -1.+2.*fract(sin(p)*43758.5453123);}\n' +
    'float gn(vec3 p){vec3 i=floor(p),f=fract(p),u=f*f*f*(f*(f*6.-15.)+10.);' +
    'return mix(mix(mix(dot(h3(i),f),dot(h3(i+vec3(1,0,0)),f-vec3(1,0,0)),u.x),mix(dot(h3(i+vec3(0,1,0)),f-vec3(0,1,0)),dot(h3(i+vec3(1,1,0)),f-vec3(1,1,0)),u.x),u.y),' +
    'mix(mix(dot(h3(i+vec3(0,0,1)),f-vec3(0,0,1)),dot(h3(i+vec3(1,0,1)),f-vec3(1,0,1)),u.x),mix(dot(h3(i+vec3(0,1,1)),f-vec3(0,1,1)),dot(h3(i+vec3(1,1,1)),f-vec3(1,1,1)),u.x),u.y),u.z);}\n' +
    'void main(){\n' +
    '  vec2 cp=gl_FragCoord.xy/uDpr; float unit=clamp(uRes.x/uDpr*.36,200.,430.)*uScale; vec2 p=cp/unit;\n' +
    '  float n=gn(vec3(p,uTime))*.68+gn(vec3(p*2.13+7.3,uTime*1.31))*.32;\n' +
    '  float h=.5+n*.95;\n' +
    '  vec2 d=(cp-uPtr)/unit; h+=uPeak*.42*exp(-dot(d,d)*2.2);\n' +                          // the peak under the cursor
    '  for(int i=0;i<4;i++){ if(uWave[i].w>0.){ float r=length(cp-uWave[i].xy)/unit, x=r-uWave[i].z*.9, age=uWave[i].z;\n' +  // waves: crest + trough
    '    h+=uWave[i].w*.2*sin(x*9.)*exp(-x*x*5.)*(1.-smoothstep(.55,1.,age)); } }\n' +
    '  h=.5+(h-.5)*uRise;\n' +                                                                // bloom out from the middle level
    '  float v=h*uLevels, w=fwidth(v);\n' +
    '  float k=floor(v+.5), dist=abs(v-k)/max(w,1e-4);\n' +
    '  float idx=step(mod(k,5.),.5);\n' +
    '  float half_=mix(.7,1.25,idx), line=1.-smoothstep(half_-.6,half_+.6,dist);\n' +
    '  float a=line*mix(.55,1.,idx)*uAlpha;\n' +
    '  vec2 q=(gl_FragCoord.xy/uRes-.5)*vec2(uRes.x/uRes.y,1.);\n' +
    '  a*=mix(.42,1.,smoothstep(.12,.55,length(q*vec2(.8,1.1))));\n' +                       // calmer behind the headline
    '  o=vec4(uColor*a,a);\n}';

  function sh(type, src) {
    var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s);
    if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) { console.warn(gl.getShaderInfoLog(s)); return null; }
    return s;
  }
  var vs = sh(gl.VERTEX_SHADER, VS), fs = sh(gl.FRAGMENT_SHADER, FS);
  if (!vs || !fs) return;
  var prog = gl.createProgram(); gl.attachShader(prog, vs); gl.attachShader(prog, fs); gl.linkProgram(prog);
  if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) return;
  gl.useProgram(prog);
  var buf = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, buf);
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
  var loc = gl.getAttribLocation(prog, 'a'); gl.enableVertexAttribArray(loc); gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
  var U = {}; ['uRes', 'uDpr', 'uTime', 'uRise', 'uLevels', 'uScale', 'uPeak', 'uAlpha', 'uPtr', 'uColor', 'uWave'].forEach(function (n) { U[n] = gl.getUniformLocation(prog, n); });

  // line colour: the canvas's text colour
  var m = (getComputedStyle(canvas).color.match(/[\d.]+/g) || [11, 11, 11]).map(Number);
  gl.uniform3f(U.uColor, m[0] / 255, m[1] / 255, m[2] / 255);
  gl.uniform1f(U.uLevels, OPT.levels); gl.uniform1f(U.uScale, OPT.scale);
  gl.uniform1f(U.uAlpha, parseFloat(getComputedStyle(canvas).getPropertyValue('--topo-alpha')) || 0.3);

  var dpr = 1, W = 0, H = 0;
  function resize() {
    dpr = Math.min(window.devicePixelRatio || 1, window.innerWidth < 640 ? 1.5 : 2);
    var w = Math.max(1, Math.round(canvas.clientWidth * dpr)), h = Math.max(1, Math.round(canvas.clientHeight * dpr));
    if (w === canvas.width && h === canvas.height) return;
    canvas.width = w; canvas.height = h; W = w; H = h; gl.viewport(0, 0, w, h);
    gl.uniform2f(U.uRes, w, h); gl.uniform1f(U.uDpr, dpr);
    if (still) draw(0);
  }

  // ---- state: rise, peak spring, waves
  var t0 = 0, started = false, still = reduce.matches;
  var ptr = { x: 0, y: 0, vx: 0, vy: 0, tx: 0, ty: 0, on: false, s: 0, sv: 0 };
  var waves = [];                                     // {x, y, born}
  function ease(p) { return 1 - Math.pow(1 - p, 3); }

  function rel(e) { var r = canvas.getBoundingClientRect(); return { x: e.clientX - r.left, y: r.height - (e.clientY - r.top) }; }
  if (!still) {
    hero.addEventListener('pointermove', function (e) {
      var p = rel(e); ptr.tx = p.x; ptr.ty = p.y;
      if (!ptr.on) { ptr.on = true; if (ptr.s < .02) { ptr.x = p.x; ptr.y = p.y; } }
    }, { passive: true });
    hero.addEventListener('pointerleave', function () { ptr.on = false; });
    hero.addEventListener('pointerup', function (e) { if (e.pointerType !== 'mouse') ptr.on = false; });
    hero.addEventListener('pointerdown', function (e) {
      var p = rel(e); waves.push({ x: p.x, y: p.y, born: performance.now() }); if (waves.length > 4) waves.shift();
      if (e.pointerType !== 'mouse') { ptr.tx = p.x; ptr.ty = p.y; ptr.on = true; if (ptr.s < .02) { ptr.x = p.x; ptr.y = p.y; } }
    }, { passive: true });
  }

  function spring(x, v, target, dt) {                 // damped spring, semi-implicit Euler, mass 1
    var a = OPT.stiffness * (target - x) - OPT.damping * v;
    v += a * dt; return [x + v * dt, v];
  }

  var last = 0;
  function draw(now) {
    var tms = now - t0, rise = still ? 1 : ease(Math.min(1, Math.max(0, tms / 1800)));
    gl.uniform1f(U.uTime, still ? 3.1 : (tms / 1000) * 0.07 * OPT.speed + 3.1);
    gl.uniform1f(U.uRise, rise);
    gl.uniform1f(U.uPeak, OPT.peak * ptr.s);
    gl.uniform2f(U.uPtr, ptr.x, ptr.y);
    var arr = new Float32Array(16);
    waves = waves.filter(function (w) { return now - w.born < 2600; });
    waves.forEach(function (w, i) { var age = (now - w.born) / 2600; arr[i * 4] = w.x; arr[i * 4 + 1] = w.y; arr[i * 4 + 2] = age; arr[i * 4 + 3] = 1; });
    gl.uniform4fv(U.uWave, arr);
    gl.drawArrays(gl.TRIANGLES, 0, 3);
  }

  var raf = 0, visible = true;
  function frame(now) {
    raf = 0;
    if (!visible || document.hidden) return;
    var dt = Math.min(.033, (now - (last || now)) / 1000); last = now;
    var r;
    r = spring(ptr.x, ptr.vx, ptr.tx, dt); ptr.x = r[0]; ptr.vx = r[1];
    r = spring(ptr.y, ptr.vy, ptr.ty, dt); ptr.y = r[0]; ptr.vy = r[1];
    r = spring(ptr.s, ptr.sv, ptr.on ? 1 : 0, dt); ptr.s = r[0]; ptr.sv = r[1];
    draw(now);
    raf = requestAnimationFrame(frame);
  }
  function kick() { if (!raf && !still && started) { last = 0; raf = requestAnimationFrame(frame); } }

  function begin() {
    if (started) return;
    started = true; t0 = performance.now(); resize();
    canvas.classList.add('is-on');
    if (still) draw(t0); else kick();
  }
  // the relief starts rising once the loading screen has gone
  var root = document.documentElement;
  if (root.classList.contains('is-ready') || !document.getElementById('cdLoad')) begin();
  else new MutationObserver(function (_, mo) { if (root.classList.contains('is-ready')) { mo.disconnect(); begin(); } }).observe(root, { attributes: true, attributeFilter: ['class'] });

  if ('ResizeObserver' in window) new ResizeObserver(resize).observe(canvas); else window.addEventListener('resize', resize);
  if ('IntersectionObserver' in window) new IntersectionObserver(function (en) { visible = en[0].isIntersecting; if (visible) kick(); }, { threshold: 0 }).observe(hero);
  document.addEventListener('visibilitychange', kick);
  reduce.addEventListener && reduce.addEventListener('change', function () { still = reduce.matches; if (still) { cancelAnimationFrame(raf); raf = 0; draw(performance.now()); } else kick(); });
  resize();
})();
