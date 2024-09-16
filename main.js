/*
Author: Karl Dyson
Copyright 2024 Karl Dyson. All rights reserved.

main.js deals with the various page actions and ajax call needed for the operation of the nameserver looking glass page

*/

$(document).ready(function () {

	// deal with the form being submitted
	$("form").submit(function (event) {

		// collect all the data from the form
		var formData = {
			op: 'dig',
			ns: $("#ns").val(),
			customns: $("#customns").val(),
			qname: $("#qname").val(),
			qtype: $("#qtype").val(),
			adflag: $("#adflag").is(":checked"),
			rdflag: $("#rdflag").is(":checked"),
			cdflag: $("#cdflag").is(":checked"),
			doflag: $("#doflag").is(":checked"),
			nsid: $("#nsid").is(":checked"),
			edns: $("#edns").is(":checked"),
			ednssize: $("#edns-size").val(),
			ecs: $("#ecs").is(":checked"),
			ecsfamily: $("#ecs-family").val(),
			ecsaddress: $("#ecs-address").val(),
			ecsscope: $("#ecs-scope").val(),
			ecssource: $("#ecs-source").val(),
		};

		//console.log(formData);

		// variables for the uri modifcation
		var uri = [];
		var l = 0;

		// add relevant variables to the url if they differ from the defaults
		if (formData.ns != 'lon') uri[l++] = 'ns=' + formData.ns;
		if (formData.ns === 'custom') uri[l++] = 'customns=' + formData.customns;
		if (formData.qname != undefined) uri[l++] = 'qname=' + formData.qname;
		if (formData.qtype != undefined) uri[l++] = 'qtype=' + formData.qtype;
		if (formData.adflag === false) uri[l++] = 'adflag=false';
		if (formData.rdflag === false) uri[l++] = 'rdflag=false';
		if (formData.cdflag === true) uri[l++] = 'cdflag=true';
		if (formData.doflag === true) uri[l++] = 'doflag=true';
		if (formData.nsid === true) uri[l++] = 'nsid=true';
		if (formData.edns === true && formData.ednssize != "") {
			uri[l++] = 'edns=true';
			uri[l++] = 'ednssize=' + formData.ednssize;
		}
		if (formData.ecs === true && formData.ecsfamily != "" && formData.ecsaddress != "" && formData.ecsscope != "" && formData.ecssource != "") {
			uri[l++] = 'ecs=true';
			uri[l++] = 'ecsfamily=' + formData.ecsfamily;
			uri[l++] = 'ecsaddress=' + formData.ecsaddress;
			uri[l++] = 'ecsscope=' + formData.ecsscope;
			uri[l++] = 'ecssource=' + formData.ecssource;
		}

		// build the uri string
		var uriString = uri.join('&');

		// replace the uri string in the address bar. pondering adding to the history but need to tinker with "back" button behaviour
		window.history.replaceState(null, "", "?" + uriString);

		// stick the spinner in the page while we wait
		$("#api").html('<img src="loading.gif" alt="spinny thing while we wait for the api response...">');

		// .. and hide the other two (in case we're re-submitting a new query and want the shown divs to hide again)
		$("#query").hide();
		$("#results").hide();

		$.ajaxSetup({
			timeout: 5000
		});

		// start time for the api call
		var startTime = performance.now()

		// make the ajax call
		$.ajax({
			type: "POST",
			url: "nslg.cgi",
			data: formData,
			dataType: "json",
			encode: true,
		}).done(function (data) {

			// how long did it take?
			var endTime = performance.now()
			var apiTime = (endTime - startTime).toFixed(2);

			//console.log(data);

			// did we get an error back in the json?
			if(data.error) {
				$("#api").html("Error:<br><br>" + data.error);
			}

			// did we get an error response from the dns lookup?
			else if(data.response.error) {
				$("#api").html("<pre>Got an error from " + data.query.nsname + " in " + apiTime + " msec :: " + data.response.error + "</pre>");
			}

			// display the results in the appropriate elements
			else {
				var nsidtext = "";
				if(data.response.nsid != undefined) nsidtext = "NSID: " + data.response.nsid + "\n";	
				$("#api").html("<pre>Results received from " + data.query.nsname + " in " + apiTime + " msec</pre>");
				$("#query").html("<pre>QUERY SENT:\n\n" + data.query.string + "Nameservers: " + data.query.nameservers + "</pre>").show();
				$("#results").html("<pre>DNS response received in " + data.response.querytime + " msec\n\n" + data.response.output + nsidtext + "</pre>").show();
			}
		}).fail(function(request, status, error) {
			$("#api").html("<pre>An error occurred talking to the API\n\nResponse: " + request.status + "\nStatus: " + status + "\nError: " + error + "</pre>");
		});

		event.preventDefault();
	});

	// function handles showing the custom nameserver input field if the custom option is selected from the drop down
	$(function() {
		var nsdd = $('#ns'),
			onChange = function(event) {
				if($(this).val() === 'custom') {
					$('#customns-group').show();
					$('#customns').focus().select();
					$('#customns').attr('required', '');
				} else {
					$('#customns-group').hide();
					$('#customns').removeAttr('required', '');
				}
			};
		onChange.apply(nsdd.get(0));
		nsdd.change(onChange);
	});

	// this function handles showing the EDNS client-subnet related fields if we tick the checkbox on the form
	$(function() {
		var ecstb = $('#ecs'),
			onChange = function(event) {
				if($(this).is(':checked')) {
					$('#ecs-group').show();
					$('#ecs-address').focus().select();
				} else {
					$('#ecs-group').hide();
				}
			};
		onChange.apply(ecstb.get(0));
		ecstb.change(onChange);
	});

	// this function handles showing the EDNS UDP size option input if we tick the checkbox on the form
	$(function() {
		var ednstb = $('#edns'),
			onChange = function(event) {
				if($(this).is(':checked')) {
					$('#edns-group').show();
					$('#edns-size').focus().select();
				} else {
					$('#edns-group').hide();
				}
			};
		onChange.apply(ednstb.get(0));
		ednstb.change(onChange);
	});
});

