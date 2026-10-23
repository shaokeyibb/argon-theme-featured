export const reducedMotion = () => matchMedia('(prefers-reduced-motion: reduce)').matches;

const disclosures = new WeakMap();
const dimensions = ['height', 'padding-top', 'padding-bottom', 'margin-top', 'margin-bottom', 'overflow', 'box-sizing'];

// Animate real layout height, then return to auto sizing. Reversing an in-flight
// toggle starts at its current frame rather than queuing or jumping to an end.
export function slide(element, open, { duration = 300, immediate = false, fromHeight } = {}) {
  if (!element) return Promise.resolve();
  let state = disclosures.get(element);
  if (state?.open === open) return state.finished;
  const style = getComputedStyle(element);
  const visible = style.display !== 'none';
  const from = {
    height: `${fromHeight ?? (visible ? element.getBoundingClientRect().height : 0)}px`,
    paddingTop: visible ? style.paddingTop : '0px', paddingBottom: visible ? style.paddingBottom : '0px',
    marginTop: visible ? style.marginTop : '0px', marginBottom: visible ? style.marginBottom : '0px'
  };
  const original = state?.original ?? Object.fromEntries(dimensions.map(name => [name, [element.style.getPropertyValue(name), element.style.getPropertyPriority(name)]]));
  const display = state?.display ?? (element.style.display === 'none' ? '' : element.style.display);
  state?.animation?.cancel();
  const restore = () => Object.entries(original).forEach(([name, [value, priority]]) => value ? element.style.setProperty(name, value, priority) : element.style.removeProperty(name));
  restore();
  element.hidden = false; element.style.display = display;
  if (getComputedStyle(element).display === 'none') element.style.display = 'block';
  const natural = getComputedStyle(element);
  const to = {
    height: open ? `${element.getBoundingClientRect().height}px` : '0px',
    paddingTop: open ? natural.paddingTop : '0px', paddingBottom: open ? natural.paddingBottom : '0px',
    marginTop: open ? natural.marginTop : '0px', marginBottom: open ? natural.marginBottom : '0px'
  };
  element.inert = !open;
  element.setAttribute('aria-hidden', String(!open));
  state = { open, original, display, finished: Promise.resolve() };
  disclosures.set(element, state);
  const finish = () => {
    if (disclosures.get(element) !== state) return;
    restore();
    element.hidden = !open;
    if (!open) element.style.display = 'none';
    state.animation?.cancel(); state.animation = null;
  };
  if (immediate || reducedMotion() || from.height === to.height || !element.animate) {
    finish(); return state.finished;
  }
  element.style.overflow = 'hidden'; element.style.boxSizing = 'border-box';
  state.animation = element.animate([from, to], { duration, easing: 'cubic-bezier(.075,.82,.165,1)', fill: 'both' });
  state.finished = state.animation.finished.then(finish, () => {});
  return state.finished;
}

export function fadeIn(element, duration = 300) {
  if (!element || reducedMotion() || !element.animate) return;
  element.animate([{ opacity: 0 }, { opacity: 1 }], { duration, easing: 'ease-out' });
}

export async function fadeOut(element, duration = 180) {
  if (!element || reducedMotion() || !element.animate) return;
  await element.animate([{ opacity: getComputedStyle(element).opacity }, { opacity: 0 }], { duration, easing: 'ease-in', fill: 'forwards' }).finished.catch(() => {});
}
