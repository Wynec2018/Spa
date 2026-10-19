// Interacciones progresivas. Sin secretos ni SQL.
// CP-LAND-06: sombra del header al hacer scroll (sin librerías).
(function () {
  'use strict';
  var header = document.querySelector('.site-header');
  if (!header) return;
  var ticking = false;
  function update() {
    header.classList.toggle('is-scrolled', window.scrollY > 8);
    ticking = false;
  }
  window.addEventListener('scroll', function () {
    if (!ticking) {
      window.requestAnimationFrame(update);
      ticking = true;
    }
  }, { passive: true });
})();
