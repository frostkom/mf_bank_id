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

	function update_response(orderref, user_ssn)
	{
		$.ajax(
		{
			url: script_bank_id.plugin_url + 'api.php?action=check&orderref=' + orderref,
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
					update_response(orderref, user_ssn);
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

	function bankid_login(user_ssn)
	{
		checkstatus = 0;

		$("#login_loading").removeClass('hide');
		$("#login_fields").addClass('hide');

		$.ajax(
		{
			url: script_bank_id.plugin_url + 'api.php?action=init&user_ssn=' + user_ssn,
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

				/*if(script_bank_id.bank_id_v2 == 'yes')
				{*/
					auto_launch_v2(data.start_token, data.orderref, user_ssn);
				/*}

				else
				{
					auto_launch(data.start_token, data.orderref, user_ssn);
				}*/
			}
		});
	}

	function auto_launch_v2(autostarttoken, orderref, user_ssn)
	{
		var url = 'bankid:///?autostarttoken=' + autostarttoken + '&redirect=null',
			login_iframe = $('<iframe src="' + url + '">');

		$("#loginform").append(login_iframe);

		setTimeout(function()
		{
			update_response(orderref, user_ssn);
		}, 2000);
	}

	/*function auto_launch(autostarttoken, orderref, user_ssn)
	{
		var url = 'bankid:///?autostarttoken=' + autostarttoken + '&redirect=null',
			timer,
			heartbeat,
			iframe_timer;

		activate();

		function activate()
		{
			heartbeat = setInterval(intervalHeartbeat, 200);

			if(navigator.userAgent.match(/Safari/))
			{
				tryWebkitApproach();

				iframe_timer = setTimeout(function ()
				{
					tryIframeApproach();
				}, 1500);
			}

			else if(navigator.userAgent.match(/Chrome/))
			{
				useIntent();
			}

			else if(navigator.userAgent.match(/Firefox/))
			{
				tryWebkitApproach();

				iframe_timer = setTimeout(function ()
				{
					tryIframeApproach();
				}, 1500);
			}

			else
			{
				tryIframeApproach();
			}
		}

		function clearTimers()
		{
			clearTimeout(timer);
			clearTimeout(heartbeat);
			clearTimeout(iframe_timer);
		}

		function intervalHeartbeat()
		{
			if(document.webkitHidden || document.hidden)
			{
				clearTimers();
			}
		}

		function tryIframeApproach()
		{
			var iframe = document.createElement('iframe');
			iframe.style.border = 'none';
			iframe.style.width = '1px';
			iframe.style.height = '1px';

			iframe.onload = function()
			{
				if(autostarttoken != null)
				{
					window.location = 'results.php?orderref=' + orderref;
				}
			};

			if(autostarttoken != null)
			{
				iframe.src = url;
				document.body.appendChild(iframe);
			}

			setTimeout(function()
			{
				update_response(orderref, user_ssn);
			}, 10000);
		}

		function tryWebkitApproach()
		{
			if(autostarttoken != null)
			{
				window.location = url;
			}

			setTimeout(function()
			{
				update_response(orderref, user_ssn);
			}, 10000);
		}

		function useIntent()
		{
			window.location = 'intent://?autostarttoken=' + autostarttoken+'&redirect=null#Intent;scheme=bankid;package=com.bankid.bus;end';

			setTimeout(function()
			{
				update_response(orderref, user_ssn);
			}, 10000);
		}
	}*/

	if(script_bank_id.disable_default_login == 'yes')
	{
		$("#loginform").attr({'action': '#'});

		$("#user_login").parent("p").remove();
		$("#user_pass").parent(".wp-pwd").parent(".user-pass-wrap").remove();
		$("#loginform .login_or, #loginform .forgetmenot").remove();
	}

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