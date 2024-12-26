jQuery(function($)
{
	var dom_obj_form = $("#loginform");

	if($("body > #wrapper").length > 0)
	{
		var dom_obj_username = $("#user_login").parent(".form_textfield"),
			dom_obj_password = $("#user_pass").parent(".form_password"),
			dom_obj_remember = dom_obj_form.find("#rememberme").parent(".form_checkbox"),
			dom_obj_submit = dom_obj_form.find(".form_button, .wp-block-button"),
			dom_obj_forgot_password_link = dom_obj_form.next("p#lost_password_link");
	}

	else
	{
		var dom_obj_username = $("#user_login").parent("p"),
			dom_obj_password = $("#user_pass").parent(".wp-pwd").parent(".user-pass-wrap"),
			dom_obj_remember = dom_obj_form.find(".forgetmenot"),
			dom_obj_submit = dom_obj_form.find(".submit"),
			dom_obj_forgot_password_link = dom_obj_form.next("p#nav");
	}

	var dom_obj_choice = dom_obj_form.children("#login_choice"),
		dom_obj_or = dom_obj_form.children(".login_or"),
		dom_obj_fields = dom_obj_form.children("#login_ssn"),
			dom_obj_user_ssn = dom_obj_fields.find("#user_ssn"),
		dom_obj_qr = dom_obj_form.children("#login_qr"),
		dom_obj_connected = dom_obj_form.children("#login_connected"),
		dom_obj_loading = dom_obj_form.children("#login_loading"),
		dom_obj_notification = dom_obj_form.children("#notification"),
		checkstatus = 0,
		checkstatus_limit = 7,
		timeout_time = 3000;

	function update_notification(type, message)
	{
		dom_obj_notification.addClass('hide');

		if(typeof message !== 'undefined' && message != 'undefined' && message != '')
		{
			switch(type)
			{
				case 'success':
					dom_obj_notification.html("<div class='success updated'>" + message + "</div>").removeClass('hide');
				break;

				case 'error':
					dom_obj_notification.html("<div id='login_error' class='error'><p>" + message + "</p></div>").removeClass('hide');
				break;

				/*default:
					console.log("Unknown Type: " , type);
				break;*/
			}
		}
	}

	function display_loading()
	{
		dom_obj_loading.removeClass('hide');

		dom_obj_or.addClass('hide');
		dom_obj_fields.addClass('hide');
		dom_obj_qr.addClass('hide');
		dom_obj_connected.addClass('hide');
		dom_obj_notification.addClass('hide');
	}

	function reset_form_on_error()
	{
		dom_obj_or.removeClass('hide');
		dom_obj_fields.removeClass('hide');
		dom_obj_qr.removeClass('hide');
		dom_obj_connected.removeClass('hide');
	}

	function check_ssc_response(orderref, user_ssn)
	{
		$.ajax(
		{
			url: script_bank_id.plugin_url + 'api/?action=ssc_check&login_type=' + script_bank_id.login_type + '&orderref=' + orderref,
			type: 'POST',
			cache: false,
			dataType: 'json'
		})
		.done(function(data, textStatus)
		{
			if(data.success == 1)
			{
				checkstatus = 0;

				dom_obj_loading.addClass('hide');

				update_notification('success', data.msg);

				if(typeof data.redirect !== 'undefined' && data.redirect != '')
				{
					location.href = data.redirect;
				}

				else
				{
					reset_form_on_error();
				}
			}

			else if(data.retry == 1 && checkstatus < checkstatus_limit)
			{
				checkstatus++;

				setTimeout(function()
				{
					check_ssc_response(orderref, user_ssn);
				}, timeout_time);
			}

			else if(data.error == 1 && checkstatus >= checkstatus_limit)
			{
				dom_obj_loading.addClass('hide');

				update_notification('error', script_bank_id.took_too_long_text);

				reset_form_on_error();
			}

			else if(typeof data.msg !== 'undefined')
			{
				update_notification('error', data.msg);

				reset_form_on_error();
			}
		});
	}

	function bankid_login(user_ssn)
	{
		checkstatus = 0;

		display_loading();

		$.ajax(
		{
			url: script_bank_id.plugin_url + 'api/?action=ssc_init&user_ssn=' + user_ssn,
			type: 'POST',
			cache: false,
			dataType: 'json'
		})
		.done(function(data, textStatus)
		{
			if(data.success == 1)
			{
				dom_obj_loading.addClass('hide');

				update_notification('success', data.msg);
			}

			else if(data.error == 1)
			{
				dom_obj_loading.addClass('hide');

				update_notification('error', data.msg);

				reset_form_on_error();
			}

			else
			{
				update_notification('success', data.msg);

				auto_launch(data.start_token, data.orderref, user_ssn);
			}
		});
	}

	function auto_launch(autostarttoken, orderref, user_ssn)
	{
		var url = 'bankid:///?autostarttoken=' + autostarttoken + '&redirect=null',
			login_iframe = $('<iframe src="' + url + '"></iframe>');

		dom_obj_form.append(login_iframe);

		setTimeout(function()
		{
			check_ssc_response(orderref, user_ssn);
		}, timeout_time);
	}

	if(dom_obj_choice.length == 0)
	{
		$(".login_actions").addClass('hide');
	}

	if(script_bank_id.allow_username_login == false)
	{
		dom_obj_form.attr({'action': '#'});

		dom_obj_username.addClass('hide');
		dom_obj_password.addClass('hide');
		dom_obj_remember.addClass('hide');
		dom_obj_submit.addClass('hide');
		dom_obj_forgot_password_link.addClass('hide');
	}

	dom_obj_form.on('submit', function(e)
	{
		if(dom_obj_fields.length > 0)
		{
			var user_ssn = dom_obj_user_ssn.val();

			if(user_ssn != '')
			{
				e.preventDefault();

				bankid_login(user_ssn);

				return false;
			}

			else if(script_bank_id.allow_username_login == false)
			{
				e.preventDefault();

				return false;
			}
		}
	});

	/* Choice */
	if(dom_obj_choice.length > 0)
	{
		dom_obj_username.addClass('hide');
		dom_obj_password.addClass('hide');
		dom_obj_remember.addClass('hide');
		dom_obj_submit.addClass('hide');
		dom_obj_or.addClass('hide');
		dom_obj_fields.addClass('hide');
		dom_obj_user_ssn.addClass('hide');
		dom_obj_qr.addClass('hide');
		dom_obj_connected.addClass('hide');
	}

	dom_obj_choice.children(".login_choice_bankid").on('click', function()
	{
		dom_obj_choice.addClass('hide');

		if(dom_obj_fields.length > 0)
		{
			dom_obj_remember.removeClass('hide');
			dom_obj_submit.removeClass('hide');
		}

		dom_obj_or.removeClass('hide');
		dom_obj_fields.removeClass('hide');
		dom_obj_user_ssn.removeClass('hide');
		dom_obj_qr.removeClass('hide');
		dom_obj_connected.removeClass('hide');

		return false;
	});

	dom_obj_choice.children(".login_choice_username").on('click', function()
	{
		dom_obj_choice.addClass('hide');

		dom_obj_username.removeClass('hide');
		dom_obj_password.removeClass('hide');
		dom_obj_remember.removeClass('hide');
		dom_obj_submit.removeClass('hide');

		return false;
	});

	/* QR */
	if(dom_obj_qr.length > 0)
	{
		function check_qr_response()
		{
			$.ajax(
			{
				url: script_bank_id.plugin_url + 'api/?action=qr_check&login_type=' + script_bank_id.login_type,
				type: 'POST',
				cache: false,
				dataType: 'json'
			})
			.done(function(data, textStatus)
			{
				if(data.success == 1)
				{
					checkstatus = 0;

					dom_obj_loading.addClass('hide');

					update_notification('success', data.msg);

					if(typeof data.redirect !== 'undefined' && data.redirect != '')
					{
						location.href = data.redirect;
					}

					else
					{
						reset_form_on_error();
					}
				}

				else if(data.retry == 1 && checkstatus < checkstatus_limit)
				{
					checkstatus++;

					if(data.html)
					{
						dom_obj_qr.html(data.html);
					}

					setTimeout(function()
					{
						check_qr_response();
					}, timeout_time);
				}

				else if(data.error == 1 && checkstatus >= checkstatus_limit)
				{
					dom_obj_loading.addClass('hide');

					update_notification('error', script_bank_id.took_too_long_text);

					reset_form_on_error();
				}

				else if(typeof data.msg !== 'undefined')
				{
					update_notification('error', data.msg);

					reset_form_on_error();
				}
			});
		}

		dom_obj_qr.on('click', function()
		{
			display_loading();

			$.ajax(
			{
				url: script_bank_id.plugin_url + 'api/?action=qr_init',
				type: 'POST',
				cache: false,
				dataType: 'json'
			})
			.done(function(data, textStatus)
			{
				dom_obj_loading.addClass('hide');

				if(data.success == 1)
				{
					dom_obj_qr.html(data.html).removeClass('flex_flow hide');

					setTimeout(function()
					{
						check_qr_response();
					}, timeout_time);
				}

				else
				{
					update_notification('error', data.msg);

					reset_form_on_error();
				}
			});

			return false;
		});
	}

	/* Same Device */
	if(dom_obj_connected.length > 0)
	{
		function check_connected_response()
		{
			$.ajax(
			{
				url: script_bank_id.plugin_url + 'api/?action=connected_check&login_type=' + script_bank_id.login_type,
				type: 'POST',
				cache: false,
				dataType: 'json'
			})
			.done(function(data, textStatus)
			{
				if(data.success == 1)
				{
					checkstatus = 0;

					dom_obj_loading.addClass('hide');

					update_notification('success', data.msg);

					if(typeof data.redirect !== 'undefined' && data.redirect != '')
					{
						location.href = data.redirect;
					}

					else
					{
						reset_form_on_error();
					}
				}

				else if(data.retry == 1 && checkstatus < checkstatus_limit)
				{
					checkstatus++;

					setTimeout(function()
					{
						check_connected_response();
					}, timeout_time);
				}

				else if(data.error == 1 && checkstatus >= checkstatus_limit)
				{
					dom_obj_loading.addClass('hide');

					update_notification('error', script_bank_id.took_too_long_text);

					reset_form_on_error();
				}

				else if(typeof data.msg !== 'undefined')
				{
					update_notification('error', data.msg);

					reset_form_on_error();
				}
			});
		}

		dom_obj_connected.children("span").on('click', function()
		{
			display_loading();

			$.ajax(
			{
				url: script_bank_id.plugin_url + 'api/?action=connected_init',
				type: 'POST',
				cache: false,
				dataType: 'json'
			})
			.done(function(data, textStatus)
			{
				dom_obj_loading.addClass('hide');

				if(data.success == 1)
				{
					dom_obj_connected.html(data.html).removeClass('hide');

					setTimeout(function()
					{
						check_connected_response();
					}, timeout_time);
				}

				else
				{
					update_notification('error', data.msg);

					reset_form_on_error();
				}
			});

			return false;
		});
	}
});