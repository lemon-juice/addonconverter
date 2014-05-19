document.addEventListener('DOMContentLoaded', function() {
	var form = document.getElementById('converterForm');
	
	if (form) {
		form.convertChromeUrls.addEventListener('click', toggleChromeExtensions, false);
		toggleChromeExtensions();
	}
}, false);

function toggleChromeExtensions() {
	var form = document.getElementById('converterForm');
	
	var disabled = !form.convertChromeUrls.checked;
	var checkboxes = form.querySelectorAll('#convertChromeUrlsExtensions input[type=checkbox]');
	
	for (var i=0; i<checkboxes.length; i++) {
		checkboxes[i].disabled = disabled;
	}
}
