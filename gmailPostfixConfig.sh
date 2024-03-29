#!/bin/bash
echo "Setup of sendmail/postfix to use gmail account to send emails"

HOST=$(hostname)

echo | sudo debconf-set-selections <<__EOF
postfix	postfix/root_address	string	
postfix	postfix/rfc1035_violation	boolean	false
postfix	postfix/mydomain_warning	boolean	
postfix	postfix/mynetworks	string	127.0.0.0/8 [::ffff:127.0.0.0]/104 [::1]/128
postfix	postfix/mailname	string	$HOST
postfix	postfix/tlsmgr_upgrade_warning	boolean	
postfix	postfix/recipient_delim	string	+
postfix	postfix/main_mailer_type	select	Internet with smarthost
postfix	postfix/destinations	string	$HOST, localhost.localdomain, localhost
postfix	postfix/retry_upgrade_warning	boolean	
# Install postfix despite an unsupported kernel?
postfix	postfix/kernel_version_warning	boolean	
#postfix	postfix/not_configured	error	
postfix	postfix/sqlite_warning	boolean	
postfix	postfix/mailbox_limit	string	0
postfix	postfix/relayhost	string	[smtp.gmail.com]:587
postfix	postfix/procmail	boolean	false
#postfix	postfix/bad_recipient_delimiter	error	
postfix	postfix/protocols	select	all
postfix	postfix/chattr	boolean	false
__EOF


if ! dpkg -s postfix >/dev/null;
then
   echo "Postfix should be configured as Internet site with smarthost"
   sudo apt-get install -q -y postfix
else
  echo "Postfix is already installed."
  echo "You may consider removing it before running the script."
  echo "You may do so with the following command:"
  echo "sudo apt-get purge postfix"
  echo
fi

GMAIL_USER=""
GMAIL_PASS=""

if [ -z "$GMAIL_USER" ]; then echo "No gmail username given. Exiting."; exit -1; fi
if [ -z "$GMAIL_PASS" ]; then echo "No gmail password given. Exiting."; exit -1; fi

if ! [ -f /etc/postfix/main.cf.original ]; then
  sudo cp /etc/postfix/main.cf /etc/postfix/main.cf.original
fi

sudo tee /etc/postfix/main.cf >/dev/null <<__EOF
relayhost = [smtp.gmail.com]:587
inet_protocols=ipv4
smtp_use_tls = yes
smtp_sasl_auth_enable = yes
smtp_sasl_password_maps = hash:/etc/postfix/sasl_passwd
smtp_sasl_security_options = noanonymous
smtp_tls_loglevel = 1
smtp_tls_per_site = hash:/etc/postfix/tls_per_site
smtp_tls_CAfile = /etc/ssl/certs/Equifax_Secure_CA.pem
smtp_tls_session_cache_database = btree:/var/lib/postfix/smtp_tls_session_cache
__EOF

echo "[smtp.gmail.com]:587 $GMAIL_USER:$GMAIL_PASS" | sudo tee /etc/postfix/sasl_passwd >/dev/null
sudo chmod 400 /etc/postfix/sasl_passwd
sudo postmap /etc/postfix/sasl_passwd
echo "smtp.gmail.com MUST" | sudo tee /etc/postfix/tls_per_site >/dev/null
sudo chmod 400 /etc/postfix/tls_per_site
sudo postmap /etc/postfix/tls_per_site

sudo service postfix restart
echo "Configuration done"

mail -s "Email relaying configured at ${HOST}" $GMAIL_USER <<__EOF
The postfix service has been configured at host '${HOST}'.
Thank you for using this postfix configuration script.
__EOF

echo "Please check your inbox at gmail." 

# end of sh
