#!usr/bin/perl

$|++;

use strict;
use JSON;

my $json;
open(C, '<', 'nslg-config.json') || die "file open fail: $!\n";
while(<C>) {
	chomp;
	$json .= $_;
}
close C;

print "json is [$json]\n";

my $data = decode_json($json) || die "failed to parse JSON\n";

use Data::Dumper;
print Dumper($data);

print "OK\n";
