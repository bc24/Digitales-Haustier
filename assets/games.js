(function () {
  var cfg = window.GAME, box = document.getElementById('g-box'), over = document.getElementById('g-over');
  var scoreEl = document.getElementById('g-score'), extra = document.getElementById('g-extra');
  var token = null, running = false;

  function api(body) {
    return fetch(cfg.api, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': cfg.csrf }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || 'Fehler'); return j; }); });
  }
  function showOver(html) { over.innerHTML = html; over.style.display = 'grid'; box.appendChild(over); }
  function finish(payload) {
    running = false;
    showOver('<h2 style="margin:0">Geschafft!</h2><p class="muted">Ergebnis wird geprüft...</p>');
    payload.do = 'finish'; payload.token = token;
    api(payload).then(function (r) {
      showOver('<h2 style="margin:0">' + r.score + ' Punkte' + (r.newBest ? ' – neuer Wochenbestwert!' : '') + '</h2>' +
        '<p>' + (r.coins ? '+' + r.coins + ' Münzen' : (r.capped ? 'Tageslimit erreicht, keine Münzen mehr' : 'Keine Münzen')) + '</p>' +
        '<button class="btn big" id="g-again">Nochmal</button> <a class="btn ghost" href="games.php">Übersicht</a>');
      document.getElementById('g-again').onclick = begin;
    }).catch(function (e) {
      showOver('<h2 style="margin:0">Ups</h2><p class="muted">' + e.message + '</p><button class="btn" id="g-again">Nochmal</button>');
      document.getElementById('g-again').onclick = begin;
    });
  }
  function begin() {
    api({ do: 'start', game: cfg.game }).then(function (r) {
      token = r.token; over.style.display = 'none'; running = true;
      Array.prototype.slice.call(box.children).forEach(function (c) { if (c !== over) box.removeChild(c); });
      scoreEl.textContent = '0'; extra.textContent = '';
      ({ catch: gCatch, memory: gMemory, whack: gWhack })[cfg.game]();
    }).catch(function (e) { showOver('<h2 style="margin:0">Pause</h2><p class="muted">' + e.message + '</p>'); });
  }
  document.getElementById('g-start').onclick = begin;

  // ---- Leckerli-Fänger ----
  function gCatch() {
    var W = box.clientWidth, H = box.clientHeight, score = 0, bx = W / 2, items = [], t0 = performance.now(), last = t0, spawn = 0, DUR = 30000, done = false;
    var basket = document.createElement('div'); basket.className = 'basket'; basket.textContent = '🧺'; box.appendChild(basket);
    function move(e) { var r = box.getBoundingClientRect(), x = (e.touches ? e.touches[0].clientX : e.clientX) - r.left; bx = Math.max(30, Math.min(W - 30, x)); }
    box.addEventListener('mousemove', move); box.addEventListener('touchmove', function (e) { move(e); e.preventDefault(); }, { passive: false }); box.addEventListener('touchstart', move);
    function frame(now) {
      if (done) return;
      var dt = (now - last) / 1000; last = now; spawn -= dt;
      var left = DUR - (now - t0); extra.textContent = 'Zeit: ' + Math.max(0, Math.ceil(left / 1000));
      if (spawn <= 0) {
        spawn = 0.55 + Math.random() * 0.25; var r = Math.random();
        var kind = r < 0.1 ? { e: '⭐', p: 3 } : r < 0.25 ? { e: '🪨', p: -2 } : { e: ['🍖', '🍪', '🍎', '🥕'][Math.floor(Math.random() * 4)], p: 1 };
        var el = document.createElement('div'); el.className = 'fall'; el.textContent = kind.e; var x = 20 + Math.random() * (W - 60); el.style.left = x + 'px';
        box.appendChild(el); items.push({ el: el, x: x + 14, y: -30, v: 140 + Math.random() * 110, p: kind.p });
      }
      items = items.filter(function (it) {
        it.y += it.v * dt * (H / 500); it.el.style.top = it.y + 'px';
        if (it.y > H - 70 && it.y < H - 20 && Math.abs(it.x - bx) < 42) { score = Math.max(0, score + it.p); scoreEl.textContent = score; box.removeChild(it.el); return false; }
        if (it.y > H) { box.removeChild(it.el); return false; }
        return true;
      });
      basket.style.left = bx + 'px';
      if (left <= 0) { done = true; finish({ score: score }); return; }
      requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
  }

  // ---- Memory ----
  function gMemory() {
    var cards = cfg.emojis.concat(cfg.emojis).sort(function () { return Math.random() - 0.5; }), open = [], moves = 0, found = 0, lock = false;
    var grid = document.createElement('div'); grid.className = 'memo'; box.appendChild(grid);
    cards.forEach(function (em, i) {
      var b = document.createElement('button'); b.textContent = '?';
      b.onclick = function () {
        if (lock || b.classList.contains('open') || b.classList.contains('done')) return;
        b.classList.add('open'); b.textContent = em; open.push({ b: b, em: em });
        if (open.length === 2) {
          moves++; extra.textContent = 'Züge: ' + moves; lock = true;
          var a = open[0], c = open[1];
          setTimeout(function () {
            if (a.em === c.em) { a.b.className = c.b.className = 'done'; found++; scoreEl.textContent = found * 10; }
            else { a.b.className = c.b.className = ''; a.b.textContent = c.b.textContent = '?'; }
            open = []; lock = false;
            if (found === 8) finish({ moves: moves });
          }, 600);
        }
      };
      grid.appendChild(b);
    });
  }

  // ---- Tier-Tipp ----
  function gWhack() {
    var holes = [], hits = 0, misses = 0, up = -1, t0 = performance.now(), DUR = 30000, done = false, timer;
    var grid = document.createElement('div'); grid.className = 'holes'; box.appendChild(grid);
    for (var i = 0; i < 9; i++) (function (i) {
      var h = document.createElement('div'); h.className = 'hole'; var s = document.createElement('span'); h.appendChild(s); grid.appendChild(h); holes.push({ h: h, s: s });
      h.onpointerdown = function (e) {
        e.preventDefault(); if (done) return;
        if (up === i) { hits++; holes[i].h.classList.remove('up'); up = -1; } else misses++;
        scoreEl.textContent = Math.max(0, hits * 10 - misses * 4);
      };
    })(i);
    function pop() {
      if (done) return;
      var left = DUR - (performance.now() - t0); extra.textContent = 'Zeit: ' + Math.max(0, Math.ceil(left / 1000));
      if (left <= 0) { done = true; if (up >= 0) holes[up].h.classList.remove('up'); finish({ hits: hits, misses: misses }); return; }
      if (up >= 0) holes[up].h.classList.remove('up');
      var n = Math.floor(Math.random() * 9); up = n; holes[n].s.textContent = cfg.emojis[Math.floor(Math.random() * cfg.emojis.length)]; holes[n].h.classList.add('up');
      timer = setTimeout(pop, 550 + Math.random() * 350);
    }
    pop();
  }
})();
