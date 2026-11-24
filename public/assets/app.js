// Confirmation dialogs for dangerous actions (no inline JS, so a strict CSP can be used).
document.addEventListener('submit', function (ev) {
  var msg = ev.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) { ev.preventDefault(); }
});
// Client-side hints only. The server re-validates everything.
document.addEventListener('DOMContentLoaded', function () {
  var d = document.querySelector('input[type=datetime-local][data-future]');
  if (d) {
    var n = new Date(Date.now() - new Date().getTimezoneOffset() * 60000);
    d.min = n.toISOString().slice(0, 16);
  }
});
