<?php
	//require_once 'nslg-config.php';

	$configJson = file_get_contents('nslg-config.json');
	$config = json_decode($configJson, true);
	$nameservers = $config['nameservers'];

	$qtypes = $config['qtypes'];
	asort($qtypes);

	/*
		Grab things from GET parameters to populate into form choices
		Set a default if nothing supplied by the URL
	*/

	// default nameserver
	$nsDefault = $config['defaults']['nameserver'];
	if(isset($_GET["ns"])) { $nsDefault = htmlspecialchars($_GET['ns']); }

	// default for a custom supplied nameserver
	$customNsDefault = "";
	if(isset($_GET["customns"])) { $customNsDefault = htmlspecialchars($_GET['customns']); }

	// default qname
	$qnameDefault = "";
	if(isset($_GET["qname"])) { $qnameDefault = htmlspecialchars($_GET['qname']); }

	// default qtype
	$qtypeDefault = $config['defaults']['qtype'];
	if(isset($_GET["qtype"])) { $qtypeDefault = htmlspecialchars($_GET["qtype"]); }

	// is the AD flag box ticked?
	$adflagDefault = "checked";
	if(isset($_GET["adflag"]) && $_GET["adflag"] === "false") { $adflagDefault = ""; }

	// is the RD flag box ticked?
	$rdflagDefault = "checked";
	if(isset($_GET["rdflag"]) && $_GET["rdflag"] === "false") { $rdflagDefault = ""; }

	// is the CD flag box ticked?
	$cdflagDefault = "";
	if(isset($_GET["cdflag"]) && $_GET["cdflag"] === "true") { $cdflagDefault = "checked"; }

	// is the DO flag box ticked?
	$doflagDefault = "";
	if(isset($_GET["doflag"]) && $_GET["doflag"] === "true") { $doflagDefault = "checked"; }

	// is the NSID box ticked?
	$donsidDefault = "";
	if(isset($_GET["nsid"]) && $_GET["nsid"] === "true") { $donsidDefault = "checked"; }

	// is the EDNS UDP size box ticked, and what's the default size...?
	$doednsDefault = "";
	$ednsSizeDefault = "1232";
	if(isset($_GET["edns"]) && $_GET["edns"] === "true") { $doednsDefault = "checked"; }
	if(isset($_GET["edns-size"]) && $_GET["edns-size"] > 511 && $_GET["edns-size"] < 4097) { $ednsSizeDefault = htmlspecialchars($_GET["edns-size"]); }

	// if we don't have a source address supplied, populate with the IP of the visiting client
	// ...and suitable address family
	if(preg_match("/^\d+\.\d+\.\d+\.\d+$/", $_SERVER["REMOTE_ADDR"])) {
		$ecsFamilyDefault = 1;
		$ecsSourceDefault = 24;
	} else {
		$ecsFamilyDefault = 2;
		$ecsSourceDefault = 48;
	}

	// the ECS family
	if(isset($_GET["ecsfamily"])) { $ecsFamilyDefault = htmlspecialchars($_GET['ecsfamily']); }

	// ...and the ECS source
	if(isset($_GET["ecssource"])) { $ecsSourceDefault = htmlspecialchars($_GET['ecssource']); }

	// ...and the ECS scope
	$ecsScopeDefault = "0";
	if(isset($_GET["ecsscope"])) { $ecsScopeDefault = htmlspecialchars($_GET['ecsscope']); }

	/// ...and finally the address
	$ecsAddressDefault = $_SERVER['REMOTE_ADDR'];
	if(isset($_GET["ecsaddress"])) { $ecsAddressDefault = htmlspecialchars($_GET['ecsaddress']); }
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Junesta Nameserver Looking Glass</title>
		<style>
			body {
				font-family: Tahoma, Verdana, Monaco, sans-serif;
			}
			.pre {
				/* display: block; */
				unicode-bidi: embed;
				font-family: monospace;
				white-space: pre;
			}
			form input[type="text"] {
				//text-transform: lowercase;
			}
		</style>
		<link rel="stylesheet" href="https://netdna.bootstrapcdn.com/bootstrap/3.0.3/css/bootstrap.min.css"/>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.min.js"></script>
		<script src="main.js"></script>
	</head>
	<body>
		<p>
		<div class="col-sm-6 col-sm-offset">
			<div class="panel panel-default">
				<div class="panel-heading">
					<center><h1>Junesta Nameserver Looking Glass</h1></center>
				</div>
			</div>
			<form action="" id="lgForm" class="form-horizontal">

				<!-- pick a nameserver -->
				<div id="ns-group" class="form-group">
					<label for="ns" class="control-label col-sm-3">Nameserver</label>
					<div class="col-sm-8">
						<select name="ns" id="ns" class="input-sm">
<?php
	// start of nameserver options loop
	// loop through each group, adding a <optgroup> section
	foreach($nameservers as $nsgroup) {

		/*
			If the remote address variable it set, and the group has a prefixes section set, 
			grab a library and work out if the client IP is contained in any of the permitted
			source IP ranges to determine if that's one of our allowed admin locations.
		*/

		// https://github.com/mlocati/ip-lib?tab=readme-ov-file
		// check if remote_addr is set; if not, we're running from cli or something and we wanna be able to debug
		if(isset($_SERVER['REMOTE_ADDR']) && isset($nsgroup['prefixes'])) {
			require_once './php/ip-lib/ip-lib.php';
			$permittedRanges = $nsgroup['prefixes'];
			$client = $_SERVER['REMOTE_ADDR'];
			$c = \IPLib\Factory::parseAddressString($client);
			$permittedClient = 0;
			// permittedRanges is picked up from config
			foreach($permittedRanges as $range) {
				$r = \IPLib\Factory::parseRangeString($range);
				if($r->contains($c)) {
					$permittedClient = 1;
					break;
				}
			}
		} else {
			$permittedClient = 1;
		}

		// if the group has prefixe set and the client is not in those prefixes, break to the next loop
		if($permittedClient === 0) break;

		// otherwise, output the group opening tag
		echo "\t\t\t\t\t\t\t<optgroup label=\"".$nsgroup['name']."\">\n";

		// loop through each item in the group
		asort($nsgroup['items']);
		foreach($nsgroup['items'] as $nstag => $nsitem) {
			$selected = "";
			if($nstag === $nsDefault) { $selected = " selected"; } 

			echo "\t\t\t\t\t\t\t\t<option value=\"$nstag\"$selected>".$nsitem['name']."</option>\n";
		}
	}
	// end of the nameservers loop
?>
<?php
	// if the custom option is enabled, add the group and option to the drop down
	if($config['custom']['enabled']) { ?>
							<optgroup label="Custom">
								<option value="custom"<?php if($nsDefault === "custom") echo " selected"; ?>>Custom Nameserver</option>
							</optgroup>
<?php } ?>
						</select>
					</div>
				</div>

			<!-- the custom nameserver box that will appear when we select custom from the drop down -->
			<div id="customns-group" class="form-group">
				<label for="customns" class="control-label col-sm-3">Custom Nameserver</label>
				<div class="col-sm-8">
					<input type="text" id="customns" name="customns" placeholder="Nameserver IP or hostname" class="form-control input-sm" value="<?php echo $customNsDefault; ?>">
					<span class="help-block">Custom nameserver, can be a hostname or IP address...</span>
				</div>
			</div>

			<!-- input box for qname -->
			<div id="qname-group" class="form-group">
				<label for="qname" class="control-label col-sm-3">QNAME</label>
				<div class="col-sm-8">
					<input type="text" id="qname" name="qname" placeholder="Type your qname here" required autofocus class="form-control input-lg" value="<?php echo $qnameDefault; ?>">
				</div>
			</div>

			<!-- drop down selection for qtype -->
			<div id="qtype-group" class="form-group">
				<label for="qtype" class="control-label col-sm-3">QTYPE</label>
				<div class="col-sm-8">
					<select name="qtype" id="qtype" class="input-sm">
<?php
	foreach($qtypes as $q) {
		echo "\t\t\t\t\t\t<option value=\"$q\"";
		if($q == $qtypeDefault) {
			echo " selected=\"selected\"";
		}
		echo ">$q</option>\n";
	}
?>
					</select>
				</div>
			</div>

			<!-- group for tickbox options -->
			<div id="tickbox-group" class="form-group">

				<!-- AD flag... -->
				<label for="adflag" class="control-label col-sm-3">Authenticated Data</label>
				<div class="checkbox col-sm-8">
					<input type="checkbox" id="adflag" name="adflag" value="AD" <?php echo $adflagDefault; ?>>AD</input>
				</div>

				<!-- RD flag... -->
				<label for="rdflag" class="control-label col-sm-3">Recursion Desired</label>
				<div class="checkbox col-sm-8">
					<input type="checkbox" id="rdflag" name="rdflag" value="RD" <?php echo $rdflagDefault; ?>>RD</input>
				</div>

				<!-- CD flag... -->
				<label for="cdflag" class="control-label col-sm-3">Checking Disabled</label>
				<div class="checkbox col-sm-8">
					<input type="checkbox" id="cdflag" name="cdflag" value="CD" <?php echo $cdflagDefault; ?>>CD</input>
				</div>

				<!-- DO flag... -->
				<label for="doflag" class="control-label col-sm-3">DNSSEC OK</label>
				<div class="checkbox col-sm-8">
					<input type="checkbox" id="doflag" name="doflag" value="DO" <?php echo $doflagDefault; ?>>DO</input>
				</div>

				<!-- NSID -->
				<label for="nsid" class="control-label col-sm-3">NSID</label>
				<div class="checkbox col-sm-8">
					<input type="checkbox" id="nsid" name="nsid" value="NSID" <?php echo $donsidDefault; ?>>EDNS option 3 - DNS Name Server Identifier</input>
				</div>

				<!-- EDNS UDP Size tickbox -->
				<label for="edns" class="control-label col-sm-3">EDNS UDP Size</label>
				<div class="checkbox col-sm-8">
					<input type="checkbox" id="edns" name="edns" value="EDNS UDP SIZE" <?php echo $doednsDefault; ?>>EDNS UDP Packet Size</input>
				</div>

				<!-- EDNS UDP Size value box that will appear when we tick the box... -->
				<div id="edns-group" class="form-group" style="display: none;">
					<label for="edns-size" class="control-label col-sm-3">EDNS UDP Size Value</label>
					<div class="col-sm-2">
						<input type="number" min="512" max="4096" size="4" id="edns-size" name="edns-size" placeholder="EDNS UDP SIZE VALUE" class="form-control input-sm" value="<?php echo $ednsSizeDefault; ?>">
					</div>
				</div>

				<!-- EDNS CLIENT-SUBNET tickbox -->
				<label for="ecs" class="control-label col-sm-3">EDNS CLIENT-SUBNET</label>
				<div class="checkbox col-sm-8">
					<input type="checkbox" id="ecs" name="ecs" value="EDNS CLIENT-SUBNET">EDNS option 8 - CLIENT-SUBNET</input>
				</div>

			</div>

			<!-- EDNS CLIENT-SUBNET parameters that will appear when we tick the box... -->
			<div id="ecs-group" class="form-group row">
				<div class="col-xs-2">
					<label for="ecs-family">FAMILY</label>
					<select name="ecs-family" id="ecs-family" class="input-sm">
						<option value="1"<?php if($ecsFamilyDefault === 1) echo "selected"; ?>>IPv4</option>
						<option value="2"<?php if($ecsFamilyDefault === 2) echo "selected"; ?>>IPv6</option>
					</select>
				</div>

				<!-- EDNS CLIENT-SUBNET ADDRESS -->
				<div class="col-xs-6">
					<label for="ecs-address">ADDRESS</label>
					<input type="text" pattern="^(\d+\.\d+\.\d+\.\d+|[0-9a-fA-F:]+)$" id="ecs-address" name="ecs-address" placeholder="EDNS CLIENT-SUBNET ADDRESS" class="form-control input-sm" value="<?php echo $ecsAddressDefault; ?>">
				</div>

				<!-- EDNS CLIENT-SUBNET SCOPE -->
				<div class="col-xs-2">
					<label for="ecs-scope">SCOPE</label>
					<input type="number" min="0" max="128" size="3" id="ecs-scope" name="ecs-scope" placeholder="EDNS CLIENT-SUBNET SCOPE-PREFIX-LENGTH" class="form-control input-sm" value="<?php echo $ecsScopeDefault; ?>">
				</div>

				<!-- EDNS CLIENT-SUBNET SOURCE -->
				<div class="col-xs-2">
					<label for="ecs-source">SOURCE</label>
					<input type="number" min="0" max="128" size="3" id="ecs-source" name="ecs-source" placeholder="EDNS CLIENT-SUBNET SOURCE-PREFIX-LENGTH" class="form-control input-sm" value="<?php echo $ecsSourceDefault; ?>">
				</div>
			</div>

			<!-- submit button... -->
			<div id="name-group" class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<button type="submit" class="btn btn-success">Send Query</button>
				</div>
			</div>

		</form>
		</p>
		<p>
		<div id="api" class="form-group"></div>
		<div id="query" class="form-group"></div>
		<div id="results" class="form-group"></div>
		</p>
		<div class="panel panel-default">
			<div id="footer" class="panel-heading">
				Copyright &copy; Karl Dyson 2024. All rights reserved.
			</div>
		</div>
	</body>
</html>
