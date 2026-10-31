import Collapse from 'bootstrap/js/dist/collapse';
import Dropdown from 'bootstrap/js/dist/dropdown';
import { $, __ } from './dom.js';

// Keep the desktop navigation and search in place. Only the trailing entries
// that do not fit move to More; mobile continues using the original collapse.
export function initNavbar() {
  const nav = $('#navbar-main'), row = nav?.querySelector(':scope > .container');
  const menu = $('#navbar_global', nav || document);
  if (!row || !menu) return;
  const desktop = matchMedia('(min-width: 900px)');
  const search = $('#navbar_search_input', nav), mobileSearch = $('#navbar_search_input_mobile', nav);
  const toggle = $(':scope > button[data-target="#navbar_global"]', row);
  const focusOptions = { preventScroll: true }, decorated = new WeakSet();
  const options = { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['class', 'src', 'srcset', 'sizes'] };
  let list, more, button, panel, dropdown, measure, measuredList, frame;
  let dirty = true, wasDesktop = desktop.matches, resizeFocus;
  const outerWidth = el => {
    const style = getComputedStyle(el);
    return el.getBoundingClientRect().width + (parseFloat(style.marginLeft) || 0) + (parseFloat(style.marginRight) || 0);
  };
  const update = () => {
    observer.disconnect();
    const changed = wasDesktop !== desktop.matches;
    const active = changed && document.activeElement === document.body && resizeFocus ? resizeFocus : document.activeElement;
    resizeFocus = null;
    const main = $(':scope > .navbar-nav:not(.navbar-search)', menu);
    if (main !== list) {
      dropdown?.dispose(); more?.remove();
      list = main; more = null; dirty = true;
    }
    if (list && !more) {
      more = document.createElement('li');
      more.id = 'navbar_more'; more.className = 'nav-item dropdown navbar-more';
      more.innerHTML = '<button id="navbar_more_toggle" class="nav-link" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><span class="nav-link-inner--text"></span></button><ul id="navbar_more_menu" class="dropdown-menu dropdown-menu-end navbar-more-menu"></ul>';
      button = $('button', more); $('span', button).textContent = __('更多');
      panel = $('ul', more); list.append(more);
      dropdown = Dropdown.getOrCreateInstance(button);
    }
    const items = list ? [...list.children].filter(el => el !== more).concat([...panel.children]) : [];
    const firstOverflow = panel?.querySelector('.nav-link');
    if (changed && desktop.matches && active === mobileSearch && search) {
      search.value = mobileSearch.value;
      search.closest('#navbar_search_input_container').classList.add('open');
      dirty = true;
    }
    if (list && dirty) {
      if (measure) { resize.unobserve(measure); measure.remove(); }
      measure = row.cloneNode(true);
      measure.className = 'navbar navbar-expand-lg navbar-measure';
      measure.inert = true; measure.ariaHidden = 'true';
      measuredList = $('.navbar-collapse > .navbar-nav:not(.navbar-search)', measure);
      const copies = items.map(item => {
        const copy = item.cloneNode(true), link = $(':scope > .nav-link', item);
        if (decorated.has(link)) $(':scope > .nav-link', copy)?.classList.remove('dropdown-item');
        return copy;
      });
      const moreCopy = more.cloneNode(true); moreCopy.hidden = false;
      measuredList.replaceChildren(...copies, moreCopy);
      measure.querySelectorAll('.navbar-toggler, .navbar-collapse-header, #navbar_menu_mask, .dropdown-menu, .d-lg-none').forEach(el => el.remove());
      measure.querySelectorAll('[id]').forEach(el => el.removeAttribute('id'));
      document.body.append(measure); resize.observe(measure); dirty = false;
    }
    let count = items.length;
    if (list && desktop.matches) {
      // Reserve the opening target immediately; release space as the original
      // search field finishes its closing transition, without changing it.
      const input = $('.navbar-search input', measure);
      if (input && search) {
        const open = search.closest('#navbar_search_input_container').classList.contains('open');
        input.style.setProperty('width', `${Math.max(open ? 200 : 0, search.getBoundingClientRect().width)}px`, 'important');
        if (!open) input.style.setProperty('padding', getComputedStyle(search).padding, 'important');
      }
      const widths = [...measuredList.children].map(outerWidth), moreWidth = widths.pop();
      const sum = widths.reduce((total, width) => total + width, 0);
      const style = getComputedStyle(row);
      const available = row.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
      const fixed = measure.getBoundingClientRect().width - sum - moreWidth;
      if (fixed + sum > available + 1) {
        count = 0; let used = fixed + moreWidth;
        while (count < widths.length && used + widths[count] <= available + 1) used += widths[count++];
      }
    }
    let movedFocus = false;
    items.forEach((item, index) => {
      const overflow = index >= count, parent = overflow ? panel : list;
      const position = parent.children[overflow ? index - count : index] || (overflow ? null : more);
      if (position !== item) {
        if (item.contains(active)) movedFocus = true;
        parent.insertBefore(item, position);
      }
      const link = $(':scope > .nav-link', item);
      if (overflow && link && !link.classList.contains('dropdown-item')) { link.classList.add('dropdown-item'); decorated.add(link); }
      if (!overflow && decorated.has(link)) { link.classList.remove('dropdown-item'); decorated.delete(link); }
    });
    if (more) {
      more.hidden = count === items.length;
      if (more.hidden) dropdown.hide();
    }
    observer.observe(row, options);
    if (!desktop.matches && (changed || movedFocus)) {
      let target = active;
      if (active === search && mobileSearch) { mobileSearch.value = search.value; target = mobileSearch; }
      if (active === button) target = firstOverflow || $('.navbar-title, .custom-logo-link', nav);
      if (target && menu.contains(target)) {
        const focus = () => { if (!desktop.matches) target.focus(focusOptions); };
        if (menu.classList.contains('show')) focus();
        else { menu.addEventListener('shown.bs.collapse', focus, { once: true }); Collapse.getOrCreateInstance(menu, { toggle: false }).show(); }
      }
    } else if (desktop.matches) {
      if (changed && active === mobileSearch) search?.focus(focusOptions);
      else if (active === button && more?.hidden) (firstOverflow || $('.navbar-title, .custom-logo-link', nav))?.focus(focusOptions);
      else if (movedFocus) { if (panel.contains(active)) dropdown.show(); active.focus(focusOptions); }
      else if (changed && active === toggle) $('.navbar-title, .custom-logo-link', nav)?.focus(focusOptions);
    }
    wasDesktop = desktop.matches;
  };
  const schedule = () => { cancelAnimationFrame(frame); frame = requestAnimationFrame(update); };
  const resize = new ResizeObserver(schedule);
  const observer = new MutationObserver(() => { dirty = true; schedule(); });
  resize.observe(row);
  if (search) resize.observe(search);
  desktop.addEventListener('change', schedule);
  document.fonts.addEventListener('loadingdone', schedule);
  nav.addEventListener('focusout', event => {
    if (!event.relatedTarget && desktop.matches !== wasDesktop && [search, mobileSearch].includes(event.target)) resizeFocus = event.target;
  });
  // Run before Bootstrap’s delegated document handlers so Escape closes only
  // the innermost dropdown, then More on the next press.
  window.addEventListener('keydown', event => {
    if (event.key !== 'Escape' || !nav.contains(event.target)) return;
    if (desktop.matches && more?.contains(event.target) && panel.classList.contains('show')) {
      event.preventDefault(); event.stopPropagation();
      const nested = $('.dropdown-menu.show', panel);
      const trigger = nested?.parentElement.querySelector(':scope > [data-bs-toggle="dropdown"]');
      if (trigger) { Dropdown.getOrCreateInstance(trigger).hide(); trigger.focus(focusOptions); }
      else { dropdown.hide(); button.focus(focusOptions); }
    } else if (!desktop.matches && menu.classList.contains('show') && !$('.dropdown-menu.show', menu)) {
      Collapse.getOrCreateInstance(menu, { toggle: false }).hide(); toggle?.focus(focusOptions);
    }
  }, true);
  nav.addEventListener('click', event => {
    const link = event.target.closest('a');
    if (panel?.contains(link) && !link.hasAttribute('data-bs-toggle')) dropdown.hide();
  });
  update();
}
