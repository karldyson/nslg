# nslg - Nameserver Looking Glass

## Introduction

Since I started running a set of anycast DNS nodes, one of the things I wanted to do, mostly as an exercise,
was to write a looking glass that would allow me to see what any one of the nodes thought about a given name.

It's possible to send queries with a number of options set or unset, as follows:

* AD
* RD
* CD
* DO
* NSID
* EDNS UDP Size (which will default to 1232)
* EDNS CLIENT-SUBNET (which will default to your client IP)

They all default off/unticked apart from AD and RD.

## Installation

Copy the files to a directory on your webserver.

There's nothing particularly elaborate here other than your webserver need to run both PHP and cgi.

This has been tested on Debian 11.11 with:

* Apache 2.4
* PHP 7.4
* Perl 5.32

You also need the following perl modules. I've included the version installed on my system that this is tested with.

* Net::DNS (1.29) (libnet-dns-perl)
* Net::DNS::DNSSEC (1.18) (libnet-dns-sec-perl)
* Time::HiRes (1.9764) (libtime-hires-perl)
* JSON (4.03) (libjson-perl)
* CGI (4.51)
* HTML::Entities (3.75)

If you want to be able to use the private hosts/prefixes feature, you need to grab ip-lib for php.

https://github.com/mlocati/ip-lib?tab=readme-ov-file

I've tested witih 1.18

## Configuration

There's an example config file in the repo.

There are also two config checkers in the repo, one that replicates how php reads the config, the other how perl reads it.

You can add arrays of groups (so they come out in the order you want).

The lists of items within each group will be output in alphabetic order by tag.

Each group can be protected and only offered to clients coming from given source network prefixes, as seen in the example.

Place prefixes that will be used more often nearer the start of the list, as the parsing/checking exits the checking loop on match.

If the custom option is enabled, a suitable option and group will be added to the drop down permitting the user to enter any
ip or hostname in the form.

## ToDo

 * add the IP protection for group(s) to nslg.cgi as we're relying on a client not knowing others may exist
 * make response output links to "retry this query with this ns or this label instead"

## Bug & Security Reporting

Please use the issues feature.

## Licence and Copyright

This code is Copyright (c) 2024 Karl Dyson.

All rights reserved.

## Warranty

There's no warranty that this code is safe, secure, or fit for any purpose.

I'm not responsible if you don't read the code, check its suitability for your
use case, and wake up to find it's eaten your cat...
