#!/usr/bin/perl -w
# Author: <joao.alfredo@gmail.com>
# Modificado por: <rsilveira1987@gmail.com>
use Cyrus::IMAP::Admin;
#
# CONFIGURATION PARAMS
#
my $cyrus_server = "localhost";
my $cyrus_user = "cyrus-admin";
my $user = "cyrus-admin";
my $mechanism = "login";

if (!$ARGV[0]) {
	die "Usage: $0 <admin password> <mailbox>\n";
} else {
	$cyrus_pass = "$ARGV[0]";
	$mailbox = "$ARGV[1]";
}

print "Deletando usuario: $mailbox \n";
deletemailbox($mailbox);

sub createMailbox {
	my ($user, $domain , $subfolder) = @_;
	my $cyrus = Cyrus::IMAP::Admin->new($cyrus_server);
	$cyrus->authenticate($mechanism,'imap','',$cyrus_user,'0','10000',$cyrus_pass);
	if ($subfolder eq "INBOX") {
		$mailbox = "user/". $user . "@" . $domain;
	} else {
		$mailbox = "user/". $user ."/". $subfolder . "@" . $domain;
	}
	$cyrus->create($mailbox);
	if ($cyrus->error) {
		print STDERR "Error: ", $mailbox," ", $cyrus->error, "\n";
	} else {
		print "Mailbox $mailbox criada com êxito.\n";
	}
}

sub deletemailbox {
        ($mailbox) = @_;
        @mbxs=$imap->list("user.$mailbox*");
        my $IMAPERROR=$imap->error;
        if ( $IMAPERROR ) {
           print " Error occurred listing mailboxes: $IMAPERROR\n";
           exit;
        }
        if ( scalar(@mbxs) == 0 ) {
                print " Error: no mailboxes found for user $mailbox\n";
                exit;
        }
        # step through mailboxes
        foreach $mbox (reverse @mbxs) {

                # Give ourselve permissions to mailbox
                $result=$imap->setacl($mbox->[0],"$imapauthuser","c");
                $IMAPERROR=$imap->error;
                if ( $IMAPERROR ) {
                   print " Error occurred setting mailbox acls: $IMAPERROR\n";
                   exit;
                }

                # Now delete mailbox
                $result=$imap->delete($mbox->[0]);
                $IMAPERROR=$imap->error;
                if ( $IMAPERROR ) {
                   print " Error occurred deleting $mbox->[0]: $IMAPERROR\n";
                   exit;
                }
                else {
                        print " deleted $mbox->[0]\n";
                }
        }
}
