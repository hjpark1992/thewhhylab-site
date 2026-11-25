(function () {
  var slug = document.body.getAttribute('data-case-slug');
  var cases = window.AX_CASES || [];
  var item = cases.find(function (entry) { return entry.slug === slug; });
  var root = document.getElementById('caseRoot');
  if (!item || !root) return;

  var index = cases.indexOf(item);
  var total = String(cases.length).padStart(2, '0');
  var previous = cases[(index - 1 + cases.length) % cases.length];
  var next = cases[(index + 1) % cases.length];
  var sections = item.sections.map(function (section) {
    return '<section class="ax-case-section"><h2>' + section.title + '</h2>' + section.html + '</section>';
  }).join('');

  root.innerHTML =
    '<section class="ax-case-hero"><div class="ax-ref-shell">' +
      '<nav class="p-crumb" aria-label="현재 위치"><a href="../../../index.html">Home</a><span class="sep">/</span><a href="../index.html">AX 레퍼런스</a><span class="sep">/</span><span>' + item.number + '</span></nav>' +
      '<p class="ax-case-meta">CASE ' + item.number + ' · ' + item.category + '</p>' +
      '<h1>' + (item.detailHeadline || item.headline).replace(/\n/g, '<br/>') + '</h1>' +
      '<p class="ax-case-project">' + item.projectName + '</p>' +
      '<p class="ax-case-target"><span>CLIENT</span>' + item.clientType + '</p>' +
    '</div></section>' +
    '<section class="ax-case-content"><div class="ax-ref-shell ax-case-layout">' +
      '<aside class="ax-case-aside"><p>CASE ' + item.number + ' / ' + total + '</p><a href="../index.html">← 사례 목록</a><a href="#caseRoot">↑ 페이지 위로</a></aside>' +
      '<div>' + sections +
        '<nav class="ax-case-next" aria-label="다른 사례"><a href="../' + previous.slug + '/index.html">← ' + previous.number + ' 이전 사례</a><a href="../' + next.slug + '/index.html">' + next.number + ' 다음 사례 →</a></nav>' +
        '<div class="ax-case-inline-cta"><div><strong>우리 조직의 AX 과제도 함께 설계해 보세요.</strong><p>업무 진단부터 워크플로우 재설계와 실행까지 연결합니다.</p></div><a href="../../../contact/">AX 문의하기 →</a></div>' +
      '</div>' +
    '</div></section>';
})();
