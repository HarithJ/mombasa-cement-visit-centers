'use strict';
// Mosaic design: school section layout preview, the 340+ count-up, and tiles that pre-select the request type.
const schools = document.querySelector('.mo-schools');
if (schools) {
  const options = schools.querySelectorAll('[data-layout-option]');
  const setLayout = layout => {
    schools.dataset.layout = layout;
    options.forEach(option => option.setAttribute('aria-pressed', String(option.dataset.layoutOption === layout)));
    try { localStorage.setItem('mosaic-school-layout', layout); } catch {}
  };
  options.forEach(option => option.addEventListener('click', () => setLayout(option.dataset.layoutOption)));
  try { const saved = localStorage.getItem('mosaic-school-layout'); if (saved && schools.querySelector(`[data-layout-option="${saved}"]`)) setLayout(saved); } catch {}

  const count = schools.querySelector('.mo-count');
  if (count && 'IntersectionObserver' in window && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const target = Number(count.dataset.count);
    count.textContent = '0';
    const observer = new IntersectionObserver(entries => {
      if (!entries.some(entry => entry.isIntersecting)) return;
      observer.disconnect();
      const start = performance.now();
      const step = now => {
        const progress = Math.min((now - start) / 1600, 1);
        count.textContent = String(Math.round(target * (1 - Math.pow(1 - progress, 3))));
        if (progress < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    }, { threshold: .4 });
    observer.observe(count);
  }

  // Tiles open the usual request dialog with classroom or wall already chosen.
  const opener = schools.querySelector('.mo-schools-cta');
  const content = document.querySelector('#school-request-dialog [data-school-content]');
  let pending = null;
  const applySupport = () => {
    const select = content?.querySelector('#school-support');
    if (!pending || !select) return;
    select.value = pending;
    select.dispatchEvent(new Event('change', { bubbles: true }));
    pending = null;
  };
  if (content) new MutationObserver(applySupport).observe(content, { childList: true, subtree: true });
  schools.querySelectorAll('[data-school-support]').forEach(link => link.addEventListener('click', event => {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || !opener || !content) return;
    event.preventDefault();
    pending = link.dataset.schoolSupport;
    opener.click();
    applySupport();
  }));
}
