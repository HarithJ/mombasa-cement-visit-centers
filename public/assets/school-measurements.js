(() => {
  function sync() {
    document.querySelectorAll('form:has(#school-support)').forEach(form => {
      const support = form.querySelector('#school-support').value;
      form.querySelectorAll('[data-measurement-type]').forEach(section => {
        const active = support === 'both' || support === section.dataset.measurementType;
        section.hidden = !active;
        section.disabled = !active;
        section.querySelectorAll('input').forEach(input => { input.required = active; });
      });
    });
  }
  document.addEventListener('change', event => { if (event.target.id === 'school-support') sync(); });
  new MutationObserver(sync).observe(document.body, {childList: true, subtree: true});
  sync();
})();
