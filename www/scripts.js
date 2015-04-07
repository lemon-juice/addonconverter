document.addEventListener('DOMContentLoaded', function() {
	var form = document.getElementById('converterForm');
	
	if (form) {
		form.convertChromeUrls.addEventListener('click', toggleChromeExtensions, false);
		form.convertManifest.addEventListener('click', toggleConvertManifest, false);
		toggleChromeExtensions();
		toggleConvertManifest();
	}
	
	var ao = document.getElementById('advOptionsChbox');
	
	if (ao) {
		ao.addEventListener('click', toggleAdvOptions, false);
		toggleAdvOptions();
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

function toggleConvertManifest() {
	var form = document.getElementById('converterForm');
	
	form.convertPageInfoChrome.disabled = !form.convertManifest.checked;
}

function toggleAdvOptions() {
	var display = document.getElementById('advOptionsChbox').checked ? '' : 'none';
	document.getElementById('options').style.display = display;
}