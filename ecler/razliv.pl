#!/usr/bin/perl
$\=$/;
$al=`curl http://6.0.0.1 2>/dev/null`;
@ips = $al=~/(\d+\.\d+\.\d+\.\d+)/g;
print join(",",@ips);
print scalar(@ips);

for $ip(@ips){
	print $ip;
	$adr = 'root@'.$ip;
	`scp -o "StrictHostKeyChecking no" server.py $adr:/home/cryptobulki/`;
	`ssh -o "StrictHostKeyChecking no" $adr 'service cryptobulki restart'`;
	print `ssh -o "StrictHostKeyChecking no" $adr 'service cryptobulki status'`;
}
