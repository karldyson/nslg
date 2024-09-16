<?php

$json = file_get_contents('nslg-config.json');

if($json === false) {
	echo "error reading JSON\n";
}

$config = json_decode($json, true);

if($config === null) {
	echo "error decoding JSON\n";
}

print_r($config);

foreach($config['nameservers'] as $nsgroup) {
	echo "group: " . $nsgroup['name'] . "\n";

	if(isset($nsgroup['prefixes'])) {
		echo "  -- would only output this group if client is in one of the prefixes: ".join(", ", $nsgroup['prefixes'])."\n";
	}

	asort($nsgroup['items']);
	foreach($nsgroup['items'] as $nstag => $nsitem) {
		echo "\tns tag is $nstag\n";
		echo "\t\tname is " . $nsitem['name'] . "\n";
		echo "\t\thost is " . $nsitem['host'] . "\n";
	}
}
if($config['custom']['enabled'] == true) {
	echo "would output the custom option\n";
} else {
	echo "would NOT output the custom option\n";
}
echo "the default nameserver will be ".$config['defaults']['nameserver']."\n";

?>

