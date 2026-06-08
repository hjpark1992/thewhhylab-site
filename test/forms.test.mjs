import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const formsSrc = readFileSync(new URL('../shared/js/forms.js', import.meta.url), 'utf8');

function setup(formHtml) {
  const dom = new JSDOM(`<!doctype html><body>${formHtml}</body>`, { runScripts: 'outside-only', url: 'http://localhost/' });
  const { window } = dom;
  // forms.js 평가
  window.eval(formsSrc);
  return window;
}

const contactForm = `
<form data-form="contact" action="/api/submit.php" method="post">
  <input type="hidden" name="form_type" value="contact">
  <input type="hidden" name="form_ts" value="0">
  <div class="form-field"><label>이름<span class="req">*</span></label>
    <input type="text" name="name" required><span class="err-msg">필수입니다.</span></div>
  <div class="form-field"><label>이메일<span class="req">*</span></label>
    <input type="email" name="email" required data-validate="email"><span class="err-msg">이메일 형식</span></div>
  <label class="form-consent"><input type="checkbox" name="privacy_agreed" required value="1">동의</label>
  <input class="form-honey" type="text" name="company_website" tabindex="-1" autocomplete="off">
  <button type="submit" class="form-submit">제출</button>
  <div class="form-status" role="status" aria-live="polite"></div>
</form>`;

test('필수값 비면 제출 차단 + invalid 가시화', () => {
  const w = setup(contactForm);
  const form = w.document.querySelector('form');
  w.initForm(form);
  form.dispatchEvent(new w.Event('submit', { cancelable: true, bubbles: true }));
  const nameField = w.document.querySelector('input[name=name]').closest('.form-field');
  assert.equal(nameField.classList.contains('invalid'), true);
});

test('이메일 형식 오류 감지', () => {
  const w = setup(contactForm);
  const form = w.document.querySelector('form');
  w.document.querySelector('input[name=name]').value = '홍길동';
  w.document.querySelector('input[name=email]').value = 'not-an-email';
  w.document.querySelector('input[name=privacy_agreed]').checked = true;
  w.initForm(form);
  form.dispatchEvent(new w.Event('submit', { cancelable: true, bubbles: true }));
  const emailField = w.document.querySelector('input[name=email]').closest('.form-field');
  assert.equal(emailField.classList.contains('invalid'), true);
});

test('허니팟 채워지면 차단(검증 통과해도 제출 안 함)', () => {
  const w = setup(contactForm);
  let fetched = false;
  w.fetch = async () => { fetched = true; return { json: async () => ({ ok: true, message: 'ok' }) }; };
  const form = w.document.querySelector('form');
  w.document.querySelector('input[name=name]').value = '홍길동';
  w.document.querySelector('input[name=email]').value = 'a@b.com';
  w.document.querySelector('input[name=privacy_agreed]').checked = true;
  w.document.querySelector('input[name=company_website]').value = 'bot';
  w.initForm(form);
  form.dispatchEvent(new w.Event('submit', { cancelable: true, bubbles: true }));
  assert.equal(fetched, false);
});

test('정상 입력 시 fetch 호출 + 성공 메시지 가시화', async () => {
  const w = setup(contactForm);
  w.fetch = async () => ({ json: async () => ({ ok: true, message: '접수 완료' }) });
  const form = w.document.querySelector('form');
  w.document.querySelector('input[name=name]').value = '홍길동';
  w.document.querySelector('input[name=email]').value = 'a@b.com';
  w.document.querySelector('input[name=privacy_agreed]').checked = true;
  w.initForm(form);
  form.dispatchEvent(new w.Event('submit', { cancelable: true, bubbles: true }));
  await new Promise(r => setTimeout(r, 20));
  const status = w.document.querySelector('.form-status');
  assert.equal(status.classList.contains('show'), true);
  assert.equal(status.classList.contains('success'), true);
  assert.match(status.textContent, /접수 완료/);
});

const checkGroupForm = `
<form data-form="member" action="/api/submit.php" method="post">
  <input type="hidden" name="form_ts" value="0">
  <div class="form-field" data-require-checked><label>강의 분야<span class="req">*</span></label>
    <div class="check-grid">
      <label class="check-item"><input type="checkbox" name="fields[]" value="A"> A</label>
      <label class="check-item"><input type="checkbox" name="fields[]" value="B"> B</label>
    </div><span class="err-msg">1개 이상 선택</span></div>
  <input class="form-honey" type="text" name="company_website" tabindex="-1" autocomplete="off">
  <button type="submit" class="form-submit">제출</button>
  <div class="form-status" role="status" aria-live="polite"></div>
</form>`;

test('필수 체크박스 그룹: 미선택 시 제출 차단 + invalid', () => {
  const w = setup(checkGroupForm);
  const form = w.document.querySelector('form');
  w.fetch = async () => ({ json: async () => ({ ok: true, message: 'ok' }) });
  w.initForm(form);
  form.dispatchEvent(new w.Event('submit', { cancelable: true, bubbles: true }));
  const group = w.document.querySelector('[data-require-checked]');
  assert.equal(group.classList.contains('invalid'), true);
});

test('필수 체크박스 그룹: 1개 선택 시 invalid 해제', () => {
  const w = setup(checkGroupForm);
  const form = w.document.querySelector('form');
  w.fetch = async () => ({ json: async () => ({ ok: true, message: 'ok' }) });
  w.document.querySelector('input[name="fields[]"]').checked = true;
  w.initForm(form);
  form.dispatchEvent(new w.Event('submit', { cancelable: true, bubbles: true }));
  const group = w.document.querySelector('[data-require-checked]');
  assert.equal(group.classList.contains('invalid'), false);
});
