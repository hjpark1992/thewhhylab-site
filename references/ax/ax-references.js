(function () {
  var cases = window.AX_CASES || [];
  var grid = document.getElementById('caseGrid');
  var buttons = document.querySelectorAll('[data-filter]');
  var empty = document.getElementById('emptyState');
  var status = document.getElementById('filterStatus');

  function escapeHtml(value) {
    return String(value).replace(/[&<>"]/g, function (character) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[character];
    });
  }

  function renderCards() {
    grid.innerHTML = cases.map(function (item) {
      var highlights = item.highlights.map(function (highlight) {
        return '<span>' + escapeHtml(highlight) + '</span>';
      }).join('');
      return '<article class="ax-case-card" data-category="' + item.filter + '">' +
        '<a href="' + item.slug + '/index.html" aria-label="' + escapeHtml(item.headline.replace(/\s+/g, ' ')) + ' 상세 보기">' +
          '<div class="ax-case-image">' +
            '<div class="ax-case-image-top"><span>' + escapeHtml(item.category) + '</span></div>' +
            '<div class="ax-case-image-copy"><strong>' + (item.cardClientLines || [item.clientType]).map(escapeHtml).join('<br/>') + '</strong></div>' +
            '<img class="ax-case-character" src="img/' + item.character + '" alt="" aria-hidden="true"/>' +
            '<i aria-hidden="true"></i>' +
          '</div>' +
          '<div class="ax-case-card-body">' +
            '<h3>' + escapeHtml(item.headline).replace(/\n/g, '<br/>') + '</h3>' +
            '<div class="ax-case-highlights">' + highlights + '</div>' +
            '<span class="ax-case-link">자세히 보기 <b aria-hidden="true">→</b></span>' +
          '</div>' +
        '</a>' +
      '</article>';
    }).join('');
  }

  function selectFilter(filter, label) {
    var cards = document.querySelectorAll('.ax-case-card');
    var shown = 0;
    cards.forEach(function (card) {
      var match = filter === 'all' || card.dataset.category === filter;
      card.hidden = !match;
      if (match) shown += 1;
    });
    buttons.forEach(function (button) {
      var active = button.dataset.filter === filter;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    empty.hidden = shown !== 0;
    status.textContent = label + ' 사례 ' + shown + '건을 표시합니다.';
  }

  renderCards();
  buttons.forEach(function (button) {
    button.addEventListener('click', function () {
      selectFilter(button.dataset.filter, button.childNodes[0].textContent.trim());
    });
  });
  document.querySelectorAll('[data-reset-filter]').forEach(function (button) {
    button.addEventListener('click', function () { selectFilter('all', '전체'); });
  });
})();
