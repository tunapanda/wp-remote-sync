var lastMessageIsStatus = false;

function removeLastLine() {
	var el = document.getElementById("job-output");

	var pos = el.value.lastIndexOf("\n") + 1;
	if (pos < 0)
		pos = 0;

	el.value = el.value.substring(0, pos);
}

function removeOldLines() {
	var el = document.getElementById("job-output");
	var value = el.value;

	var pos = el.length;
	for (var i = 0; i < 100; i++) {
		var pos = el.value.lastIndexOf("\n", pos) - 1;
		if (pos < 0)
			pos = 0;
	}

	el.value = value.substring(pos);
}

function jobl(message) {
	var el = document.getElementById("job-output");

	if (lastMessageIsStatus)
		removeLastLine();

	removeOldLines();

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

	removeOldLines();

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