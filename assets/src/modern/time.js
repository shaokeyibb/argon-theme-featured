// Pure formatter shared by initial rendering and periodic comment refreshes.
export function humanTime(timestamp, { now = Date.now(), locale = 'zh-CN', dateFormat = 'YMD', justNow = '刚刚' } = {}) {
  const date = new Date(timestamp * 1000);
  if (!Number.isFinite(date.getTime())) return '';
  const seconds = Math.max(0, (now - date.getTime()) / 1000);
  if (seconds < 60) return justNow;
  if (seconds < 2592000) {
    const current = new Date(now);
    const days = (Date.UTC(current.getFullYear(), current.getMonth(), current.getDate()) - Date.UTC(date.getFullYear(), date.getMonth(), date.getDate())) / 86400000;
    if (seconds >= 86400 && (days === 1 || (days === 2 && locale.startsWith('zh')))) {
      return `${new Intl.RelativeTimeFormat(locale, { numeric: 'auto' }).format(-days, 'day')} ${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')}`;
    }
    const [divisor, unit] = seconds < 3600 ? [60, 'minute'] : seconds < 86400 ? [3600, 'hour'] : [86400, 'day'];
    return new Intl.RelativeTimeFormat(locale, { numeric: 'auto' }).format(-Math.floor(seconds / divisor), unit);
  }
  const parts = { Y: date.getFullYear(), M: String(date.getMonth() + 1).padStart(2, '0'), D: String(date.getDate()).padStart(2, '0') };
  const order = ['YMD', 'MDY', 'DMY'].includes(dateFormat) ? dateFormat : 'YMD';
  return [...order].filter(part => part !== 'Y' || date.getFullYear() !== new Date(now).getFullYear()).map(part => parts[part]).join('-');
}
