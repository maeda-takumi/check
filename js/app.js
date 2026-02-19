document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.querySelector('.menu-toggle');
  const menu = document.querySelector('.fab-menu');

  if (!toggle || !menu) {
    return;
  }

  toggle.addEventListener('click', () => {
    menu.classList.toggle('open');
    const expanded = menu.classList.contains('open');
    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    menu.setAttribute('aria-hidden', expanded ? 'false' : 'true');
  });
});
