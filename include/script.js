jQuery(function($)
{
	var checkstatus = 0;

	function update_notification(type, message)
	{
		$("#notification").addClass('hide');

		if(typeof message !== 'undefined' && message != 'undefined' && message != '')
		{
			switch(type)
			{
				case 'success':
					$("#notification").html("<div class='success'>" + message + "</div>").removeClass('hide');
				break;

				case 'error':
					$("#notification").html("<div id='login_error'><p>" + message + "</p></div>").removeClass('hide');
				break;
			}
		}
	}

	function check_ssc_response(orderref, user_ssn)
	{
		$.ajax(
		{
			url: script_bank_id.plugin_url + 'api.php?action=ssc_check&orderref=' + orderref,
			type: 'POST',
			cache: false,
			dataType: 'json'
		})
		.done(function(data, textStatus)
		{
			if(data.success == 1)
			{
				update_notification('success', data.msg);

				if(typeof data.redirect !== 'undefined' && data.redirect != '')
				{
					location.href = data.redirect;
				}

				else
				{
					checkstatus = 0;

					$("#login_loading").addClass('hide');
					$("#login_fields").removeClass('hide');
				}
			}

			else if(data.retry == 1 && checkstatus < 3)
			{
				checkstatus++;

				setTimeout(function()
				{
					check_ssc_response(orderref, user_ssn);
				}, 10000);
			}

			else if(data.error == 1 && checkstatus == 3)
			{
				update_notification('error', script_bank_id.took_too_long_text);

				$("#login_loading").addClass('hide');
				$("#login_fields").removeClass('hide');
			}

			else
			{
				update_notification('error', data.msg);
			}
		});
	}

	function check_qr_response()
	{
		$.ajax(
		{
			url: script_bank_id.plugin_url + 'api.php?action=qr_check',
			type: 'POST',
			cache: false,
			dataType: 'json'
		})
		.done(function(data, textStatus)
		{
			if(data.success == 1)
			{
				update_notification('success', data.msg);

				if(typeof data.redirect !== 'undefined' && data.redirect != '')
				{
					location.href = data.redirect;
				}

				else
				{
					$("#login_loading").addClass('hide');
					$("#login_fields").removeClass('hide');
				}
			}

			else
			{
				console.log("Error: " , data.msg);

				update_notification('error', data.msg);
			}
		});
	}

	function bankid_login(user_ssn)
	{
		checkstatus = 0;

		$("#login_loading").removeClass('hide');
		$("#login_fields").addClass('hide');

		$.ajax(
		{
			url: script_bank_id.plugin_url + 'api.php?action=ssc_init&user_ssn=' + user_ssn,
			type: 'POST',
			cache: false,
			dataType: 'json'
		})
		.done(function(data, textStatus)
		{
			if(data.success == 1)
			{
				update_notification('success', data.msg);

				$("#login_loading").addClass('hide');
				$("#login_fields").removeClass('hide');
			}

			else if(data.error == 1)
			{
				update_notification('error', data.msg);

				$("#login_loading").addClass('hide');
				$("#login_fields").removeClass('hide');
			}

			else
			{
				update_notification('success', script_bank_id.open_bank_id_application_text);

				auto_launch(data.start_token, data.orderref, user_ssn);
			}
		});
	}

	function auto_launch(autostarttoken, orderref, user_ssn)
	{
		var url = 'bankid:///?autostarttoken=' + autostarttoken + '&redirect=null',
			login_iframe = $('<iframe src="' + url + '">');

		$("#loginform").append(login_iframe);

		setTimeout(function()
		{
			check_ssc_response(orderref, user_ssn);
		}, 2000);
	}

	if(script_bank_id.disable_default_login == 'yes')
	{
		$("#loginform").attr({'action': '#'});

		$("#user_login").parent("p").remove();
		$("#user_pass").parent(".wp-pwd").parent(".user-pass-wrap").remove();
		$("#loginform .forgetmenot").remove(); /*#loginform .login_or, */
	}

	$("#bankid_qr").on('click', function()
	{
		$("#login_loading").removeClass('hide');
		$("#bankid_qr").addClass('hide');

		$.ajax(
		{
			url: script_bank_id.plugin_url + 'api.php?action=qr_init',
			type: 'POST',
			cache: false,
			dataType: 'json'
		})
		.done(function(data, textStatus)
		{
			if(data.success == 1)
			{
				$("#bankid_qr").html(data.html).removeClass('flex_flow');

				$("#login_loading").addClass('hide');
				$("#bankid_qr").removeClass('hide');

				setTimeout(function()
				{
					check_qr_response();
				}, 10000);
			}

			else
			{
				update_notification('error', data.msg);

				$("#login_loading").addClass('hide');
				$("#bankid_qr").removeClass('hide');
			}
		});

		return false;
	});

	$("#loginform").on('submit', function(e)
	{
		var user_ssn = $("#user_ssn").val();

		if(user_ssn != '')
		{
			e.preventDefault();

			bankid_login(user_ssn);

			return false;
		}

		else if(script_bank_id.disable_default_login == 'yes')
		{
			e.preventDefault();

			return false;
		}
	});
});