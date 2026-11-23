(() => {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const form = $('check-form');
  const button = $('start-check');
  const message = $('check-message');
  const results = $('scan-result');
  const list = $('check-list');
  const stateNames = {ok: '確認できた項目', attention: '改善候補', unknown: '未確認'};
  let latest = null;
  function node(tag, className, text) {
    const el = document.createElement(tag);
    if (className) el.className = className;
    if (text !== undefined) el.textContent = String(text);
    return el;
  }
  function showMessage(text, isError = false) {
    message.textContent = text;
    message.classList.toggle('error', isError);
  }
  function render(data) {
    latest = data;
    results.hidden = false;
    $('result-domain').textContent = data.url;
    $('result-time').textContent = '検査日時：' + new Date(data.checkedAt).toLocaleString('ja-JP') + '（対象ページの公開HTMLを確認）';
    $('metric-ok').textContent = data.counts.ok;
    $('metric-attention').textContent = data.counts.attention;
    $('metric-unknown').textContent = data.counts.unknown;
    list.replaceChildren();
    const priority = { attention: 0, unknown: 1, ok: 2 };
    const sorted = [...data.checks].sort((a, b) => priority[a.status] - priority[b.status]);
    sorted.forEach((item) => {
      const card = node('article', 'check-item');
      const status = node('span', 'check-chip', stateNames[item.status] || '未確認');
      status.dataset.status = item.status;
      card.append(status, node('h3', '', item.label), node('p', '', item.detail));
      if (item.action && item.status !== 'ok') card.append(node('small', '', '対応の候補：' + item.action));
      list.append(card);
    });
    const limits = $('result-limitations');
    limits.replaceChildren();
    data.limitations.forEach((text) => limits.append(node('li', '', text)));
    results.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
  }
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const url = $('target-url').value.trim();
    if (!url) { showMessage('商品ページのURLを入力してください。', true); return; }
    results.hidden = true;
    latest = null;
    button.disabled = true;
    button.textContent = '診断しています…';
    showMessage('公開ページを確認中です。ログインや購入は行いません。');
    try {
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), 38000);
      let res;
      try {
        res = await fetch('/ai-commerce-check/api.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ url }), credentials: 'same-origin',
          cache: 'no-store', signal: controller.signal
        });
      } finally { clearTimeout(timeout); }
      const payload = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(payload.error || 'HTTP ' + res.status + ': 診断に失敗しました。');
      if (!Array.isArray(payload.checks) || !payload.counts || !payload.url) throw new Error('診断結果の形式を確認できませんでした。');
      render(payload);
      showMessage('診断が完了しました。未確認の項目は本診断で結論を出していません。');
    } catch (error) {
      showMessage(error.name === 'AbortError' ? '応答がありませんでした。時間を空けて再試行してください。' : (error.message || '診断できませんでした。'), true);
    } finally {
      button.disabled = false;
      button.textContent = '無料診断する ↗';
    }
  });
  $('copy-result').addEventListener('click', async () => {
    if (!latest) return;
    const rows = [
      'NT Relay AI購買対応 一次診断',
      'URL: ' + latest.url,
      '日時: ' + latest.checkedAt,
      '良好 ' + latest.counts.ok + ' / 改善候補 ' + latest.counts.attention + ' / 未確認 ' + latest.counts.unknown,
      ...latest.checks.map((c) => '[' + stateNames[c.status] + '] ' + c.label + ': ' + c.detail + (c.status !== 'ok' && c.action ? ' / 対応候補: ' + c.action : '')),
      '', ...latest.limitations, '', 'https://ntrelay.com/ai-commerce-check/'
    ];
    try {
      await navigator.clipboard.writeText(rows.join('\n'));
      showMessage('診断結果をコピーしました。問い合わせ時に貼り付けられます。');
    } catch (_) { showMessage('コピーできませんでした。ブラウザの権限を確認してください。', true); }
  });
})();
