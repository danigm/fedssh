# $Id: Makefile.in,v 1.5 2006/10/23 21:44:47 tim Exp $

prefix=/home/danigm/fed+ssh/openssh
datadir=${datarootdir}
datarootdir=${prefix}/share
srcdir=.
top_srcdir=..

INSTALL=/usr/bin/install -c



all:

#Ssh.bin:  Ssh.bin.uu
#	uudecode Ssh.bin.uu

clean:
#	rm -rf Ssh.bin

distprep:
	uudecode Ssh.bin.uu

distclean: clean
	rm -f Makefile *~

install: $(srcdir)/Ssh.bin
	$(top_srcdir)/mkinstalldirs $(DESTDIR)$(datadir)
	$(INSTALL) -m 0644 $(srcdir)/Ssh.bin $(DESTDIR)$(datadir)/Ssh.bin
