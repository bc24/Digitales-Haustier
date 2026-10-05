// Brutkasten-Countdowns
(function () {
  function fmt(s) {
    if (s <= 0) return 'bereit';
    var h = Math.floor(s / 3600), m = Math.floor(s % 3600 / 60), x = s % 60;
    return (h ? h + ' Std. ' : '') + (h || m ? m + ' Min. ' : '') + x + ' Sek.';
  }
  var els = document.querySelectorAll('[data-countdown]');
  if (!els.length) return;
  var end = [];
  els.forEach(function (el) { end.push(Date.now() + parseInt(el.dataset.countdown, 10) * 1000); });
  function tick() {
    els.forEach(function (el, i) {
      var s = Math.max(0, Math.round((end[i] - Date.now()) / 1000));
      el.textContent = fmt(s);
      if (s === 0 && !el.dataset.reloaded && el.dataset.reload) { el.dataset.reloaded = 1; location.reload(); }
    });
  }
  tick(); setInterval(tick, 1000);
})();
