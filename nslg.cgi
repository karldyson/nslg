#!/usr/bin/perl

use strict;
use Net::DNS;
use CGI;
use JSON;
use Time::HiRes qw/time/;
use HTML::Entities;

# initiate a CGI object instance
my $c = new CGI;

# initiate a JSON object
my $json = JSON->new;

# this only outputs JSON back to the webpage AJAX call, so output the header
print "Content-Type: application/json\r\n";
print "\r\n";

# read the config file
my $configJson;
if(open(C, '<', 'nslg-config.json')) {
	while(<C>) {
		chomp;
		$configJson .= $_;
	}
	close C;
} else {
	my $data;
	$data->{error} = 'internal server error';
	print $json->pretty->encode($data);
	exit;
}

# decode the json into the config object
my $config = decode_json($configJson);

# set up an array of the nameservers. move this to JSON config file?
my $nameservers = $config->{nameservers};

# turn the nameservers into a hash structure indexed on the tag
# because that's what we will get from the webpage in the ajax call
my $nstags;
for my $nsg (@$nameservers) {
	for my $nsi (keys %{$nsg->{items}}) {
		$nstags->{$nsi} = $nsg->{items}->{$nsi};
	}
}

# set up an array of supported qtypes. move this to config?
my $qtypes = $config->{qtypes};

# grab the operation being requested
my $op = $c->param('op');

# we're being asked to do a dns query, so gather the parameters
if($op eq 'dig') {
	my $ns = $c->param('ns');
	my $customns = $c->param('customns');
	my $qname = $c->param('qname');
	my $qtype = $c->param('qtype');
	my($adflag) = ($c->param('adflag') eq 'true');
	my($rdflag) = ($c->param('rdflag') eq 'true');
	my($cdflag) = ($c->param('cdflag') eq 'true');
	my($doflag) = ($c->param('doflag') eq 'true');
	my($donsid) = ($c->param('nsid') eq 'true');
	my($doedns) = ($c->param('edns') eq 'true');
	my $ednsSize = $c->param('ednssize');
	my($doecs) = ($c->param('ecs') eq 'true');
	my $ecsFamily = $c->param('ecsfamily');
	my $ecsAddress = $c->param('ecsaddress');
	my $ecsScope = $c->param('ecsscope');
	my $ecsSource = $c->param('ecssource');

	# if the custom option is enabled, set the name and nameservers, or error
	if($config->{custom}->{enabled} && $ns eq 'custom') {
		$nstags->{custom}->{name} = "Custom Nameserver";
		if($customns) {
			$nstags->{custom}->{host} = $customns;
		} else {
			my $data->{error} = "custom nameserver selected, but no nameserver supplied";
			print $json->pretty->encode($data);
			exit;
		}
	}

	# initialise the resolver option and default parameters
	my $resolver = new Net::DNS::Resolver;
	$resolver->tcp_timeout(5);
	$resolver->udp_timeout(5);
	$resolver->retry(1);
	$resolver->retrans(2);

	# set the nameservers if the ns tag is valid
	if($nstags->{$ns}) {
		$resolver->nameservers($nstags->{$ns}->{host});
	}
	# reply with an error if the nameserver is not a valid option
	else {
		my $data->{error} = "$ns is an unknown nameserver";
		print $json->pretty->encode($data);
		exit;
	}

	# reply with an error if the qtype is not a valid option
	unless(grep /^$qtype$/, @$qtypes) {
		my $data->{error} = "$qtype is an unsupported qtype";
		print $json->pretty->encode($data);
		exit;
	}

	# initialise the data structured that will be JSON encoded and returned
	# start by populating with query related parameters we received
	my $data;
	$data->{query}->{ns} = $ns;
	$data->{query}->{nsname} = $nstags->{$ns}->{name};
	$data->{query}->{qname} = $qname;
	$data->{query}->{qtype} = $qtype;
	$data->{query}->{adflag} = $adflag;
	$data->{query}->{rdflag} = $rdflag;
	$data->{query}->{cdflag} = $cdflag;
	$data->{query}->{doflag} = $doflag;
	$data->{query}->{donsid} = $donsid;
	$data->{query}->{edns}->{flag} = $doedns;
	$data->{query}->{edns}->{size} = $ednsSize;
	$data->{query}->{ecs}->{flag} = $doecs;
	$data->{query}->{ecs}->{family} = $ecsFamily;
	$data->{query}->{ecs}->{address} = $ecsAddress;
	$data->{query}->{ecs}->{scope} = $ecsScope;
	$data->{query}->{ecs}->{source} = $ecsSource;

	# add selected nameserver to the query section of the returned data
	$data->{query}->{nameservers} = join(", ", $resolver->nameservers());

	# set the received flags in the resolver object
	$resolver->adflag($adflag);
	$resolver->recurse($rdflag);
	$resolver->cdflag($cdflag);
	$resolver->dnssec($doflag);
	
	# we need a sneaky . for the query if the qname doesn't have one on the end
	my $qn = $qname;
	$qn .= '.' unless $qn =~ m/\.$/;
	
	# create the question section from the parameters
	my $question = Net::DNS::Question->new($qn, $qtype, 'IN');

	# ...and the query packet
	my $packet = Net::DNS::Packet->new;

	# add the question to the query packet...
	$packet->push(question => $question);

	# ...and set the header flags...
	$packet->header->ad($adflag);
	$packet->header->rd($rdflag);
	$packet->header->cd($cdflag);
	$packet->header->do($doflag);
	
	# set the nsid option
	$packet->edns->option('NSID' => { 'OPTION-DATA' => '' }) if $donsid;

	# ...and the edns option
	$packet->edns->size($ednsSize) if $doedns;

	# set the edns client-subnet options
	if($doecs) {
		$packet->edns->option('CLIENT-SUBNET' => {
				'FAMILY' => $ecsFamily,
				'ADDRESS' => $ecsAddress,
				'SCOPE-PREFIX-LENGTH' => $ecsScope,
				'SOURCE-PREFIX-LENGTH' => $ecsSource
			});
	}

	# query packet into the returned data structure	
	$data->{query}->{string} = encode_entities($packet->string);

	# ...and grab the start time (in high def, as we included Time::HiRes)
	my $startTime = time();

	# ...and send the query!
	my $response = $resolver->send($packet);

	# how long did it take?
	my $queryTime = time() - $startTime;

	# did we get a response? if so, chuck it and various useful info in the response structure
	if($response) {
		$data->{response}->{nsid} = $response->edns->option('NSID');
		$data->{response}->{querytime} = sprintf("%.2f", $queryTime * 1000);
		$data->{response}->{querytimesec} = sprintf("%.4f", $queryTime);
		chomp($data->{response}->{output} = encode_entities($response->string));
		# note the use of encode_entities in the above we don't trust what's in DNS
	}
	# if not, chuck some error/diag in the response structure
	else {
		$data->{response}->{querytime} = sprintf("%.2f", $queryTime * 1000);
		$data->{response}->{querytimesec} = sprintf("%.4f", $queryTime);
		chomp($data->{response}->{error} = encode_entities($resolver->errorstring));
	}

	# encode the data structure to JSON and print it (so it'll get returned to the page)
	print $json->pretty->encode($data);
	exit;
}
# invalid operation, so return an error
else {
	my $data->{error} = "$op is an unknown operation type";
	print $json->pretty->encode($data);
	exit;
}
