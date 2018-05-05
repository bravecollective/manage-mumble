<?php if (!defined('GUEST')) die('go away'); ?>

    <script src="js/msc.js"></script>

    <div class="row">
	<div class="col-xs-12">
	    <div class="panel panel-default">
		<div class="panel-heading">
		    <h3 class="panel-title">Moderation</h3>
		</div>
		<div class="panel-body">

		    <div class="row">
			<div class="col-xs-4">
			    <select class="form-control" id="kick_users" onchange="buttons()">
				<option disabled selected value="0">Please select the user you want to kick</option>
			    </select>
			</div>
			<div class="col-xs-6">
			     <input class="form-control" id="kick_reason" placeholder="Please specify the reason" type="text" oninput="buttons()">
			</div>
			<div class="col-xs-1">
			    <button id="kick_button" class="btn btn-danger btn" onclick="kick($('#kick_users').val(), $('#kick_reason').val())">Kick User</button>
			</div>
		    </div>

		    <br>

		    <div class="row">
			<div class="col-xs-4">
			    <select class="form-control" id="ban_users" onchange="buttons()">
				<option disabled selected value="0">Please select the user you want to ban</option>
			    </select>
			</div>
			<div class="col-xs-4">
			     <input class="form-control" id="ban_reason" placeholder="Please specify the reason" type="text" oninput="buttons()">
			</div>
			<div class="col-xs-2">
			    <select class="form-control" id="ban_duration" onchange="buttons()">
				<option disabled selected value="0">Select Duration</option>
				<option value="900">15 min</option>
				<option value="3600">1 hour</option>
				<option value="43200">12 hours</option>
				<option value="86400">24 hours</option>
				<option value="604800">7 days</option>
				<option value="2592000">30 days</option>
				<option value="315360000">Unlimited</option>
			    </select>
			</div>
			<div class="col-xs-1">
			    <button id="ban_button" class="btn btn-danger btn" onclick="kickban($('#ban_users').val(), $('#ban_duration').val(), $('#ban_reason').val())">KickBan User</button>
			</div>
		    </div>

		</div>
	    </div>
	</div>
    </div>

    <div class="row">
	<div class="col-xs-12">
	    <div class="panel panel-default">
		<div class="panel-heading">
		    <h3 class="panel-title">Existing Bans</h3>
		</div>
		<div class="panel-body">
		    <table class="table table-striped table-hover ">
			<thead>
			    <tr>
				<th>Created At</th>
				<th>Vald Till</th>
				<th>User</th>
				<th>Reason</th>
				<th>Action</th>
			    </tr>
			</thead>
			<tbody id="bans">
			    <tr class="keep"><td colspan="5">Loading existing bans...</td></tr>
			</tbody>
		    </table>
		</div>
	    </div>
	</div>
    </div>
