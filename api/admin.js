/* 더와이랩 관리자 v2 — 메뉴 토글 · 필터 자동 제출 · 선택/일괄 · 인라인 상태 · 우측 상세 패널(상태·메모·첨부) · 토스트 */
(function () {
  'use strict';
  var T = window.TWL || {};
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

  /* ---------- 토스트 ---------- */
  var toastEl = $('#toast'), toastT = null;
  function toast(msg, err) {
    if (!toastEl) return;
    toastEl.textContent = msg; toastEl.hidden = false; toastEl.classList.toggle('err', !!err);
    clearTimeout(toastT); toastT = setTimeout(function () { toastEl.hidden = true; }, 2400);
  }

  /* ---------- 서버 통신 ---------- */
  function post(data) {
    var body = new URLSearchParams(); body.set('csrf', T.csrf);
    Object.keys(data).forEach(function (k) { if (Array.isArray(data[k])) data[k].forEach(function (v) { body.append(k + '[]', v); }); else body.set(k, data[k]); });
    return fetch(location.pathname, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
      .then(function (r) { return r.json(); });
  }
  function getJSON(params) {
    return fetch(location.pathname + '?' + new URLSearchParams(params).toString(), { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); });
  }

  /* ---------- 모바일 메뉴 ---------- */
  var menuBtn = $('#menuBtn'), backdrop = $('#sideBackdrop');
  function setNav(open) { document.body.classList.toggle('nav-open', open); if (menuBtn) menuBtn.setAttribute('aria-expanded', String(open)); }
  if (menuBtn) menuBtn.addEventListener('click', function () { setNav(!document.body.classList.contains('nav-open')); });
  if (backdrop) backdrop.addEventListener('click', function () { setNav(false); });

  /* ---------- 필터 자동 제출 ---------- */
  var filterForm = $('#filterForm');
  if (filterForm) {
    $$('select', filterForm).forEach(function (s) { s.addEventListener('change', function () { filterForm.submit(); }); });
    var q = $('input[name=q]', filterForm), qt = null;
    if (q) q.addEventListener('input', function () { clearTimeout(qt); qt = setTimeout(function () { filterForm.submit(); }, 550); });
  }

  /* ---------- 전체 CSV 확인 ---------- */
  $$('button[data-confirm]').forEach(function (b) { b.addEventListener('click', function (e) { if (!confirm(b.getAttribute('data-confirm'))) e.preventDefault(); }); });

  /* ---------- 선택 / 일괄 ---------- */
  var chkAll = $('#chkAll'), boxes = $$('.rowChk'), bulkBar = $('#bulkBar'), selCount = $('#selCount');
  function selected() { return boxes.filter(function (b) { return b.checked; }); }
  function refreshSel() {
    var n = selected().length;
    if (selCount) selCount.textContent = n;
    if (bulkBar) bulkBar.hidden = n === 0;
    if (chkAll) { chkAll.checked = n > 0 && n === boxes.length; chkAll.indeterminate = n > 0 && n < boxes.length; }
  }
  if (chkAll) chkAll.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = chkAll.checked; }); refreshSel(); });
  boxes.forEach(function (b) { b.addEventListener('change', refreshSel); });
  refreshSel();
  $$('button[data-confirm-sel]').forEach(function (b) {
    b.addEventListener('click', function (e) {
      var n = selected().length;
      if (n === 0) { e.preventDefault(); toast('먼저 항목을 선택하세요.', true); return; }
      if (!confirm(n + '건 — ' + b.getAttribute('data-confirm-sel').replace(/&#10;/g, '\n'))) e.preventDefault();
    });
  });
  var bulkApply = $('#bulkApply'), bulkStatus = $('#bulkStatus');
  if (bulkApply) bulkApply.addEventListener('click', function () {
    var ids = selected().map(function (b) { return b.value; }), st = bulkStatus.value;
    if (!ids.length) { toast('먼저 항목을 선택하세요.', true); return; }
    if (!st) { toast('변경할 상태를 고르세요.', true); return; }
    if (!confirm(ids.length + '건을 "' + st + '"(으)로 변경할까요?')) return;
    bulkApply.disabled = true;
    post({ action: 'bulk_status', view: T.view, status: st, ids: ids }).then(function (j) {
      if (j.ok) { toast(j.count + '건 변경됨. 새로고침합니다.'); setTimeout(function () { location.reload(); }, 600); }
      else { bulkApply.disabled = false; toast(j.message || '실패', true); }
    }).catch(function () { bulkApply.disabled = false; toast('통신 오류', true); });
  });

  /* ---------- 상태 배지 갱신 ---------- */
  function paintRow(id, status, cls) {
    var row = $('tr.row[data-id="' + id + '"]'); if (!row) return;
    row.dataset.status = status; row.classList.toggle('is-new', status === '신규');
    var pill = $('.pill', row); if (pill) { pill.className = 'pill p-' + cls; pill.innerHTML = '<i></i>' + esc(status); }
    var sel = $('.st-inline', row); if (sel && sel.value !== status) sel.value = status;
  }
  function saveStatus(id, status, done) {
    post({ action: 'status_save', view: T.view, id: id, status: status }).then(function (j) {
      if (j.ok) { paintRow(id, j.status, j.cls); toast('상태를 "' + j.status + '"(으)로 저장했습니다.'); done && done(true, j); }
      else { toast(j.message || '저장 실패', true); done && done(false, j); }
    }).catch(function () { toast('통신 오류', true); done && done(false); });
  }
  $$('.st-inline').forEach(function (sel) {
    sel.addEventListener('change', function () { saveStatus(sel.dataset.id, sel.value, function (ok) { if (!ok) sel.value = sel.closest('tr').dataset.status; }); });
  });

  /* ---------- 우측 상세 패널 ---------- */
  var drawer = $('#drawer'), dBack = $('#drawerBackdrop'), dBody = $('#drawerBody'), dTitle = $('#drawerTitle'), dEye = $('#drawerEyebrow');
  var lastFocus = null, curId = 0;
  function openDrawer(id) {
    curId = id; lastFocus = document.activeElement;
    drawer.hidden = false; dBack.hidden = false; drawer.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden';
    dBody.innerHTML = '<div class="empty">불러오는 중…</div>'; dTitle.textContent = '상세'; dEye.textContent = '';
    getJSON({ action: 'detail', view: T.view, id: id }).then(render).catch(function () { dBody.innerHTML = '<div class="empty">불러오지 못했습니다.</div>'; });
    $('#drawerClose').focus();
  }
  function closeDrawer() {
    drawer.hidden = true; dBack.hidden = true; drawer.setAttribute('aria-hidden', 'true'); document.body.style.overflow = '';
    if (lastFocus && lastFocus.focus) lastFocus.focus();
    if (/[?&]open=/.test(location.search)) { var u = new URL(location.href); u.searchParams.delete('open'); history.replaceState(null, '', u.toString()); }
  }
  function memoHTML(m) {
    var sys = /^상태 변경 · /.test(m.body);
    return '<div class="memo' + (sys ? ' sys' : '') + '" data-mid="' + m.id + '"><div class="memo-head"><span>' + esc(String(m.created_at).substring(0, 16)) + '</span>' +
      '<button type="button" class="memo-del" data-mid="' + m.id + '">삭제</button></div><div class="memo-body">' + esc(m.body) + '</div></div>';
  }
  function render(j) {
    if (!j.ok) { dBody.innerHTML = '<div class="empty">' + esc(j.message || '오류') + '</div>'; return; }
    dEye.textContent = '#' + j.id + ' · ' + j.rel + ' 접수';
    dTitle.textContent = j.name || '상세';
    var html = '';
    html += '<div class="d-status"><label>진행 상태</label><select class="sel" id="dStatus">' + j.statusOpts.map(function (s) { return '<option' + (s === j.status ? ' selected' : '') + '>' + esc(s) + '</option>'; }).join('') + '</select>' +
      '<button type="button" class="btn btn-primary btn-sm" id="dSave">저장</button><span class="ok" id="dOk"></span></div>';
    j.sections.forEach(function (sec) {
      html += '<section class="d-sec"><h3>' + esc(sec.title) + '</h3><dl class="d-grid">';
      sec.items.forEach(function (it) {
        var v;
        if (it.file) v = it.value ? '<a class="filelink" href="' + esc(it.value.url) + '">⬇ ' + esc(it.value.label) + '</a>' : '<span class="muted">없음</span>';
        else v = (it.value === null || it.value === '' || it.value === undefined) ? '<span class="muted">-</span>' : esc(it.value);
        html += '<div class="d-item' + (it.long ? ' long' : it.wide ? ' wide' : '') + '"><dt>' + esc(it.label) + '</dt><dd>' + v + '</dd></div>';
      });
      html += '</dl></section>';
    });
    html += '<section class="d-sec"><h3>처리 메모 · 히스토리</h3><div class="memo-list" id="memoList">' + (j.memos.length ? j.memos.map(memoHTML).join('') : '<div class="muted" id="memoEmpty">아직 메모가 없습니다.</div>') + '</div>' +
      '<div class="memo-add"><textarea id="memoInput" placeholder="새 메모 입력 (저장 시 시간이 기록됩니다)"></textarea><button type="button" class="btn btn-ghost btn-sm" id="memoAdd">메모 추가</button></div></section>';
    html += '<div class="d-meta">접수 ' + esc(j.created_at) + (j.ip ? ' · IP ' + esc(j.ip) : '') + ' · #' + j.id + '</div>';
    dBody.innerHTML = html;

    $('#dSave').addEventListener('click', function () {
      var st = $('#dStatus').value, ok = $('#dOk'); ok.textContent = '저장 중…';
      saveStatus(j.id, st, function (good) {
        ok.textContent = good ? '✓ 저장됨' : '✗ 실패'; ok.style.color = good ? 'var(--done)' : 'var(--new)';
        if (good && st !== j.status) { getJSON({ action: 'detail', view: T.view, id: j.id }).then(function (nj) { if (nj.ok) { var list = $('#memoList'); if (list) { list.innerHTML = nj.memos.map(memoHTML).join(''); bindMemoDel(); } j.status = st; } }); }
      });
    });
    function bindMemoDel() {
      $$('.memo-del', dBody).forEach(function (b) {
        if (b._bound) return; b._bound = true;
        b.addEventListener('click', function () {
          if (!confirm('이 메모를 삭제할까요?')) return;
          post({ action: 'memo_del', memoId: b.dataset.mid }).then(function (r) {
            if (r.ok) { var it = b.closest('.memo'); it.remove(); if (!$('.memo', dBody)) $('#memoList').innerHTML = '<div class="muted" id="memoEmpty">아직 메모가 없습니다.</div>'; toast('메모를 삭제했습니다.'); }
            else toast('삭제 실패', true);
          });
        });
      });
    }
    bindMemoDel();
    $('#memoAdd').addEventListener('click', function () {
      var ta = $('#memoInput'), body = ta.value.trim(); if (!body) { ta.focus(); return; }
      var btn = this; btn.disabled = true;
      post({ action: 'memo_add', view: T.view, id: j.id, body: body }).then(function (r) {
        btn.disabled = false;
        if (r.ok) { var e = $('#memoEmpty'); if (e) e.remove(); $('#memoList').insertAdjacentHTML('beforeend', memoHTML(r.memo)); bindMemoDel(); ta.value = ''; toast('메모를 추가했습니다.'); }
        else toast(r.message || '메모 저장 실패', true);
      }).catch(function () { btn.disabled = false; toast('통신 오류', true); });
    });
  }
  $$('[data-open]').forEach(function (el) { el.addEventListener('click', function (e) { e.preventDefault(); openDrawer(parseInt(el.getAttribute('data-open'), 10)); }); });
  $$('tr.row').forEach(function (row) { row.addEventListener('click', function (e) { if (e.target.closest('input,select,button,a,label')) return; openDrawer(parseInt(row.dataset.id, 10)); }); });
  if (dBack) dBack.addEventListener('click', closeDrawer);
  var dClose = $('#drawerClose'); if (dClose) dClose.addEventListener('click', closeDrawer);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && drawer && !drawer.hidden) closeDrawer(); });
  if (T.open && T.view !== 'dash') openDrawer(T.open);
})();
