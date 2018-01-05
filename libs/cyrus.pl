#!/usr/bin/perl -w
# Author: <joao.alfredo@gmail.com>
use Cyrus::IMAP::Admin;
#
# CONFIGURATION PARAMS
#
my $cyrus_server = "localhost";
my $cyrus_user = "cyrus-admin";
my $user = "cyrus-admin";
my $mechanism = "login";

if (!$ARGV[0]) {
die "Usage: $0 [admin password]\n";
} else {
$cyrus_pass = "$ARGV[0]";
$user = "$ARGV[1]";
}

print "Criando usuario: $user. \n";
createMailbox($user,'INBOX');
createMailbox($user,'Sent');
createMailbox($user,'Trash');
createMailbox($user,'Draft');
sub createMailbox {
my ($user, $subfolder) = @_;
my $cyrus = Cyrus::IMAP::Admin->new($cyrus_server);
$cyrus->authenticate($mechanism,'imap','',$cyrus_user,'0','10000',$cyrus_pass);
if ($subfolder eq "INBOX") {
$mailbox = "user/". $user;
} else {
$mailbox = "user/". $user ."/". $subfolder;
}

$cyrus->create($mailbox);
if ($cyrus->error) {
print STDERR "Error: ", $mailbox," ", $cyrus->error, "\n";
} else {
print "Mailbox $mailbox criada com êxito.\n";
}

}
