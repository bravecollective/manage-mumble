jQuery(document).ready(function() {
    buttons();
    update_users();
    update_bans();
});

function update_users() {
    $.ajax({
	async: true,
	url: "mod.php?arg1=users",
	mimeType: "application/json",
	dataType: 'json',
	error: function(xhr, status, error) {},
	success: function(json) {
	    jQuery.each(json, function(i, line) {
		cnt = "";
		cnt += '<option value=' + line['id'] + '>' + line['name'] + '</option>';
		$('#kick_users option:last').after(cnt);
		$('#ban_users option:last').after(cnt);
	    })
        },
    });
}

function update_bans() {
    $("#bans > tr").each(function() {
	if (!$(this).hasClass("keep")) {
	    $(this).remove();
	}
    });
    $('#bans .keep').removeClass("hide");

    $.ajax({
	async: true,
	url: "mod.php?arg1=bans",
	mimeType: "application/json",
	dataType: 'json',
	error: function(xhr, status, error) {
	},
	success: function(json) {
	    $('#bans .keep').addClass("hide");
	    var t;
	    jQuery.each(json, function(i, line) {

		cnt = "";
		cnt += '<tr>';
		t = new Date();
		t.setTime(parseInt(line['start']) * 1000);
		cnt += '<td>' + t.toUTCString() + '</td>';

		t = new Date();
		t.setTime(parseInt(line['start']) * 1000 + parseInt(line['duration']) * 1000);
		cnt += '<td>' + t.toUTCString() + '</td>';

		cnt += '<td>' + line['name'] + '</td>';
		cnt += '<td>' + line['reason'] + '</td>';
		cnt += '<td><button class="btn btn-danger btn-xs unban" onclick="unban(\'' + line['name'] + '\')">Unban</button></td>';
		cnt += '</tr>';
		$('#bans tr:last').after(cnt);
	    })
        },
    });
}

function buttons() {
    if ($('#kick_users').val() && $('#kick_reason').val() != "") {
	$('#kick_button').prop("disabled", false);
    } else {
	$('#kick_button').prop("disabled", true);
    }

    if ($('#ban_users').val() && $('#ban_users').val() != "0" && $('#ban_reason').val() != "" && $('#ban_duration').val()) {
	$('#ban_button').prop("disabled", false);
    } else {
	$('#ban_button').prop("disabled", true);
    }

}

function disable() {
    $('#kick_users').prop("disabled", true);
    $('#kick_reason').prop("disabled", true);
    $('#kick_button').prop("disabled", true);

    $('#ban_users').prop("disabled", true);
    $('#ban_reason').prop("disabled", true);
    $('#ban_duration').prop("disabled", true);
    $('#ban_button').prop("disabled", true);

    $("#bans .unban").each(function() {
	    $(this).prop("disabled", true);
    });
}

function enable() {
    $('#kick_users').prop("disabled", false);
    $('#kick_reason').prop("disabled", false);
    $('#ban_users').prop("disabled", false);
    $('#ban_reason').prop("disabled", false);
    $('#ban_duration').prop("disabled", false);

    $("#bans .unban").each(function() {
	    $(this).prop("disabled", false);
    });
    buttons()
}


function kick(userid, reason) {
    disable();

    $.ajax({
	async: true,
	url: "mod.php?arg1=kick&arg2=" + userid + "&arg3=" + reason,
	mimeType: "application/json",
	dataType: 'json',
	error: function(xhr, status, error) {
	    enable();
	},
	success: function(json) {
	    enable();
        },
    });
}

function kickban(userid, length, reason) {
    disable();

    $.ajax({
	async: true,
	url: "mod.php?arg1=kickban&arg2=" + userid + "&arg3=" + length + "&arg4=" + reason,
	mimeType: "application/json",
	dataType: 'json',
	error: function(xhr, status, error) {
	    enable();
	},
	success: function(json) {
	    update_bans();
	    enable();
        },
    });
}

function unban(username) {
    disable();

    $.ajax({
	async: true,
	url: "mod.php?arg1=unban&arg2=" + username,
	mimeType: "application/json",
	dataType: 'json',
	error: function(xhr, status, error) {
	    enable();
	},
	success: function(json) {
	    update_bans();
	    enable();
        },
    });
}
