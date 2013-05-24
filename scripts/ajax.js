jQuery(document).ready(function($) {
	list_handler();

	$('select#prefix-dropdown').change(function() {
		list_handler();
	});

	function list_handler(statustext) {
		listhtml = statustext ? statustext : '<img src="' + ajax_object.batch_plugins_dir + '/images/ajax-loader.gif" alt="Loading..." title="Loading..." />';
		var data = {
			action: 'get_response',
			site_prefix: $('select#prefix-dropdown option:selected').val()
		};

		$('#site-url').html('');
		
		if(statustext)
			$('tbody#the-list').html('<tr id="loading-ajax"><td colspan="3">' + listhtml + '</td></tr>');
		
		$.post(ajaxurl, data, function(response) {
			var responseObject = $.parseJSON(response);
			$('tbody#the-list').html(responseObject.siteplugins);
			$('#site-url').html(responseObject.siteurl);
			activation_handler();
		});

		if(statustext)
			list_handler();
	}

	function activation_handler() {
		$("a.activatebutton").click(function() {
			var act_data = {
				action: 'get_response',
				site_prefix: $('select#prefix-dropdown option:selected').val(),
				site_action: 'activate_plugin',
				plugin_path: $(this).attr('id')
			};

			$.post(ajaxurl, act_data, function(response) {
				var responseObject = $.parseJSON(response);
				$("#message-placeholder").html( '<div id="message" class="updated fade"><p>' + responseObject.message + '</p></div>' );
			});

			list_handler('Activating plugin...');

		});
	}

});