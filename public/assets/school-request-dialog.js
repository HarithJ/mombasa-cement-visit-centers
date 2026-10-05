const schoolDialog = document.querySelector('#school-request-dialog');
const schoolContent = schoolDialog.querySelector('[data-school-content]');
let schoolOpener;
let schoolBusy = false;
let schoolLoaded = false;
let schoolUrl = '/school-request';
async function loadSchoolForm(url, options = {}) {
  if (schoolBusy) return;
  schoolBusy = true;
  schoolContent.setAttribute('aria-busy', 'true');
  const buttons = [...schoolContent.querySelectorAll('button')];
  buttons.forEach(button => { button.disabled = true; });
  try {
    const response = await fetch(url, {...options, credentials: 'same-origin'});
    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    const main = page.querySelector('main');
    if (!main) throw new Error('Missing form');
    schoolContent.replaceChildren(...main.childNodes);
    schoolUrl = response.url;
    schoolLoaded = true;
    schoolContent.scrollTop = 0;
    const heading = schoolContent.querySelector('h1');
    if (heading && schoolDialog.open) { heading.tabIndex = -1; heading.focus(); }
  } catch {
    let error = schoolContent.querySelector('[data-load-error]');
    if (!error) {
      error = document.createElement('p');
      error.dataset.loadError = '';
      error.setAttribute('role', 'alert');
      schoolContent.prepend(error);
    }
    error.textContent = 'We could not load your request. Please close and reopen this form, or try submitting again.';
  } finally {
    schoolBusy = false;
    schoolContent.removeAttribute('aria-busy');
    buttons.forEach(button => { button.disabled = false; });
  }
}
document.querySelectorAll('a[href="/school-request"]').forEach(link => {
  link.setAttribute('aria-haspopup', 'dialog');
  link.addEventListener('click', event => {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    schoolOpener = link;
    schoolDialog.showModal();
    document.body.style.overflow = 'hidden';
    if (!schoolLoaded) { schoolContent.textContent = 'Loading school request…'; loadSchoolForm('/school-request'); }
  });
});
schoolContent.addEventListener('submit', event => {
  event.preventDefault();
  const form = event.target;
  loadSchoolForm(form.getAttribute('action') || schoolUrl, {method: 'POST', body: new FormData(form)});
});
schoolContent.addEventListener('click', event => {
  if (event.target.closest('a[href="/#school-projects"]')) { event.preventDefault(); schoolDialog.close(); }
});
schoolDialog.querySelector('[data-school-close]').addEventListener('click', () => schoolDialog.close());
schoolDialog.addEventListener('click', event => {
  if (event.target !== schoolDialog) return;
  const box = schoolDialog.getBoundingClientRect();
  if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) schoolDialog.close();
});
schoolDialog.addEventListener('close', () => { document.body.style.overflow = ''; schoolOpener?.focus({preventScroll: true}); });
