(() => {
  'use strict';
  const content = window.AX_CONTENT;
  if (!content) return;
  const escape = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const allPrograms = [...content.areas.flatMap(area => area.programs), ...content.special];
  const topics = list => `<ul class="ax-program-topics">${list.map(item => `<li>${escape(item)}</li>`).join('')}</ul>`;
  const card = (program, special = false) => `<article class="ax-program-card${special ? ' ax-special-card' : ''}" id="course-${escape(program.id)}" aria-labelledby="course-title-${escape(program.id)}">
    <div class="ax-course-code">${special ? `<span aria-hidden="true"></span><span>${escape(program.relation)}</span>` : `<span>${escape(program.id)}</span><span>AX PROGRAM</span>`}</div>
    <h${special ? '3' : '4'} id="course-title-${escape(program.id)}">${escape(program.name)}</h${special ? '3' : '4'}>
    <p class="ax-program-description">${escape(program.introduction)}</p>
    <dl class="ax-program-details"><div><dt>추천 대상</dt><dd>${escape(program.audience)}</dd></div><div><dt>주요 교육내용</dt><dd>${topics(program.topics)}</dd></div></dl>
    <div class="ax-course-actions">${program.detail ? `<a href="${escape(content.links[program.detail])}">AX Discovery 상세보기 ↗</a>` : program.detailUrl ? `<a href="${escape(program.detailUrl)}" target="_blank" rel="noopener">${escape(program.detailLabel)} ↗</a>` : '<span></span>'}<a class="ax-course-inquiry" href="#inquiry" data-course="${escape(program.id)}">이 과정 문의하기 <span aria-hidden="true">→</span></a></div>
  </article>`;

  document.querySelector('#program-tabs').innerHTML = content.areas.map((area, index) => `<button type="button" class="ax-program-tab ax-${area.group}" role="tab" id="tab-${area.id}" aria-controls="panel-${area.id}" aria-selected="${index === 0}" tabindex="${index === 0 ? '0' : '-1'}"><span>${String(index + 1).padStart(2, '0')}</span>${escape(area.shortName)}</button>`).join('');
  document.querySelector('#program-panels').innerHTML = content.areas.map((area, index) => `<div class="ax-program-panel ax-${area.group}" id="panel-${area.id}" role="tabpanel" aria-labelledby="tab-${area.id}" tabindex="0" ${index === 0 ? '' : 'hidden'}><div class="ax-panel-heading"><div><p>${area.group === 'external' ? '외부 전환' : '내부 전환'} · ${String(index + 1).padStart(2, '0')}</p><h3>${escape(area.name)}</h3><span>${escape(area.description)}</span></div></div><div class="ax-program-grid">${area.programs.map(program => card(program)).join('')}</div></div>`).join('');
  document.querySelectorAll('[data-ax-link]').forEach(link => {
    link.href = content.links[link.dataset.axLink];
  });
  document.querySelector('#special-programs').innerHTML = content.special.map(program => card(program, true)).join('');

  const tabs = [...document.querySelectorAll('.ax-program-tab')];
  function activateArea(id, focus = false) {
    content.areas.forEach(area => {
      const selected = area.id === id;
      const tab = document.querySelector(`#tab-${area.id}`);
      tab.setAttribute('aria-selected', String(selected));
      tab.tabIndex = selected ? 0 : -1;
      document.querySelector(`#panel-${area.id}`).hidden = !selected;
      if (selected && focus) tab.focus();
    });
    const area = content.areas.find(item => item.id === id);
    document.querySelector('#program-status').textContent = `${area.name} 프로그램 5개를 표시합니다.`;
  }
  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => activateArea(content.areas[index].id));
    tab.addEventListener('keydown', event => {
      let next;
      if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
      if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
      if (event.key === 'Home') next = 0;
      if (event.key === 'End') next = tabs.length - 1;
      if (next !== undefined) { event.preventDefault(); activateArea(content.areas[next].id, true); }
    });
  });

  // 기존 영역 카드를 유지하고 선택한 영역의 5개 과정으로 연결합니다.
  content.areas.forEach(area => {
    const link = document.createElement('a');
    link.className = 'ax-area-program-link';
    link.href = '#programs';
    link.innerHTML = '이 영역 프로그램 보기 <span aria-hidden="true">→</span>';
    link.addEventListener('click', () => { activateArea(area.id); requestAnimationFrame(() => document.querySelector(`#tab-${area.id}`).focus({preventScroll:true})); });
    document.querySelector(`#${area.id}`).append(link);
  });
  document.querySelectorAll('.ax-map-group a').forEach(link => link.addEventListener('click', () => {
    const target = document.querySelector(link.getAttribute('href'));
    if (target) target.focus({preventScroll:true});
  }));

  const emailLink = document.querySelector('#email-inquiry');
  const selection = document.querySelector('#selected-program');
  const makeMail = program => `mailto:${content.contact.email}?subject=${encodeURIComponent(program ? `[AX 프로그램 문의] ${program.name}` : '[더와이랩] AX 맞춤형 제안 문의')}&body=${encodeURIComponent(`${program ? `관심 프로그램: ${program.name}\n\n` : ''}조직명:\n해결하고 싶은 과제:\n교육대상 및 예상 인원:\n희망 일정과 운영방식:\n담당자 연락처:\n`)}`;
  emailLink.href = makeMail();
  document.querySelectorAll('[data-course]').forEach(link => link.addEventListener('click', () => {
    const program = allPrograms.find(item => item.id === link.dataset.course);
    document.querySelector('#selected-program-name').textContent = program.name;
    selection.hidden = false;
    emailLink.href = makeMail(program);
    requestAnimationFrame(() => document.querySelector('#inquiry-title').focus({preventScroll:true}));
  }));
  document.querySelector('#inquiry-title').tabIndex = -1;
  document.querySelector('#clear-program').addEventListener('click', () => {
    selection.hidden = true;
    document.querySelector('#selected-program-name').textContent = '';
    emailLink.href = makeMail();
    emailLink.focus();
  });
  document.querySelector('[data-print]').addEventListener('click', async () => {
    await document.fonts?.ready;
    window.print();
  });
})();
