'use strict';
// Switches the design preview in place so open forms keep their input; the links still work without JavaScript.
document.querySelectorAll('[data-design-option]').forEach(link => link.addEventListener('click', event => {
  event.preventDefault();
  const design = link.dataset.designOption;
  document.documentElement.dataset.design = design;
  document.cookie = `design=${design}; path=/; max-age=31536000; samesite=lax`;
  document.querySelectorAll('[data-design-option]').forEach(option => option.setAttribute('aria-current', String(option === link)));
  const url = new URL(location.href);
  if (url.searchParams.has('design')) { url.searchParams.delete('design'); history.replaceState(null, '', url); }
}));
