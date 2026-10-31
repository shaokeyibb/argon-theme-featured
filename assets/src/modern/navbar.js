import Collapse from 'bootstrap/js/dist/collapse';
import { $ } from './dom.js';

// Menu labels, reader fonts and logos can outgrow the desktop breakpoint.
// An inert, ID-free copy measures desktop content with search expanded. Keeping
// it outside the toolbar avoids changing its focus, transitions or open menus.
export function initNavbar() {
  const nav = $('#navbar-main');
  const row = nav?.querySelector(':scope > .container');
  const menu = nav?.querySelector('#navbar_global');
  if (!row || !menu) return;
  const desktop = matchMedia('(min-width: 900px)');
  const search = $('#navbar_search_input', nav);
  const mobileSearch = $('#navbar_search_input_mobile', nav);
  const toggle = $(':scope > button[data-target="#navbar_global"]', row);
  const focusOptions = { preventScroll: true };
  let collapsed;
  let measure, frame;
  let dirty = true;
  const update = () => {
    const active = document.activeElement;
    if (dirty) {
      if (measure) { resize.unobserve(measure); measure.remove(); }
      measure = row.cloneNode(true);
      measure.className = 'navbar navbar-expand-lg navbar-measure';
      measure.inert = true;
      measure.ariaHidden = 'true';
      measure.querySelectorAll('.navbar-toggler, .navbar-collapse-header, #navbar_menu_mask, .dropdown-menu, .d-lg-none').forEach(el => el.remove());
      measure.querySelectorAll('[id]').forEach(el => el.removeAttribute('id'));
      document.body.append(measure);
      resize.observe(measure);
      dirty = false;
    }
    const style = getComputedStyle(row);
    const available = row.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
    const compact = desktop.matches && measure.getBoundingClientRect().width > available + 1;
    nav.classList.toggle('navbar-compact', compact);
    const nextCollapsed = compact || !desktop.matches;
    if (nextCollapsed !== collapsed) {
      if (nextCollapsed && menu.contains(active)) {
        const target = active === search && mobileSearch ? mobileSearch : active;
        if (target === mobileSearch) target.value = search.value;
        const focus = () => target.focus(focusOptions);
        if (menu.classList.contains('show')) focus();
        else {
          menu.addEventListener('shown.bs.collapse', focus, { once: true });
          Collapse.getOrCreateInstance(menu, { toggle: false }).show();
        }
      } else if (!nextCollapsed && active === mobileSearch && search) {
        search.value = mobileSearch.value;
        search.closest('#navbar_search_input_container').classList.add('open');
        search.focus(focusOptions);
      } else if (!nextCollapsed && active === toggle) {
        $('.navbar-title, .custom-logo-link', nav)?.focus(focusOptions);
      }
      collapsed = nextCollapsed;
    }
  };
  const schedule = () => { cancelAnimationFrame(frame); frame = requestAnimationFrame(update); };
  const resize = new ResizeObserver(schedule);
  resize.observe(row);
  new MutationObserver(() => { dirty = true; schedule(); }).observe(row, { childList: true, subtree: true, characterData: true });
  desktop.addEventListener('change', schedule);
  document.fonts.addEventListener('loadingdone', schedule);
  nav.addEventListener('keydown', event => {
    if (event.key === 'Escape' && collapsed && menu.classList.contains('show')) {
      // Let Bootstrap close an open submenu first and restore its own focus.
      if (menu.querySelector('.dropdown-menu.show')) return;
      Collapse.getOrCreateInstance(menu, { toggle: false }).hide();
      toggle?.focus(focusOptions);
    }
  });
  update();
}
