var lastMessageIsStatus = false;

function removeLastLine() {
	var el = document.getElementById("job-output");

	var pos = el.value.lastIndexOf("\n") + 1;
	if (pos < 0)
		pos = 0;

	el.value = el.value.substring(0, pos);
}

function jobl(message) {
	var el = document.getElementById("job-output");

	if (lastMessageIsStatus)
		removeLastLine();

	lastMessageIsStatus = false;

	el.value += message + "\n";
	el.scrollTop = el.scrollHeight;

	if (el.setSelectionRange)
		el.setSelectionRange(el.value.length, el.value.length);

	el.focus();
}

function jobs(message) {
	var el = document.getElementById("job-output");

	if (lastMessageIsStatus)
		removeLastLine();

	lastMessageIsStatus = true;

	var l = el.value.length;

	el.value += message;
	el.scrollTop = el.scrollHeight;

	if (el.setSelectionRange)
		el.setSelectionRange(l, l);

	el.focus();
}

function jobdone() {
	var el = document.getElementById("job-output");
	el.blur();

	var el = document.getElementById("job-back-button");
	el.style.pointerEvents = "auto";
	el.style.opacity = 1;
	el.style.cursor = "inherit";
}

function startSyncOperation(url) {
	var source = new EventSource(url);
	var opened = false;

	source.onopen = function() {
		opened = true;
	}

	source.onerror = function(ev) {
		if (!opened) {
			jobl("**** error ****");
		}

		source.close();
		jobdone();
	}

	source.addEventListener("log", function(ev) {
		var data = JSON.parse(ev.data);
		jobl(data.message);
	});

	source.addEventListener("status", function(ev) {
		var data = JSON.parse(ev.data);
		console.log("status: "+data.message);
		jobs(data.message);
	});
}