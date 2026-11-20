/* 더와이랩 — 폼 페이지 공통 스크립트 */
(function (root) {
  function fieldOf(el) { return el.closest('.form-field'); }
  function setInvalid(el, bad) {
    var f = el.closest('.form-field') || el.closest('.form-consent');
    if (!f) return;
    f.classList.toggle('invalid', !!bad);
    if (el.setAttribute) el.setAttribute('aria-invalid', bad ? 'true' : 'false');
  }
  function validate(form) {
    var firstBad = null;
    // required (text/email/tel/textarea/select)
    var inputs = form.querySelectorAll('[required]');
    inputs.forEach(function (el) {
      var bad = false;
      if (el.type === 'checkbox') { bad = !el.checked; }
      else if (el.type === 'radio') {
        bad = !form.querySelector('input[name="' + el.name + '"]:checked');
      } else { bad = el.value.trim() === ''; }
      if (!bad && el.dataset.validate === 'email') {
        bad = !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value.trim());
      }
      if (!bad && el.dataset.validate === 'year') { bad = !/^\d{4}$/.test(el.value.trim()); }
      setInvalid(el, bad);
      if (bad && !firstBad) firstBad = el;
    });
    // 체크박스 그룹 필수 (최소 1개 선택)
    var groups = form.querySelectorAll('[data-require-checked]');
    groups.forEach(function (group) {
      var bad = group.querySelectorAll('input[type=checkbox]:checked').length === 0;
      group.classList.toggle('invalid', bad);
      group.setAttribute('aria-invalid', bad ? 'true' : 'false');
      if (bad && !firstBad) firstBad = group.querySelector('input[type=checkbox]');
    });
    return firstBad;
  }
  function showStatus(form, type, msg) {
    var s = form.querySelector('.form-status');
    if (!s) return;
    s.textContent = msg;
    s.classList.remove('success', 'error');
    s.classList.add('show', type);
  }
  function initEtc(form) {
    form.querySelectorAll('[data-etc-target]').forEach(function (ctrl) {
      var target = form.querySelector(ctrl.getAttribute('data-etc-target'));
      if (!target) return;
      function sync() {
        var on = ctrl.type === 'checkbox' ? ctrl.checked : ctrl.value === '기타';
        target.hidden = !on;
        if (!on) target.value = '';
      }
      ctrl.addEventListener('change', sync);
      sync();
    });
  }
  function initForm(form) {
    if (!form) return;
    var ts = form.querySelector('input[name=form_ts]');
    if (ts) ts.value = String(Date.now());
    initEtc(form);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      // 허니팟
      var honey = form.querySelector('.form-honey input, input.form-honey');
      if (honey && honey.value) { showStatus(form, 'success', '접수되었습니다.'); return; }
      var bad = validate(form);
      if (bad) { if (bad.focus) bad.focus(); showStatus(form, 'error', '입력 내용을 확인해주세요.'); return; }
      var btn = form.querySelector('.form-submit');
      if (btn) btn.disabled = true;
      var data = new FormData(form);
      root.fetch(form.action, { method: 'POST', body: data })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res && res.ok) {
            showStatus(form, 'success', res.message || '접수되었습니다.');
            form.reset();
          } else {
            showStatus(form, 'error', (res && res.message) || '제출에 실패했습니다.');
          }
        })
        .catch(function () { showStatus(form, 'error', '네트워크 오류가 발생했습니다.'); })
        .finally(function () { if (btn) btn.disabled = false; });
    });
  }
  root.initForm = initForm;
  if (root.document) {
    root.document.addEventListener('DOMContentLoaded', function () {
      root.document.querySelectorAll('form[data-form]').forEach(initForm);
    });
  }
})(typeof window !== 'undefined' ? window : this);
