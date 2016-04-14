jQuery( document ).ready(function( $ ) {
	var resultElement = $('#plg_system_menuary');
	if (resultElement.length == 0) {
		return;
	}

	var favicon = $('link[rel="shortcut icon"]');
	var favicon_orig_attr = favicon.attr('href');
	var url = menuary_ajax_url;
	var i = 0;
	var done_code = '##done##';
	var error_code = '##error##';
	//var max_iterations = 1000;


var num = -1;
var favicons = ['/plugins/system/menuary/images/favicon1.ico','/plugins/system/menuary/images/favicon2.ico','/plugins/system/menuary/images/favicon3.ico','/plugins/system/menuary/images/favicon4.ico'];



$( "#continue" ).click(function() {
	sendAjax(url);
});
$( "#clear" ).click(function() {
	resultElement.empty();
	sendAjax(url+'&restart=1');
	i = 0;
});

	var sendAjax = function(url) {
		i++;
		resultElement.closest('.alert').css('background','#efefef url("/plugins/system/menuary/images/ajax.gif") no-repeat scroll center center');
		//favicon.attr('href','/plugins/system/menuary/images/favicon.ico');
		var interval = setInterval(function(){
			num++;
			if (num >4) {
				num = -1;
			}
			favicon.attr('href',favicons[num]);
		},500);


		$.get(url, function(response) {
			if (response.indexOf(error_code) >= 0) { // if done
				resultElement.append( ajax_helpary_message+response.replace(error_code, '')  );
			}
			else if (response.indexOf(done_code) >= 0) { // if done
				resultElement.append( response.replace(done_code, '')  );
			} else {
				//~ if (i>max_iterations) {
					//~ resultElement.append( response + 'infinite cycle');
					//~ return;
				//~ }
				resultElement.append( response );
			}
			resultElement.scrollTop(resultElement.prop("scrollHeight"));
			resultElement.closest('.alert').css('background', '');
			clearInterval(interval); // stop the interval
			favicon.attr('href',favicon_orig_attr);
			if (response.indexOf(done_code) >= 0 || response.indexOf(error_code) >= 0) {
				return true;
			}
			// resend the AJAX request by calling the sendAjax function again
			if (typeof menuarydebug === 'undefined'  || !menuarydebug) {
				sendAjax(url);
			}

		});

	};

	sendAjax(url);

});
