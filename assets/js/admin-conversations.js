(function () {
	var buttons = document.querySelectorAll('.grayfox-view-conv');
	for (var i = 0; i < buttons.length; i++) {
		buttons[i].addEventListener('click', function () {
			var convId = this.getAttribute('data-conv-id');
			var detail = document.getElementById('grayfox-conv-detail-' + convId);
			if (detail) {
				if (detail.style.display === 'none') {
					detail.style.display = '';
					this.textContent = grayfoxConversations.i18n.hideMessages;
				} else {
					detail.style.display = 'none';
					this.textContent = grayfoxConversations.i18n.viewMessages;
				}
			}
		});
	}
})();
